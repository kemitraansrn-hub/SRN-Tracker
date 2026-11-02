<?php

namespace App\Services\AkuisisiLeadImport;

use App\Models\AkuisisiLead;
use App\Models\AkuisisiPipelineStage;
use App\Support\AkuisisiPhoneNumber;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Memvalidasi baris file import menjadi: siap diimport, error (dengan alasan), atau dilewati
 * (nomor sudah ada di database / dobel di file). Tidak menyimpan apa pun. Diporting dari
 * App\Services\LeadImport\LeadImportValidator di PartnerAcquire CRM, DITAMBAH validasi 2 kolom
 * "Hasil Follow Up" & "Tanggal Next Follow Up" (lihat AkuisisiImportColumns) -- mengisi salah satu
 * menandai baris itu `is_legacy` (permintaan eksplisit 2026-11-02, biar data lead lama yang sudah
 * ada status/jadwalnya bisa dibawa langsung tanpa harus bikin riwayat follow-up palsu), DITAMBAH
 * lagi opsi `$overwrite` (permintaan eksplisit 2026-11-02 lainnya) buat upload ulang menimpa data
 * lead LAMA yang salah -- dibatasi cuma ke lead yang masih `is_legacy` DAN belum ada riwayat
 * follow-up ASLI yang dicatat CS di aplikasi ini (`followups_count === 0`), biar gak pernah
 * menimpa progress follow-up yang sudah benar-benar berjalan.
 */
class AkuisisiLeadImportValidator
{
    public const TYPE_ERROR = 'error';

    public const TYPE_EXISTING = 'existing';

    public const TYPE_DUPLICATE = 'duplicate';

    private const EARLIEST_DATE = '2020-01-01';

    /** @var array<string, array<string, int>> key kolom => [nilai ternormalisasi => id master] */
    private array $choices = [];

    /**
     * @param  array{headers: array, rows: array<int, array>}  $sheet
     * @return array{total: int, valid: array, problems: array, unknown_headers: string[]}
     */
    public function validate(array $sheet, bool $overwrite = false): array
    {
        [$columns, $unknown] = AkuisisiImportColumns::mapHeaders($sheet['headers']);

        $missing = collect(AkuisisiImportColumns::all())
            ->filter(fn (array $column, string $key) => $column[1] && ! in_array($key, $columns, true))
            ->map(fn (array $column) => $column[0]);
        if ($missing->isNotEmpty()) {
            throw new AkuisisiImportFileException('Kolom wajib tidak ditemukan: '.$missing->implode(', ').'. Gunakan template import terbaru.');
        }
        if (! $sheet['rows']) {
            throw new AkuisisiImportFileException('File tidak berisi baris data.');
        }

        $this->loadChoices();
        $existing = AkuisisiLead::withTrashed()->withCount('followups')->get(['id', 'whatsapp', 'is_legacy', 'deleted_at'])->keyBy('whatsapp');
        $defaultStage = AkuisisiPipelineStage::where('code', 'PROFILING')->value('id');
        $seen = [];
        $valid = [];
        $problems = [];

        foreach ($sheet['rows'] as $rowNumber => $row) {
            $values = [];
            foreach ($columns as $index => $key) {
                $values[$key] = $row[$index] ?? null;
            }

            [$attributes, $messages] = $this->validateRow($values, $defaultStage);
            $display = $this->displayValues($values);

            if ($messages) {
                $problems[] = ['row' => $rowNumber, 'type' => self::TYPE_ERROR, 'messages' => $messages, 'values' => $display];

                continue;
            }

            $phone = $attributes['whatsapp'];
            $match = $existing->get($phone);

            if (isset($seen[$phone])) {
                $problems[] = ['row' => $rowNumber, 'type' => self::TYPE_DUPLICATE, 'messages' => ["Nomor {$phone} dobel dengan baris {$seen[$phone]}"], 'values' => $display];
            } elseif ($match && $overwrite && $match->is_legacy && $match->followups_count === 0 && ! $match->deleted_at) {
                $seen[$phone] = $rowNumber;
                $attributes['is_legacy'] = true;
                $valid[] = ['row' => $rowNumber, 'attributes' => $attributes, 'values' => $display, 'overwrite_lead_id' => $match->id];
            } elseif ($match) {
                $reason = $overwrite ? match (true) {
                    (bool) $match->deleted_at => "Nomor {$phone} sudah ada tapi datanya terhapus -- gak bisa ditimpa lewat import",
                    ! $match->is_legacy || $match->followups_count > 0 => "Nomor {$phone} sudah ada & sudah pernah di-follow-up -- gak bisa ditimpa",
                    default => "Nomor {$phone} sudah ada di database",
                } : "Nomor {$phone} sudah ada di database";
                $problems[] = ['row' => $rowNumber, 'type' => self::TYPE_EXISTING, 'messages' => [$reason], 'values' => $display];
            } else {
                $seen[$phone] = $rowNumber;
                $valid[] = ['row' => $rowNumber, 'attributes' => $attributes, 'values' => $display];
            }
        }

        return ['total' => count($sheet['rows']), 'valid' => $valid, 'problems' => $problems, 'unknown_headers' => $unknown];
    }

