<?php

namespace App\Services;

use App\Models\Mitra;
use App\Services\Concerns\ParsesSpreadsheetHeaders;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Parses the "Master Mitra" reference file (satu baris per reseller, kolom
 * Reseller ID/Name/Phone/Alamat/dst — lihat contoh yang dikirim user) dan
 * di-upsert ke tabel mitra dengan Reseller ID sebagai lookup key, yang
 * ternyata format-nya SAMA PERSIS dengan kode_mitra yang sudah dipakai di
 * sistem (REB2025..., REC2025..., dst). Mitra yang cocok kode_mitra-nya
 * dilengkapi/ditimpa data kontak & alamatnya; yang belum ada di sistem
 * otomatis dibuat sebagai mitra baru (status aktif, KAE belum
 * ditentukan — harus diisi manual belakangan). Segmen SENGAJA tidak
 * disentuh di sini — itu datang dari menu Special Deal, bukan dari file
 * ini (lihat SpecialDealPerformanceService/MitraController::index()).
 */
class MasterMitraImportService
{
    use ParsesSpreadsheetHeaders;

    private const HEADER_ALIASES = [
        'kode_mitra' => ['RESELLER ID', 'KODE MITRA', 'KODE RESELLER'],
        'nama' => ['NAME', 'NAMA', 'NAMA MITRA'],
        'no_wa' => ['PHONE', 'NO WA', 'NO. WA', 'WHATSAPP', 'WA'],
        'alamat' => ['ALAMAT PENGIRIMAN', 'ALAMAT'],
        'provinsi' => ['PROVINSI'],
        'kota' => ['KOTA/KAB', 'KOTA/KABUPATEN', 'KOTA', 'KABUPATEN'],
        'kecamatan' => ['KECAMATAN'],
        'desa' => ['DESA', 'KELURAHAN'],
        'kodepos' => ['KODEPOS', 'KODE POS'],
    ];

    /**
     * @return array{ok: bool, errors: array<int, string>, jumlah_baris?: int, jumlah_diperbarui?: int, jumlah_dibuat?: int, skipped?: array<int, string>}
     */
    public function import(UploadedFile $file): array
    {
        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
        } catch (\Throwable $e) {
            return ['ok' => false, 'errors' => ['File tidak bisa dibaca: '.$e->getMessage()]];
        }

        $sheet = $spreadsheet->getSheet(0);
        $headerMap = $this->readHeaderMap($sheet);
        $columnMap = $this->resolveAliases($headerMap, self::HEADER_ALIASES);

        if (! isset($columnMap['kode_mitra'], $columnMap['nama'])) {
            return ['ok' => false, 'errors' => [
                'Format file tidak dikenali. File harus punya kolom Reseller ID (kode mitra) dan Name (nama) minimal.',
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

                $value = $raw === null ? null : trim((string) $raw);

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

        $skipped = [];
        $updated = 0;
        $created = 0;

        DB::transaction(function () use ($rows, &$skipped, &$updated, &$created) {
            foreach ($rows as $row) {
                $label = 'Baris '.$row['_baris'].($row['kode_mitra'] ? ' ('.$row['kode_mitra'].')' : '');

                if (! $row['kode_mitra']) {
                    $skipped[] = $label.': Reseller ID kosong.';

                    continue;
                }

                $mitra = Mitra::where('kode_mitra', $row['kode_mitra'])->first();

                $alamatFields = [
                    'no_wa' => $row['no_wa'] ?: null,
                    'alamat' => $row['alamat'] ?: null,
                    'provinsi' => $row['provinsi'] ?: null,
                    'kota' => $row['kota'] ?: null,
                    'kecamatan' => $row['kecamatan'] ?: null,
                    'desa' => $row['desa'] ?: null,
                    'kodepos' => $row['kodepos'] ?: null,
                ];

                if ($mitra) {
                    // Mitra sudah ada — lengkapi/timpa data kontak & alamat
                    // dari master mitra (dianggap paling update), tapi
                    // NAMA, KAE, dan status sengaja gak disentuh biar gak
                    // ada perubahan identitas/assignment yang gak disengaja.
                    $mitra->update($alamatFields);
                    $updated++;

                    continue;
                }

                if (! $row['nama']) {
                    $skipped[] = $label.': Mitra baru tapi Name kosong, gak bisa dibuat.';

                    continue;
                }

                Mitra::create([
                    'kode_mitra' => $row['kode_mitra'],
                    'nama' => $row['nama'],
                    'status' => 'aktif',
                    ...$alamatFields,
                ]);
                $created++;
            }
        });

        return [
            'ok' => true,
            'errors' => [],
            'jumlah_baris' => count($rows),
            'jumlah_diperbarui' => $updated,
            'jumlah_dibuat' => $created,
            'skipped' => $skipped,
        ];
    }
}
