<?php

namespace App\Services;

use App\Models\Mitra;
use App\Models\TrackingPerformance;
use App\Models\User;
use App\Services\Concerns\ParsesSpreadsheetHeaders;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Membaca file Tracking Performance (sheet "Pesanan Dibuat", satu baris per
 * mitra per periode/minggu) dan menyimpannya per mitra. Baris dicocokkan ke
 * mitra lewat Kode Mitra; kalau kode kosong, lewat Nama Mitra yang persis
 * sama (harus tepat satu mitra). Upload ulang periode yang sama untuk mitra
 * yang sama memperbarui baris lama (bukan dobel). Angka di file bisa berupa
 * teks format Indonesia ("869.250", "289.750,00") — dibaca apa adanya.
 */
class TrackingPerformanceImportService
{
    use ParsesSpreadsheetHeaders;

    public const SHEET_NAME = 'Pesanan Dibuat';

    private const HEADER_ALIASES = [
        'kode_mitra' => ['KODE MITRA', 'RESELLER ID', 'KODE RESELLER'],
        'nama' => ['NAMA MITRA', 'NAMA', 'NAME'],
        'week' => ['WEEK', 'MINGGU'],
        'kuartal' => ['KUARTAL'],
        'tanggal' => ['TANGGAL', 'PERIODE'],
        'gmv' => ['TOTAL PENJUALAN (IDR)', 'TOTAL PENJUALAN', 'GMV'],
        'total_pesanan' => ['TOTAL PESANAN'],
        'produk_diklik' => ['PRODUK DIKLIK'],
        'total_pengunjung' => ['TOTAL PENGUNJUNG', 'TRAFFIC'],
        'ads_spend' => ['ADS SPEND (IDR)', 'ADS SPEND', 'BIAYA IKLAN'],
    ];

    private const NUMERIC = ['gmv', 'total_pesanan', 'produk_diklik', 'total_pengunjung', 'ads_spend'];

    private const LABEL_ANGKA = [
        'gmv' => 'Total Penjualan (IDR)',
        'total_pesanan' => 'Total Pesanan',
        'produk_diklik' => 'Produk Diklik',
        'total_pengunjung' => 'Total Pengunjung',
        'ads_spend' => 'Ads Spend (IDR)',
    ];

    /**
     * @return array{ok: bool, errors: array<int, string>, jumlah_baris?: int, jumlah_dibuat?: int, jumlah_diperbarui?: int, skipped?: array<int, string>}
     */
    public function import(UploadedFile $file, User $user): array
    {
        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
        } catch (\Throwable $e) {
            return ['ok' => false, 'errors' => ['File tidak bisa dibaca: '.$e->getMessage()]];
        }

        $sheet = $spreadsheet->sheetNameExists(self::SHEET_NAME)
            ? $spreadsheet->getSheetByName(self::SHEET_NAME)
            : $spreadsheet->getSheet(0);

        $columnMap = $this->resolveAliases($this->readHeaderMap($sheet), self::HEADER_ALIASES);

        foreach (['tanggal', 'gmv', 'total_pesanan', 'produk_diklik', 'total_pengunjung'] as $wajib) {
            if (! isset($columnMap[$wajib])) {
                return ['ok' => false, 'errors' => ['Format file tidak dikenali. Sheet "'.self::SHEET_NAME.'" harus punya kolom Tanggal, Total Penjualan (IDR), Total Pesanan, Produk Diklik, dan Total Pengunjung.']];
            }
        }
        if (! isset($columnMap['kode_mitra']) && ! isset($columnMap['nama'])) {
            return ['ok' => false, 'errors' => ['Format file tidak dikenali. Harus ada kolom Kode Mitra atau Nama Mitra.']];
        }

        $rows = [];
        $highestRow = $sheet->getHighestDataRow();
        for ($r = 2; $r <= $highestRow; $r++) {
            $row = ['_baris' => $r];
            foreach ($columnMap as $key => $colIndex) {
                $cell = $sheet->getCell(Coordinate::stringFromColumnIndex($colIndex).$r);

                try {
                    $raw = $cell->isFormula() ? $cell->getCalculatedValue() : $cell->getValue();
                } catch (\Throwable) {
                    $raw = $cell->getValue();
                }

                $row[$key] = $raw;
                if ($key === 'tanggal') {
                    $row['_tanggal_is_date'] = $raw !== null && is_numeric($raw) && ExcelDate::isDateTime($cell);
                }
            }

            // Baris tanpa kode, nama, dan tanggal dianggap kosong/catatan di
            // luar tabel data (mis. keterangan rumus di bawah tabel) — diabaikan.
            $kosong = trim((string) ($row['kode_mitra'] ?? '')) === ''
                && trim((string) ($row['nama'] ?? '')) === ''
                && trim((string) ($row['tanggal'] ?? '')) === '';
            if (! $kosong) {
                $rows[] = $row;
            }
        }

        if (empty($rows)) {
            return ['ok' => false, 'errors' => ['Sheet tidak berisi data.']];
        }

        $mitraAll = Mitra::get(['id', 'kode_mitra', 'nama', 'kae_code']);
        $byKode = $mitraAll->keyBy(fn ($m) => mb_strtolower(trim($m->kode_mitra)));
        $byNama = $mitraAll->groupBy(fn ($m) => mb_strtolower(trim($m->nama)));