    /** @return array{0: array, 1: string[]} */
    private function validateRow(array $values, ?int $defaultStage): array
    {
        $messages = [];
        $text = fn (string $key) => ($value = trim(preg_replace('/\s+/u', ' ', (string) ($values[$key] ?? '')))) === '' ? null : $value;

        $date = $this->parseDate($values['entry_date'] ?? null);
        if (! $date) {
            $messages[] = ($values['entry_date'] ?? null) === null || trim((string) $values['entry_date']) === ''
                ? 'Tanggal Masuk wajib diisi'
                : 'Tanggal Masuk tidak valid (gunakan DD/MM/YYYY)';
        } elseif ($date->isAfter(today())) {
            $messages[] = 'Tanggal Masuk tidak boleh melewati hari ini';
        } elseif ($date->isBefore(self::EARLIEST_DATE)) {
            $messages[] = 'Tanggal Masuk terlalu lama (sebelum 2020)';
        }

        $name = $text('name');
        if (! $name) {
            $messages[] = 'Nama Lead wajib diisi';
        } elseif (mb_strlen($name) > 150) {
            $messages[] = 'Nama Lead maksimal 150 karakter';
        }

        $city = $text('city');
        if ($city && mb_strlen($city) > 100) {
            $messages[] = 'Kota maksimal 100 karakter';
        }
        $note = $text('note');
        if ($note && mb_strlen($note) > 2000) {
            $messages[] = 'Catatan maksimal 2000 karakter';
        }

        $ids = [];
        foreach (['channel', 'business', 'interest', 'capital', 'stage', 'followup_result'] as $key) {
            $value = $text($key);
            $label = AkuisisiImportColumns::all()[$key][0];

            if ($value === null) {
                $ids[$key] = null;
                if ($key === 'channel') {
                    $messages[] = 'Channel Sumber wajib diisi';
                }

                continue;
            }

            $ids[$key] = $this->choices[$key][AkuisisiImportColumns::normalize($value)] ?? null;
            if (! $ids[$key]) {
                $messages[] = "{$label} \"{$value}\" tidak dikenal";
            }
        }

        // Format nomor WA (harus 62xxxxxxxxxx) gak dicek kalau baris ini ditandai "Invalid Number"
        // di Hasil Follow Up -- alasannya sudah tercatat di kolom itu, gak perlu diblok di import
        // (permintaan eksplisit 2026-11-02). Tetap wajib diisi sesuatu (kolom DB NOT NULL + UNIQUE).
        $invalidNumberStatusId = $this->choices['followup_result'][AkuisisiImportColumns::normalize('INVALID_NUMBER')] ?? null;
        $skipPhoneFormatCheck = $invalidNumberStatusId !== null && $ids['followup_result'] === $invalidNumberStatusId;

        $rawPhone = $values['whatsapp'] ?? null;
        $phone = AkuisisiPhoneNumber::normalize(is_float($rawPhone) ? number_format($rawPhone, 0, '', '') : (string) $rawPhone);
        if (! $phone) {
            $rawPhoneText = $text('whatsapp');
            if ($skipPhoneFormatCheck && $rawPhoneText) {
                $phone = mb_substr($rawPhoneText, 0, 20);
            } else {
                $messages[] = 'Nomor WhatsApp wajib diisi';
            }
        } elseif (! preg_match('/^62\d{8,13}$/', $phone) && ! $skipPhoneFormatCheck) {
            $messages[] = 'Nomor WhatsApp tidak valid';
        }

        // "Tanggal Next Follow Up" -- opsional, gak dibatasi rentang (boleh lampau buat lead yang
        // jadwalnya kelewat, atau masa depan buat yang baru dijadwalkan).
        $nextFollowupDate = null;
        $rawNextDate = $values['next_followup_date'] ?? null;
        if ($rawNextDate !== null && trim((string) $rawNextDate) !== '') {
            $nextFollowupDate = $this->parseDate($rawNextDate);
            if (! $nextFollowupDate) {
                $messages[] = 'Tanggal Next Follow Up tidak valid (gunakan DD/MM/YYYY)';
            }
        }

        // Isi salah satu dari 2 kolom "state follow-up" ini -> baris ditandai data lama (legacy),
        // biar skor/jadwalnya gak dihitung ulang otomatis dan nilai yang diimport dipertahankan.
        $isLegacy = $ids['followup_result'] !== null || $nextFollowupDate !== null;

        return [[
            'entry_date' => $date?->toDateString(),
            'name' => $name,
            'whatsapp' => $phone,
            'city' => $city,
            'channel_id' => $ids['channel'],
            'business_status_id' => $ids['business'],
            'interest_level_id' => $ids['interest'],
            'capital_status_id' => $ids['capital'],
            'pipeline_stage_id' => $ids['stage'] ?? $defaultStage,
            'latest_followup_status_id' => $ids['followup_result'],
            'next_followup_date' => $nextFollowupDate?->toDateString(),
            'is_legacy' => $isLegacy,
            'latest_note' => $note,
        ], $messages];
    }

