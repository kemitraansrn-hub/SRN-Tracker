<?php

namespace App\Services;

use App\Models\Mitra;
use App\Models\SpecialDeal;
use App\Models\User;
use App\Services\Concerns\ParsesSpreadsheetHeaders;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Parses a per-quarter Special Deal upload (one row per mitra) and
 * upserts special_deals rows keyed on (mitra_id, kuartal, tahun), so
 * re-uploading a corrected file for the same quarter updates in place
 * instead of creating duplicates. KAE-nya BUKAN dari kolom di file (file
 * gak punya kolom KAE lagi) — diambil otomatis dari mitra.kae_code yang
 * sudah diisi di menu Data Mitra. Mitra yang belum ada KAE-nya di Data
 * Mitra dilewati dengan catatan, bukan dipaksa pakai KAE uploader.
 */
class SpecialDealImportService
{
    use ParsesSpreadsheetHeaders;

    private const HEADER_ALIASES = [
        'kode_mitra' => ['KODE MITRA', 'RESELLER', 'KODE RESELLER'],
        'nama' => ['NAMA MITRA', 'NAMA', 'NAME'],
        'segmen' => ['SEGMENTASI', 'SEGMEN'],
        'deskripsi' => ['DESKRIPSI', 'DESKRIPSI DEAL'],
        'kuartal' => ['KUARTAL'],
        'tahun' => ['TAHUN'],
        'target_kuartal' => ['TARGET KUARTAL (RP)', 'TARGET KUARTAL'],
        'budget_persen' => ['BUDGET (%)', 'BUDGET'],
        'subsidi' => ['SUBSIDI'],
        'status' => ['STATUS MOU', 'STATUS'],
    ];

    /**
     * @return array{ok: bool, errors: array<int, string>, jumlah_baris?: int, jumlah_tersimpan?: int, skipped?: array<int, string>}
     */
    public function import(UploadedFile $file, User $user): array
    {
        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
        } catch (\Throwable $e) {
            return ['ok' => false, 'errors' => ['File tidak bisa dibaca: '.$e->getMessage()]];
        }

        $sheet = $spreadsheet->getSheet(0);
        $headerMap = $this->readHeaderMap($sheet);
        $columnMap = $this->resolveAliases($headerMap, self::HEADER_ALIASES);

        if (! isset($columnMap['kode_mitra'], $columnMap['kuartal'], $columnMap['tahun'], $columnMap['target_kuartal'])) {
            return ['ok' => false, 'errors' => [
                'Format file tidak dikenali. File harus punya kolom Kode Mitra, Kuartal, Tahun, dan Target Kuartal minimal.',
            ]];
        }

        $rows = [];
        $highestRow = $sheet->getHighestDataRow();

        for ($r = 2; $r <= $highestRow; $r++) {
            $row = ['_baris' => $r];
            $isEmpty = true;

            foreach ($columnMap as $key => $colIndex) {
                $coordinate = Coordinate::stringFromColumnIndex($colIndex).$r;
                $cell = $sheet->getCell($coordinate);

                try {
                    $raw = $cell->isFormula() ? $cell->getCalculatedValue() : $cell->getValue();
                } catch (\Throwable) {
                    $raw = $cell->getValue();
                }

                if (in_array($key, ['kuartal', 'tahun', 'target_kuartal', 'budget_persen'], true)) {
                    $value = is_numeric($raw) ? (float) $raw : null;
                } else {
                    $value = $raw === null ? null : trim((string) $raw);
                }

                if ($value !== null && $value !== '') {
                    $isEmpty = false;
                }

                $row[$key] = $value;
            }

            if (! $isEmpty) {
                $rows[] = $row;
            }
        }

        if (empty($rows)) {
            return ['ok' => false, 'errors' => ['Sheet tidak berisi data.']];
        }

        $kaeUserByCode = User::where('role', 'kae')->whereNotNull('kae_code')->get()->keyBy('kae_code');
        $skipped = [];
        $saved = 0;

        DB::transaction(function () use ($rows, $kaeUserByCode, &$skipped, &$saved) {
            foreach ($rows as $row) {
                $label = 'Baris '.$row['_baris'].($row['kode_mitra'] ? ' ('.$row['kode_mitra'].')' : '');

                if (! $row['kode_mitra']) {
                    $skipped[] = $label.': Kode Mitra kosong.';

                    continue;
                }

                $mitra = Mitra::where('kode_mitra', $row['kode_mitra'])->first();
                if (! $mitra) {
                    $skipped[] = $label.': Mitra dengan kode "'.$row['kode_mitra'].'" tidak ditemukan.';

                    continue;
                }

                // KAE otomatis dari mitra.kae_code (Data Mitra) — bukan
                // dari file ini lagi.
                if (! $mitra->kae_code || ! $kaeUserByCode->has($mitra->kae_code)) {
                    $skipped[] = $label.': Mitra ini belum ada KAE-nya di Data Mitra — isi KAE mitra dulu di menu Data Mitra sebelum upload Special Deal.';

                    continue;
                }
                $kaeUserId = $kaeUserByCode[$mitra->kae_code]->id;

                $kuartal = (int) ($row['kuartal'] ?? 0);
                if ($kuartal < 1 || $kuartal > 4) {
                    $skipped[] = $label.': Kuartal harus 1-4.';

                    continue;
                }

                $tahun = (int) ($row['tahun'] ?? 0);
                if ($tahun < 2020 || $tahun > 2100) {
                    $skipped[] = $label.': Tahun tidak valid.';

                    continue;
                }

                if ($row['target_kuartal'] === null || $row['target_kuartal'] < 0) {
                    $skipped[] = $label.': Target Kuartal bukan angka yang valid.';

                    continue;
                }

                if ($row['budget_persen'] === null || $row['budget_persen'] < 0 || $row['budget_persen'] > 100) {
                    $skipped[] = $label.': Budget (%) bukan angka 0-100 yang valid.';

                    continue;
                }

                if (! $row['subsidi']) {
                    $skipped[] = $label.': Subsidi kosong.';

                    continue;
                }

                $segmen = $row['segmen'] ? mb_strtoupper(trim($row['segmen'])) : null;
                if ($segmen && ! in_array($segmen, SpecialDeal::SEGMEN_OPTIONS, true)) {
                    $skipped[] = $label.': Segmentasi "'.$row['segmen'].'" tidak dikenali. Pilihan: '.implode(', ', SpecialDeal::SEGMEN_OPTIONS).'.';

                    continue;
                }

                $statusRaw = $row['status'] ? mb_strtolower(trim($row['status'])) : null;
                $status = in_array($statusRaw, \App\Http\Controllers\SpecialDealController::STATUSES, true) ? $statusRaw : 'proses';

                SpecialDeal::updateOrCreate(
                    ['mitra_id' => $mitra->id, 'kuartal' => $kuartal, 'tahun' => $tahun],
                    [
                        'kae_user_id' => $kaeUserId,
                        'segmen' => $segmen,
                        'deskripsi' => $row['deskripsi'] ?: 'Special Deal Q'.$kuartal.' '.$tahun,
                        'target_kuartal' => $row['target_kuartal'],
                        'budget_persen' => $row['budget_persen'],
                        'subsidi' => $row['subsidi'],
                        'status' => $status,
                    ]
                );

                $saved++;
            }
        });

        return [
            'ok' => true,
            'errors' => [],
            'jumlah_baris' => count($rows),
            'jumlah_tersimpan' => $saved,
            'skipped' => $skipped,
        ];
    }
}