        $skipped = [];
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($rows, $user, $byKode, $byNama, &$skipped, &$created, &$updated) {
            foreach ($rows as $row) {
                $kode = trim((string) ($row['kode_mitra'] ?? ''));
                $nama = trim((string) ($row['nama'] ?? ''));
                $label = 'Baris '.$row['_baris'].' ('.($kode !== '' ? $kode : ($nama !== '' ? $nama : 'tanpa mitra')).')';

                if ($kode !== '') {
                    $mitra = $byKode->get(mb_strtolower($kode));
                    if (! $mitra) {
                        $skipped[] = $label.': Kode Mitra tidak ditemukan di Data Mitra.';

                        continue;
                    }
                } elseif ($nama !== '') {
                    $cocok = $byNama->get(mb_strtolower($nama), collect());
                    if ($cocok->isEmpty()) {
                        $skipped[] = $label.': Nama Mitra tidak ditemukan di Data Mitra (isi kolom Kode Mitra biar akurat).';

                        continue;
                    }
                    if ($cocok->count() > 1) {
                        $skipped[] = $label.': Ada '.$cocok->count().' mitra dengan nama yang sama — isi kolom Kode Mitra.';

                        continue;
                    }
                    $mitra = $cocok->first();
                } else {
                    $skipped[] = $label.': Kode Mitra dan Nama Mitra kosong.';

                    continue;
                }

                if ($user->role === 'kae' && $mitra->kae_code !== $user->kae_code) {
                    $skipped[] = $label.': bukan mitra Anda.';

                    continue;
                }

                $periode = $this->parsePeriode($row['tanggal'] ?? null, (bool) ($row['_tanggal_is_date'] ?? false));
                if (! $periode) {
                    $skipped[] = $label.': Tanggal tidak valid (contoh: 31-08-2026-06-09-2026 atau 31-08-2026).';

                    continue;
                }

                $angka = [];
                $angkaGagal = null;
                foreach (self::NUMERIC as $key) {
                    $nilai = $this->parseNumber($row[$key] ?? null);
                    if ($nilai === null && $key !== 'ads_spend') {
                        $angkaGagal = $key;
                        break;
                    }
                    if ($nilai !== null && $nilai < 0) {
                        $angkaGagal = $key;
                        break;
                    }
                    $angka[$key] = $nilai ?? 0.0;
                }
                if ($angkaGagal) {
                    $skipped[] = $label.': kolom '.self::LABEL_ANGKA[$angkaGagal].' bukan angka yang valid (harus angka, tidak negatif).';

                    continue;
                }

                $week = trim((string) ($row['week'] ?? ''));
                $kuartal = trim((string) ($row['kuartal'] ?? ''));

                $record = TrackingPerformance::updateOrCreate(
                    ['mitra_id' => $mitra->id, 'tanggal_mulai' => $periode[0], 'tanggal_selesai' => $periode[1]],
                    [
                        'week' => $week !== '' ? $week : null,
                        'kuartal' => $kuartal !== '' ? $kuartal : null,
                        'gmv' => $angka['gmv'],
                        'total_pesanan' => (int) round($angka['total_pesanan']),
                        'produk_diklik' => (int) round($angka['produk_diklik']),
                        'total_pengunjung' => (int) round($angka['total_pengunjung']),
                        'ads_spend' => $angka['ads_spend'],
                        'uploaded_by' => $user->id,
                    ]
                );

                $record->wasRecentlyCreated ? $created++ : $updated++;
            }
        });

        return [
            'ok' => true,
            'errors' => [],
            'jumlah_baris' => count($rows),
            'jumlah_dibuat' => $created,
            'jumlah_diperbarui' => $updated,
            'skipped' => $skipped,
        ];
    }

    /** "869.250" / "289.750,00" / "3,37%" / angka asli -> float; null kalau bukan angka. */
    private function parseNumber(mixed $raw): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_int($raw) || is_float($raw)) {
            return (float) $raw;
        }

        $s = trim(str_ireplace(['Rp', ' ', '%'], '', (string) $raw));
        if ($s === '' || $s === '-') {
            return null;
        }

        if (str_contains($s, ',')) {
            $s = str_replace(',', '.', str_replace('.', '', $s));
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $s)) {
            $s = str_replace('.', '', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    /**
     * "31-08-2026-06-09-2026" -> [2026-08-31, 2026-09-06]; "31-08-2026" atau
     * tanggal Excel asli -> satu hari. Format hari-bulan-tahun.
     *
     * @return array{0: string, 1: string}|null
     */
    private function parsePeriode(mixed $raw, bool $isExcelDate): ?array
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if ($isExcelDate) {
            $d = Carbon::instance(ExcelDate::excelToDateTimeObject((float) $raw))->toDateString();

            return [$d, $d];
        }

        if (! preg_match_all('/(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})/', (string) $raw, $m, PREG_SET_ORDER) || count($m) > 2) {
            return null;
        }

        $tanggal = [];
        foreach ($m as $x) {
            if (! checkdate((int) $x[2], (int) $x[1], (int) $x[3])) {
                return null;
            }
            $tanggal[] = sprintf('%04d-%02d-%02d', $x[3], $x[2], $x[1]);
        }

        $mulai = $tanggal[0];
        $selesai = $tanggal[1] ?? $tanggal[0];

        return $mulai <= $selesai ? [$mulai, $selesai] : null;
    }
}