    /**
     * Tanggal dari sel Excel (serial/DateTime) atau teks DD/MM/YYYY, DD-MM-YYYY, YYYY-MM-DD.
     */
    private function parseDate(mixed $value): ?Carbon
    {
        try {
            if ($value instanceof DateTimeInterface) {
                return Carbon::instance($value)->startOfDay();
            }
            if (is_int($value) || is_float($value) || (is_string($value) && preg_match('/^\d{5}(\.\d+)?$/', trim($value)))) {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->startOfDay();
            }

            $value = trim((string) $value);
            if (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})(\s|$)/', $value, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
                return Carbon::create((int) $m[3], (int) $m[2], (int) $m[1]);
            }
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(\s|T|$)/', $value, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                return Carbon::create((int) $m[1], (int) $m[2], (int) $m[3]);
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /** Nilai asli baris untuk ditampilkan di pratinjau & laporan error. */
    private function displayValues(array $values): array
    {
        $display = [];
        foreach (AkuisisiImportColumns::all() as $key => [$title]) {
            $value = $values[$key] ?? null;
            $display[$title] = match (true) {
                $value instanceof DateTimeInterface => $value->format('d/m/Y'),
                in_array($key, ['entry_date', 'next_followup_date'], true) && is_numeric($value) && ($date = $this->parseDate($value)) => $date->format('d/m/Y'),
                is_float($value) => number_format($value, 0, '', ''),
                default => $value === null ? null : (string) $value,
            };
        }

        return $display;
    }

    private function loadChoices(): void
    {
        foreach (AkuisisiImportColumns::all() as $key => [, , , $model]) {
            if (! $model) {
                continue;
            }
            $query = $model::query();
            if ($key === 'channel') {
                $query->where('is_active', true);
            }
            foreach ($query->get(['id', 'code', 'name']) as $option) {
                $this->choices[$key][AkuisisiImportColumns::normalize($option->name)] = $option->id;
                $this->choices[$key][AkuisisiImportColumns::normalize($option->code)] = $option->id;
            }
        }
    }
}
