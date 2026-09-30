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
 *
 * Dua tahap: parse() cuma baca file + cocokkan tiap baris ke mitra yang ada
 * (buat ditampilkan sebagai preview "baris X akan masuk ke mitra Y" sebelum
 * disimpan beneran) — TIDAK nulis apa-apa ke database. commit() yang baru
 * nulis, dipanggil setelah user konfirmasi preview-nya.
 *
 * Sejak kolom ID/Kode Mitra dihapus dari template, pencocokan default-nya
 * berdasarkan Nama Mitra persis (exact match, case-insensitive) ke
 * mitra.nama. File lama yang masih punya kolom ID/Kode Mitra tetap
 * didukung (dicocokkan by kode, lebih akurat, diprioritaskan kalau ada).
 * Nama yang gak ketemu SAMA SEKALI tetap masuk sebagai mitra baru tanpa
 * kode_mitra (kolom itu sekarang nullable) — bukan di-skip — supaya gak
 * ada data target yang hilang; admin tinggal diingetkan lewat notifikasi
 * buat isi kode_mitra-nya belakangan.
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
     * Baca file, cocokkan tiap baris ke mitra yang ada (by kode kalau ada
     * kolomnya, kalau enggak by nama persis) — TIDAK menyimpan apa pun.
     *
     * @return array{ok: bool, errors: array<int, string>, rows?: array<int, array>, has_kode_column?: bool}
     */
    public function parse(UploadedFile $file): array
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

        if (! $sheet || ! isset($columnMap['nama'], $columnMap['target'])) {
            return ['ok' => false, 'errors' => [
                'Format file tidak dikenali. File harus punya kolom Nama Mitra dan Target (Rp), idealnya juga Komit dan Stretch.',
            ]];
        }

        $hasKodeColumn = isset($columnMap['kode_mitra']);

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

        $this->resolveMatches($rows, $hasKodeColumn);

        return ['ok' => true, 'errors' => [], 'rows' => $rows, 'has_kode_column' => $hasKodeColumn];
    }

    /**
     * Nempelin hasil pencocokan ke tiap baris (mutasi langsung): match_status
     * ('kode'|'nama'|'baru'), matched_mitra_id, matched_nama, matched_kode.
     *
     * @param  array<int, array>  $rows
     */
    private function resolveMatches(array &$rows, bool $hasKodeColumn): void
    {
        if ($hasKodeColumn) {
            $kodeList = collect($rows)->pluck('kode_mitra')->filter()->unique();
            $mitraByKode = Mitra::whereIn('kode_mitra', $kodeList)->get()->keyBy('kode_mitra');

            foreach ($rows as &$row) {
                $kode = $row['kode_mitra'] ?? null;
                $mitra = $kode ? $mitraByKode->get($kode) : null;

                if ($mitra) {
                    $row['match_status'] = 'kode';
                    $row['matched_mitra_id'] = $mitra->id;
                    $row['matched_nama'] = $mitra->nama;
                    $row['matched_kode'] = $mitra->kode_mitra;
                } else {
                    $row['match_status'] = 'baru';
                    $row['matched_mitra_id'] = null;
                    $row['matched_nama'] = null;
                    $row['matched_kode'] = $kode;
                }
            }

            return;
        }

        // PENTING: Eloquent Collection::only() di-override buat filter
        // berdasarkan PRIMARY KEY model, bukan array key biasa kayak
        // Illuminate\Support\Collection — jadi ->only($namaLower) di sini
        // SELALU balik kosong (nama bukan primary key). ->collect() dulu
        // buat downgrade ke base Collection sebelum filter by nama.
        $namaList = collect($rows)->pluck('nama')->filter()->map(fn ($n) => mb_strtolower(trim($n)))->unique();
        $mitraByNamaLower = Mitra::get(['id', 'nama', 'kode_mitra'])
            ->keyBy(fn ($m) => mb_strtolower(trim($m->nama)))
            ->toBase()
            ->only($namaList->all());

        foreach ($rows as &$row) {
            $namaLower = mb_strtolower(trim((string) ($row['nama'] ?? '')));
            $mitra = $mitraByNamaLower->get($namaLower);

            if ($mitra) {
                $row['match_status'] = 'nama';
                $row['matched_mitra_id'] = $mitra->id;
                $row['matched_nama'] = $mitra->nama;
                $row['matched_kode'] = $mitra->kode_mitra;
            } else {
                $row['match_status'] = 'baru';
                $row['matched_mitra_id'] = null;
                $row['matched_nama'] = null;
                $row['matched_kode'] = null;
            }
        }
    }

    /**
     * Simpan beneran ke database — dipanggil setelah user konfirmasi hasil
     * parse()+resolveMatches() di halaman preview.
     *
     * @param  array<int, array>  $rows  hasil parse()['rows'] (sudah ada match_status dkk)
     * @return array{ok: bool, errors: array<int, string>, jumlah_baris?: int, jumlah_tersimpan?: int, jumlah_mitra_baru?: int, skipped?: array, batch?: ImportBatch}
     */
    public function commit(array $rows, int $bulan, int $tahun, User $user, string $namaFile): array
    {
        $kaeCodeByName = User::where('role', 'kae')->get()->keyBy(fn ($u) => mb_strtolower($u->name));
        $skipped = [];
        $mitraBaruCount = 0;

        $batch = DB::transaction(function () use ($rows, $bulan, $tahun, $user, $namaFile, $kaeCodeByName, &$skipped, &$mitraBaruCount) {
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
                if ($row['target'] === null) {
                    $rawTarget = $row['_raw_target'] ?? null;
                    $tampil = $rawTarget === null || $rawTarget === '' ? '(kosong)' : (is_string($rawTarget) ? $rawTarget : json_encode($rawTarget));
                    $skipped[] = 'Baris '.$row['_baris'].' ('.($row['nama'] ?? '?').'): kolom Target bukan angka, nilainya: '.$tampil;

                    continue;
                }

                if (($row['match_status'] ?? 'baru') !== 'baru' && $row['matched_mitra_id']) {
                    $mitra = Mitra::find($row['matched_mitra_id']);
                    if (! $mitra) {
                        $skipped[] = 'Baris '.$row['_baris'].': mitra yang tadinya cocok udah gak ada (mungkin kehapus).';

                        continue;
                    }
                } else {
                    if (! $row['nama']) {
                        $skipped[] = 'Baris '.$row['_baris'].': Nama Mitra kosong, gak bisa dibuat mitra baru.';

                        continue;
                    }

                    $mitra = new Mitra();
                    $mitra->nama = $row['nama'];
                    $mitra->kode_mitra = $row['kode_mitra'] ?? null;
                    $mitra->status = 'aktif';
                    $mitraBaruCount++;
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

                if (! $mitra->exists || $mitra->isDirty()) {
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
            'jumlah_mitra_baru' => $mitraBaruCount,
            'skipped' => $skipped,
            'batch' => $batch,
        ];
    }
}
