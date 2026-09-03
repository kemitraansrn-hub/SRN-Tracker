<?php

namespace App\Http\Controllers;

use App\Models\BuybackRequest;
use App\Models\FollowupLog;
use App\Models\Order;
use App\Models\RunRateTarget;
use App\Models\TargetBulanan;
use App\Models\User;
use App\Services\SpecialDealPerformanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * "Sales Overview": ringkasan gaya executive dashboard (KPI tiles, sales
 * funnel, leaderboard KAE, komposisi brand/segmentasi, top mitra) untuk
 * bulan berjalan. KAE cuma lihat data dia sendiri (kae_code / kae_user_id),
 * Admin & Head lihat semua — sama pola scoping seperti Dashboard.
 */
class SalesOverviewController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $isKae = $user->role === 'kae';
        $kaeCode = $isKae ? $user->kae_code : null;

        $bulan = max(1, min(12, (int) $request->input('bulan', now()->month)));
        $tahun = max(2000, min(2100, (int) $request->input('tahun', now()->year)));
        $now = \Carbon\Carbon::create($tahun, $bulan, 1)->startOfMonth();
        $isBulanIni = $now->isSameMonth(today());

        $ordersBulanIni = Order::query()
            ->whereYear('tanggal_order', $now->year)
            ->whereMonth('tanggal_order', $now->month)
            ->when($kaeCode, fn ($q) => $q->where('orders.kae_code', $kaeCode));

        $totalOmset = (clone $ordersBulanIni)->sum('total_transaksi');
        $jumlahOrder = (clone $ordersBulanIni)->count();
        $mitraAktif = (clone $ordersBulanIni)->distinct('mitra_id')->count('mitra_id');
        $rataRataOrder = $jumlahOrder > 0 ? $totalOmset / $jumlahOrder : 0;

        // MTD vs bulan lalu di hari yang sama, biar adil (bulan berjalan
        // yang baru sebagian dibanding dengan potongan bulan lalu yang sama
        // panjangnya, bukan bulan lalu penuh).
        $prevMonthRef = $now->copy()->subMonthNoOverflow();
        // Bulan berjalan: bandingkan sampai hari ini (MTD asli). Bulan lain
        // (histori/masa depan): bandingkan sebulan penuh vs sebulan penuh.
        $dayCap = $isBulanIni ? min(today()->day, $prevMonthRef->daysInMonth) : $prevMonthRef->daysInMonth;
        $ordersMtdLalu = Order::query()
            ->whereBetween('tanggal_order', [
                $prevMonthRef->copy()->startOfMonth(),
                $prevMonthRef->copy()->startOfMonth()->addDays($dayCap - 1)->endOfDay(),
            ])
            ->when($kaeCode, fn ($q) => $q->where('orders.kae_code', $kaeCode));

        $omsetLalu = (clone $ordersMtdLalu)->sum('total_transaksi');
        $jumlahOrderLalu = (clone $ordersMtdLalu)->count();
        $mitraAktifLalu = (clone $ordersMtdLalu)->distinct('mitra_id')->count('mitra_id');
        $rataRataOrderLalu = $jumlahOrderLalu > 0 ? $omsetLalu / $jumlahOrderLalu : 0;

        $growthPct = fn ($ini, $lalu) => $lalu > 0 ? round((($ini - $lalu) / $lalu) * 100, 1) : null;

        $buybackPending = BuybackRequest::where('status', 'on-check')
            ->when($kaeCode, fn ($q) => $q->whereHas('mitra', fn ($m) => $m->where('kae_code', $kaeCode)))
            ->count();

        return view('sales-overview.index', [
            'periodeLabel' => $now->translatedFormat('F Y'),
            'bulanIni' => $now->month,
            'tahunIni' => $now->year,
            'isBulanIni' => $isBulanIni,
            'isKae' => $isKae,
            'totalOmset' => $totalOmset,
            'totalOmsetGrowth' => $growthPct($totalOmset, $omsetLalu),
            'jumlahOrder' => $jumlahOrder,
            'jumlahOrderGrowth' => $growthPct($jumlahOrder, $jumlahOrderLalu),
            'mitraAktif' => $mitraAktif,
            'mitraAktifGrowth' => $growthPct($mitraAktif, $mitraAktifLalu),
            'rataRataOrder' => $rataRataOrder,
            'rataRataOrderGrowth' => $growthPct($rataRataOrder, $rataRataOrderLalu),
            'buybackPending' => $buybackPending,
            'funnel' => $this->buildFunnel($now, $isKae, $user->id),
            'kaeAchievements' => $this->buildKaeAchievements($ordersBulanIni, $now, $user, $isKae),
            'brandSegments' => $this->buildBrandSegments($now, $kaeCode),
            'segmenSegments' => $this->buildSegmenSegments($now, $kaeCode),
            'bracketOmset' => $this->buildBracketOmset($ordersBulanIni),
            'top10Mitra' => $this->buildTop10Mitra($ordersBulanIni),
            'kaeContribSegments' => $isKae ? null : $this->buildKaeContribSegments($ordersBulanIni),
            'segmenContribSegments' => $isKae ? null : $this->buildSegmenContribSegments($now),
        ]);
    }

    /**
     * Corong konversi follow-up bulan ini: Total Di-follow-up -> Terhubung
     * -> Ada Belanja -> Belanja Penuh, dari FollowupLog.status_followup /
     * status_belanja. Setiap tahap dikasih % dari tahap sebelumnya (conversion
     * rate) dan lebar bar proporsional ke tahap pertama, sama gayanya kayak
     * funnel chart pada umumnya.
     */
    private function buildFunnel(\Carbon\Carbon $now, bool $isKae, int $userId): Collection
    {
        $fu = FollowupLog::query()
            ->whereYear('tanggal_fu', $now->year)
            ->whereMonth('tanggal_fu', $now->month)
            ->when($isKae, fn ($q) => $q->where('kae_user_id', $userId));

        $steps = [
            ['label' => 'Total Di-follow-up', 'value' => (clone $fu)->count()],
            ['label' => 'Terhubung', 'value' => (clone $fu)->where('status_followup', 'Terhubung')->count()],
            ['label' => 'Ada Belanja', 'value' => (clone $fu)->whereIn('status_belanja', ['Belanja Penuh', 'Belanja Sebagian'])->count()],
            ['label' => 'Belanja Penuh', 'value' => (clone $fu)->where('status_belanja', 'Belanja Penuh')->count()],
        ];

        $max = $steps[0]['value'] ?: 1;
        $prev = null;

        return collect($steps)->map(function ($s) use (&$prev, $max) {
            $convPct = $prev !== null && $prev > 0 ? round($s['value'] / $prev * 100, 1) : null;
            $widthPct = round($s['value'] / $max * 100, 1);
            $prev = $s['value'];

            return [...$s, 'conv_pct' => $convPct, 'width_pct' => max($widthPct, $s['value'] > 0 ? 4 : 0)];
        });
    }

    /**
     * Kartu pencapaian per KAE bulan ini: omset (+ % vs target bulanan),
     * mitra aktif (+ % vs target_mitra_aktif), reactivation, dan new mitra
     * — buat KAE cuma balik 1 kartu (dia sendiri), Admin/Head lihat semua
     * KAE diurut omset tertinggi.
     */
    private function buildKaeAchievements($ordersBulanIni, \Carbon\Carbon $now, User $user, bool $isKae): Collection
    {
        $kaeNames = $isKae ? [$user->kae_code => $user->name] : User::kaeNameMap();
        $photoMap = User::kaePhotoMap();

        $omsetPerKae = (clone $ordersBulanIni)
            ->selectRaw('kae_code, SUM(total_transaksi) as total')
            ->whereNotNull('kae_code')
            ->groupBy('kae_code')
            ->pluck('total', 'kae_code');

        $mitraAktifPerKae = (clone $ordersBulanIni)
            ->selectRaw('kae_code, COUNT(DISTINCT mitra_id) as jumlah')
            ->whereNotNull('kae_code')
            ->groupBy('kae_code')
            ->pluck('jumlah', 'kae_code');

        $omsetTargetPerKae = RunRateTarget::kaeTargetsMap($now->month, $now->year);
        $mitraAktifTargetPerKae = User::kaeTargetAktifMap();

        $reactivationByKae = SpecialDealPerformanceService::reactivationCandidates(
            $now->month,
            $now->year,
            $isKae ? $user->kae_code : null
        )->groupBy('kae_code');

        return collect($kaeNames)->map(function ($name, $code) use (
            $omsetPerKae, $mitraAktifPerKae, $omsetTargetPerKae, $mitraAktifTargetPerKae, $reactivationByKae, $photoMap
        ) {
            $rows = $reactivationByKae->get($code, collect());
            $initials = collect(preg_split('/\s+/', trim($name)))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('');

            $omset = (float) ($omsetPerKae[$code] ?? 0);
            $omsetTarget = $omsetTargetPerKae[$code] ?? null;
            $mitraAktif = (int) ($mitraAktifPerKae[$code] ?? 0);
            $mitraAktifTarget = $mitraAktifTargetPerKae[$code] ?? null;

            return [
                'kae_code' => $code,
                'name' => $name,
                'initials' => mb_strtoupper($initials) ?: '?',
                'photo_url' => $photoMap[$code] ?? null,
                'omset' => $omset,
                'omset_pct' => $omsetTarget ? round($omset / $omsetTarget * 100, 1) : null,
                'mitra_aktif' => $mitraAktif,
                'mitra_aktif_target' => $mitraAktifTarget,
                'mitra_aktif_pct' => $mitraAktifTarget ? round($mitraAktif / $mitraAktifTarget * 100, 1) : null,
                'reactivation' => $rows->where('is_new_mitra', false)->count(),
                'new_mitra' => $rows->where('is_new_mitra', true)->count(),
            ];
        })->sortByDesc('omset')->values();
    }

    private function buildBrandSegments(\Carbon\Carbon $now, ?string $kaeCode): array
    {
        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereYear('orders.tanggal_order', $now->year)
            ->whereMonth('orders.tanggal_order', $now->month)
            ->when($kaeCode, fn ($q) => $q->where('orders.kae_code', $kaeCode))
            ->selectRaw("COALESCE(order_items.brand, 'Lainnya') as brand, SUM(order_items.subtotal) as total")
            ->groupBy('brand')
            ->orderByDesc('total')
            ->get();

        $total = $rows->sum('total');

        return $rows->map(fn ($r) => [
            'label' => $r->brand,
            'pct' => $total > 0 ? round($r->total / $total * 100) : 0,
        ])->all();
    }

    private function buildSegmenSegments(\Carbon\Carbon $now, ?string $kaeCode): array
    {
        $rows = TargetBulanan::where('bulan', $now->month)
            ->where('tahun', $now->year)
            ->whereNotNull('segmen')
            ->when($kaeCode, fn ($q) => $q->whereHas('mitra', fn ($m) => $m->where('kae_code', $kaeCode)))
            ->selectRaw('segmen, COUNT(*) as jumlah')
            ->groupBy('segmen')
            ->orderByDesc('jumlah')
            ->get();

        $total = $rows->sum('jumlah');

        return $rows->map(fn ($r) => [
            'label' => $r->segmen,
            'pct' => $total > 0 ? round($r->jumlah / $total * 100) : 0,
        ])->all();
    }

    /** Jumlah mitra per bracket omset bulan ini, buat bar chart. */
    private function buildBracketOmset($ordersBulanIni): Collection
    {
        $omsetPerMitra = (clone $ordersBulanIni)
            ->selectRaw('mitra_id, SUM(total_transaksi) as total')
            ->groupBy('mitra_id')
            ->pluck('total');

        $brackets = [
            '< 5jt' => [0, 5_000_000],
            '5jt - 20jt' => [5_000_000, 20_000_000],
            '20jt - 50jt' => [20_000_000, 50_000_000],
            '> 50jt' => [50_000_000, PHP_INT_MAX],
        ];

        $max = 1;
        $result = collect($brackets)->map(function ($range, $label) use ($omsetPerMitra, &$max) {
            $jumlah = $omsetPerMitra->filter(fn ($v) => $v >= $range[0] && $v < $range[1])->count();
            $max = max($max, $jumlah);

            return ['label' => $label, 'jumlah' => $jumlah];
        })->values();

        return $result->map(fn ($r) => [...$r, 'pct' => round($r['jumlah'] / $max * 100, 1)]);
    }

    private function buildTop10Mitra($ordersBulanIni): Collection
    {
        $rows = (clone $ordersBulanIni)
            ->join('mitra', 'mitra.id', '=', 'orders.mitra_id')
            ->selectRaw('mitra.nama, mitra.kode_mitra, SUM(orders.total_transaksi) as total')
            ->groupBy('mitra.id', 'mitra.nama', 'mitra.kode_mitra')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $max = $rows->max('total') ?: 1;

        return $rows->map(fn ($r) => [
            'nama' => $r->nama,
            'kode_mitra' => $r->kode_mitra,
            'total' => (float) $r->total,
            'pct' => round($r->total / $max * 100, 1),
        ]);
    }

    /**
     * Kontribusi omset tiap segmen (Pareto/RTP/Special Reguler/Reguler/
     * Reactivation/New Mitra) terhadap total omset bulan ini — cuma buat
     * Admin/Head. Pakai SpecialDealPerformanceService yang sama dengan
     * kartu Special Deal Performance di Dashboard, biar angkanya konsisten.
     */
    private function buildSegmenContribSegments(\Carbon\Carbon $now): array
    {
        $rows = SpecialDealPerformanceService::summary($now->month, $now->year)
            ->where('segmen', '!=', 'All Chanel')
            ->filter(fn ($r) => $r->ach > 0);

        $total = $rows->sum('ach');

        return $rows->map(fn ($r) => [
            'label' => $r->segmen,
            'pct' => $total > 0 ? round($r->ach / $total * 100) : 0,
        ])->values()->all();
    }

    /** Kontribusi omset tiap KAE terhadap total omset bulan ini — cuma buat Admin/Head. */
    private function buildKaeContribSegments($ordersBulanIni): array
    {
        $kaeNames = User::kaeNameMap();

        $rows = (clone $ordersBulanIni)
            ->selectRaw('kae_code, SUM(total_transaksi) as total')
            ->whereNotNull('kae_code')
            ->groupBy('kae_code')
            ->orderByDesc('total')
            ->get();

        $total = $rows->sum('total');

        return $rows->map(fn ($r) => [
            'label' => $kaeNames[$r->kae_code] ?? $r->kae_code,
            'pct' => $total > 0 ? round($r->total / $total * 100) : 0,
        ])->all();
    }
}
