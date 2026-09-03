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

    // Nilai Segmen dipakai buat exact-match string di beberapa tempat lain
    // (ForecastController, dsb), jadi harus konsisten persis hurufnya.
    // File upload beda bulan sering ditulis format beda-beda (title case,
    // RTP disingkat tanpa keterangan) — dinormalisasi di sini sekali,
    // bukan di tiap controller yang makai.
    private const SEGMEN_ALIASES = [
        'RTP' => 'RTP (ROAD TO PARETO)',
        'ROAD TO PARETO' => 'RTP (ROAD TO PARETO)',
    ];

    private function normalizeSegmen(?string $raw): string
    {
        $upper = mb_strtoupper(trim((string) $raw));

        return self::SEGMEN_ALIASES[$upper] ?? ($upper !== '' ? $upper : 'REGULER');
    }

    private const HEADER_ALIASES = [
        'kode_mitra' => ['KODE MITRA', 'RESELLER', 'KODE RESELLER', 'ID'],
        'nama' => ['NAMA MITRA', 'NAMA', 'NAME'],
        'kae' => ['KAE'],
        'segmen' => ['SEGMEN'],
        'tier_dipakai' => ['TIER DIPAKAI', 'TIER'],
        'komit' => ['KOMIT (RP)', 'KOMIT'],
        'target' => ['TARGET (RP)', 'TARGET'],
        'stretch' => ['STRETCH (RP)', 'STRETCH'],
        'target_mou' => ['TARGET MOU (RP)', 'TARGET MOU'],
        'kategori' => ['KATEGORI'],
        'keterangan' => ['KETERANGAN', 'CATATAN'],
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

                if (in_array($key, ['komit', 'target', 'stretch', 'target_mou'], true)) {
                    $value = is_numeric($raw) ? (float) $raw : null;
                    if ($key === 'target') {
                        $row['_raw_target'] = $raw;
                    }
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
        $skipped = [];

        $batch = DB::transaction(function () use ($rows, $bulan, $tahun, $user, $namaFile, $kaeCodeByName, &$skipped) {
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
                if (! $row['kode_mitra']) {
                    $skipped[] = 'Baris '.$row['_baris'].': Kode Mitra kosong.';

                    continue;
                }

                if ($row['target'] === null) {
                    $rawTarget = $row['_raw_target'];
                    $tampil = $rawTarget === null || $rawTarget === '' ? '(kosong)' : (is_string($rawTarget) ? $rawTarget : json_encode($rawTarget));
                    $skipped[] = 'Baris '.$row['_baris'].' ('.$row['kode_mitra'].'): kolom Target bukan angka, nilainya: '.$tampil;

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

                $attributes = [
                    'segmen' => $this->normalizeSegmen($row['segmen'] ?? null),
                    'komit' => $row['komit'] ?? null,
                    'target' => $row['target'],
                    'stretch' => $row['stretch'] ?? null,
                    'target_mou' => $row['target_mou'] ?? null,
                    'kategori' => $row['kategori'] ?? null,
                    'keterangan' => $row['keterangan'] ?? null,
                    'import_batch_id' => $batch->id,
                ];

                // Only set tier_dipakai from the file when it has a
                // recognizable value, so re-importing without that column
                // doesn't wipe out a tier someone picked manually.
                $tierRaw = $row['tier_dipakai'] ?? null;
                $tierNormalized = $tierRaw ? mb_strtolower(trim($tierRaw)) : null;
                if (in_array($tierNormalized, \App\Models\TargetBulanan::TIERS, true)) {
                    $attributes['tier_dipakai'] = $tierNormalized;
                }

                TargetBulanan::updateOrCreate(
                    ['mitra_id' => $mitra->id, 'bulan' => $bulan, 'tahun' => $tahun],
                    $attributes
                );
            }

            return $batch;
        });

        return [
            'ok' => true,
            'errors' => [],
            'jumlah_baris' => count($rows),
            'jumlah_tersimpan' => count($rows) - count($skipped),
            'skipped' => $skipped,
            'batch' => $batch,
        ];
    }
}
