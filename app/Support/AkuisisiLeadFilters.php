<?php

namespace App\Support;

use App\Models\AkuisisiFollowupStatus;
use App\Models\AkuisisiPriority;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Filter dashboard dari query string. Satu sumber aturan untuk kartu rekap,
 * tab, tabel, dan matriks pipeline. Diporting dari App\Support\LeadFilters
 * di PartnerAcquire CRM -- logic sama persis.
 *
 * - period  : all | day | month | range (berdasarkan tanggal masuk lead)
 * - tab     : todo (jadwal <= hari ini) | active (status belum diarsipkan) | archive
 * - pipeline: id tahapan | none ; status: id status follow-up | none (belum di-follow-up)
 */
class AkuisisiLeadFilters
{
    public const TABS = ['todo', 'active', 'archive'];

    public const PERIODS = ['all', 'day', 'month', 'range'];

    public const NONE = 'none';

    private ?array $archivedStatusIds = null;

    public function __construct(
        public readonly string $tab = 'active',
        public readonly string $search = '',
        public readonly ?string $priority = null,
        public readonly ?string $pipeline = null,
        public readonly ?string $status = null,
        public readonly string $period = 'all',
        public readonly ?string $day = null,
        public readonly ?string $month = null,
        public readonly ?string $from = null,
        public readonly ?string $to = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $pick = fn (string $key, array $allowed, string $default) => in_array($request->query($key), $allowed, true) ? $request->query($key) : $default;
        $idOrNone = fn (string $key) => ($value = $request->query($key)) === self::NONE || ctype_digit((string) $value) ? $value : null;

        return new self(
            tab: $pick('tab', self::TABS, 'active'),
            search: trim((string) $request->query('q')),
            priority: in_array($request->query('priority'), ['HOT', 'WARM', 'COLD'], true) ? $request->query('priority') : null,
            pipeline: $idOrNone('pipeline'),
            status: $idOrNone('status'),
            period: $pick('period', self::PERIODS, 'all'),
            day: self::validDate($request->query('day'), 'Y-m-d'),
            month: self::validDate($request->query('month'), 'Y-m'),
            from: self::validDate($request->query('from'), 'Y-m-d'),
            to: self::validDate($request->query('to'), 'Y-m-d'),
        );
    }

    private static function validDate(mixed $value, string $format): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat("!{$format}", $value)->format($format) === $value ? $value : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** Rentang tanggal masuk [dari, sampai] sesuai periode; null berarti tanpa batas. */
    public function periodRange(): array
    {
        return match ($this->period) {
            'day' => [$this->day, $this->day],
            'month' => $this->month
                ? [Carbon::parse("{$this->month}-01")->toDateString(), Carbon::parse("{$this->month}-01")->endOfMonth()->toDateString()]
                : [null, null],
            'range' => [$this->from, $this->to],
            default => [null, null],
        };
    }

    public function periodLabel(): string
    {
        [$from, $to] = $this->periodRange();
        $format = fn (string $date) => Carbon::parse($date)->translatedFormat('d M Y');

        return match (true) {
            $this->period === 'month' && $from !== null => Carbon::parse($from)->translatedFormat('F Y'),
            $from !== null && $from === $to => $format($from),
            $from !== null && $to !== null => $format($from).' – '.$format($to),
            $from !== null => 'Sejak '.$format($from),
            $to !== null => 'Sampai '.$format($to),
            default => 'Semua waktu',
        };
    }

    public function applyPeriod(Builder $query): Builder
    {
        [$from, $to] = $this->periodRange();

        return $query
            ->when($from, fn ($q) => $q->where('entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('entry_date', '<=', $to));
    }

    /** Filter selain tab: periode, pencarian, prioritas, pipeline, status. */
    public function applyFilters(Builder $query): Builder
    {
        $this->applyPeriod($query);

        if ($this->search !== '') {
            $search = $this->search;
            $digits = ltrim(preg_replace('/\D+/', '', $search), '0');

            $query->where(function ($query) use ($search, $digits) {
                $query->where('name', 'like', "%{$search}%")->orWhere('city', 'like', "%{$search}%");

                if (strlen($digits) >= 3) {
                    $query->orWhere('whatsapp', 'like', "%{$digits}%");
                }
            });
        }

        if ($this->priority) {
            $query->where('priority_id', AkuisisiPriority::where('code', $this->priority)->value('id'));
        }

        match ($this->pipeline) {
            null => null,
            self::NONE => $query->whereNull('pipeline_stage_id'),
            default => $query->where('pipeline_stage_id', $this->pipeline),
        };

        match ($this->status) {
            null => null,
            self::NONE => $query->whereNull('latest_followup_status_id'),
            default => $query->where('latest_followup_status_id', $this->status),
        };

        return $query;
    }

    public function applyTab(Builder $query, ?string $tab = null): Builder
    {
        return match ($tab ?? $this->tab) {
            'todo' => $query->whereNotNull('next_followup_date')->where('next_followup_date', '<=', today()->toDateString()),
            'archive' => $query->whereIn('latest_followup_status_id', $this->archivedStatusIds()),
            default => $this->whereActive($query),
        };
    }

    public function whereActive(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q
            ->whereNull('latest_followup_status_id')
            ->orWhereNotIn('latest_followup_status_id', $this->archivedStatusIds()));
    }

    public function orderForTab(Builder $query): Builder
    {
        return match ($this->tab) {
            // Paling lama terlewat dulu, lalu skor tertinggi (Hot) dulu.
            'todo' => $query->orderBy('next_followup_date')->orderByDesc('score')->orderBy('id'),
            'archive' => $query->orderByDesc('last_followup_date')->orderByDesc('id'),
            default => $query->orderByRaw('next_followup_date IS NULL')->orderBy('next_followup_date')->orderByDesc('entry_date'),
        };
    }

    public function archivedStatusIds(): array
    {
        return $this->archivedStatusIds ??= AkuisisiFollowupStatus::where('is_archived', true)->pluck('id')->all();
    }

    /** Apakah ada filter selain tab & periode yang aktif. */
    public function hasListFilters(): bool
    {
        return $this->search !== '' || $this->priority || $this->pipeline || $this->status;
    }

    /** Jumlah filter aktif (pencarian, prioritas, pipeline, hasil follow-up, periode) untuk tombol Reset. */
    public function activeFilterCount(): int
    {
        return count(array_filter([$this->search !== '', $this->priority, $this->pipeline, $this->status, $this->hasPeriod()]));
    }

    public function hasPeriod(): bool
    {
        return $this->periodRange() !== [null, null];
    }

    /** Parameter query saat ini (tanpa nilai kosong), untuk membangun URL. */
    public function query(array $overrides = []): array
    {
        $params = [
            'tab' => $this->tab,
            'q' => $this->search,
            'priority' => $this->priority,
            'pipeline' => $this->pipeline,
            'status' => $this->status,
            'period' => $this->period === 'all' ? null : $this->period,
            'day' => $this->period === 'day' ? $this->day : null,
            'month' => $this->period === 'month' ? $this->month : null,
            'from' => $this->period === 'range' ? $this->from : null,
            'to' => $this->period === 'range' ? $this->to : null,
        ];

        return array_filter([...$params, ...$overrides], fn ($value) => $value !== null && $value !== '');
    }

    /** Parameter periode saja, untuk tautan kartu rekap & matriks. */
    public function periodQuery(array $overrides = []): array
    {
        return $this->query(['q' => null, 'priority' => null, 'pipeline' => null, 'status' => null, ...$overrides]);
    }
}
