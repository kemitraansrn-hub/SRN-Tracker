<?php

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\Mitra;
use App\Models\TargetBulanan;
use App\Models\User;
use App\Services\Concerns\ParsesSpreadsheetHeaders;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Parses the monthly Target Bulanan export (per-mitra Komit/Target/Stretch)
 * and upserts target_bulanan rows for the chosen bulan/tahun.
 */
class TargetImportService
{
    use ParsesSpreadsheetHeaders;

    private const HEADER_ALIASES = [
        'kode_mitra' => ['KODE MITRA', 'RESELLER', 'KODE RESELLER', 'ID'],
        'nama' => ['NAMA MITRA', 'NAMA', 'NAME'],
        'kae' => ['KAE'],
        'segmen' => ['SEGMEN'],
        'komit' => ['KOMIT (RP)', 'KOMIT'],
        'target' => ['TARGET (RP)', 'TARGET'],
        'stretch' => ['STRETCH (RP)', 'STRETCH'],
        'target_mou' => ['TARGET MOU (RP)', 'TARGET MOU'],
    ];

    /**
     * @return array{ok: bool, errors: array<int, string>, jumlah_baris?: int, batch?: ImportBatch}
     */
    public function import(UploadedFile $file, int $bulan, int $tahun, User $user, string $namaFile): array
    {
        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
        } catch (\Throwable $e) {
            return ['ok' => false, 'errors' => ['File tidak bisa dibaca: '.$e->getMessage()]];
        }

        $columnMap = null;
        $sheet = null;

        foreach ($spreadsheet->getAllSheets() as $candidate) {
            $headerMap = $this->readHeaderMap($candidate);

            if (isset($headerMap['KOMIT (RP)']) || isset($headerMap['KOMIT'])) {
                $sheet = $candidate;
                $columnMap = $this->resolveAliases($headerMap, self::HEADER_ALIASES);
                break;
            }
        }

        if (! $sheet || ! isset($columnMap['kode_mitra'], $columnMap['target'])) {
            return ['ok' => false, 'errors' => [
                'Format file tidak dikenali. File harus punya kolom Kode Mitra (RESELLER) dan Target (Rp), idealnya juga Komit dan Stretch.',
            ]];
        }

        $rows = [];
        $highestRow = $sheet->getHighestDataRow();

        for ($r = 2; $r <= $highestRow; $r++) {
            $row = [];
            $isEmpty = true;

            foreach ($columnMap as $key => $colIndex) {
                $coordinate = Coordinate::stringFromColumnIndex($colIndex).$r;
                $raw = $sheet->getCell($coordinate)->getValue();

                if (in_array($key, ['komit', 'target', 'stretch', 'target_mou'], true)) {
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

        $kaeCodeByName = User::where('role', 'kae')->get()->keyBy(fn ($u) => mb_strtolower($u->name));

        $batch = DB::transaction(function () use ($rows, $bulan, $tahun, $user, $namaFile, $kaeCodeByName) {
            $batch = ImportBatch::create([
                'jenis' => 'target_bulanan',
                'bulan' => $bulan,
                'tahun' => $tahun,
                'nama_file' => $namaFile,
                'uploaded_by' => $user->id,
                'jumlah_baris' => count($rows),
                'status' => 'berhasil',
            ]);

            foreach ($rows as $row) {
                if (! $row['kode_mitra'] || $row['target'] === null) {
                    continue;
                }

                $mitra = Mitra::firstOrNew(['kode_mitra' => $row['kode_mitra']]);
                $isNew = ! $mitra->exists;

                if ($isNew) {
                    $mitra->nama = $row['nama'] ?: $row['kode_mitra'];
                    $mitra->status = 'aktif';
                }

                // Order import (ID SALESMAN) is the authoritative KAE binding.
                // Only use the Target file's KAE column as a fallback for mitra
                // that don't have a binding yet (e.g. targeted before their first order).
                if (empty($mitra->kae_code) && ! empty($row['kae'])) {
                    $kaeUser = $kaeCodeByName->get(mb_strtolower($row['kae']));
                    if ($kaeUser) {
                        $mitra->kae_code = $kaeUser->kae_code;
                    }
                }

                if ($isNew || $mitra->isDirty()) {
                    $mitra->save();
                }

                TargetBulanan::updateOrCreate(
                    ['mitra_id' => $mitra->id, 'bulan' => $bulan, 'tahun' => $tahun],
                    [
                        'segmen' => $row['segmen'] ?? 'REGULER',
                        'komit' => $row['komit'] ?? null,
                        'target' => $row['target'],
                        'stretch' => $row['stretch'] ?? null,
                        'target_mou' => $row['target_mou'] ?? null,
                        'import_batch_id' => $batch->id,
                    ]
                );
            }

            return $batch;
        });

        return ['ok' => true, 'errors' => [], 'jumlah_baris' => count($rows), 'batch' => $batch];
    }
}
