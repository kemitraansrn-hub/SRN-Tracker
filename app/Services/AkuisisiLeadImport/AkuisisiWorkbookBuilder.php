<?php

namespace App\Services\AkuisisiLeadImport;

use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Membuat file Excel: template import lead dan laporan baris yang gagal diimport. Diporting dari
 * App\Services\LeadImport\WorkbookBuilder di PartnerAcquire CRM, disesuaikan buat 2 kolom tambahan
 * (Hasil Follow Up, Tanggal Next Follow Up -- lihat AkuisisiImportColumns).
 */
class AkuisisiWorkbookBuilder
{
    private const HEADER_FILL = 'DCE6F1';

    private const REQUIRED_FILL = 'FCE4D6';

    public function template(): Spreadsheet
    {
        $book = $this->newBook();
        $data = $book->getActiveSheet()->setTitle(AkuisisiImportColumns::SHEET_NAME);
        $this->writeHeader($data, AkuisisiImportColumns::headers(), requiredMarkers: true);

        // Tanggal & nomor WA sebagai teks agar hari/bulan tidak tertukar dan angka 0 tidak hilang.
        // Kolom: A=Tanggal Masuk, C=Nomor WhatsApp, K=Tanggal Next Follow Up.
        $lastRow = AkuisisiImportColumns::MAX_ROWS + 1;
        foreach (['A', 'C', 'K'] as $column) {
            $data->getStyle("{$column}2:{$column}{$lastRow}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        }

        $options = $book->createSheet()->setTitle('Daftar Pilihan');
        $letters = range('A', 'J');
        $column = 0;
        foreach (AkuisisiImportColumns::all() as $key => [$title, , , $model]) {
            if (! $model) {
                continue;
            }
            $query = $model::orderBy('sort_order');
            if ($key === 'channel') {
                $query->where('is_active', true);
            }
            $names = $query->pluck('name')->all();

            $letter = $letters[$column++];
            $options->setCellValue("{$letter}1", $title)->getStyle("{$letter}1")->getFont()->setBold(true);
            foreach ($names as $i => $name) {
                $options->setCellValue($letter.($i + 2), $name);
            }
            $options->getColumnDimension($letter)->setWidth(28);

            $target = $letters[array_search($key, array_keys(AkuisisiImportColumns::all()), true)];
            $validation = (new DataValidation)
                ->setType(DataValidation::TYPE_LIST)
                ->setAllowBlank(true)
                ->setShowDropDown(true)
                ->setShowErrorMessage(true)
                ->setErrorTitle('Nilai tidak dikenal')
                ->setError("Pilih {$title} dari daftar.")
                ->setFormula1("'Daftar Pilihan'!\${$letter}\$2:\${$letter}\$".(count($names) + 1));
            $data->setDataValidation("{$target}2:{$target}{$lastRow}", $validation);
        }

        $guide = $book->createSheet()->setTitle('Petunjuk');
        $lines = [
            ['Petunjuk import lead', null],
            [null, null],
            ['1. Isi data mulai baris ke-2 di sheet "Data Lead". Jangan ubah judul kolom.', null],
            ['2. Kolom berjudul merah muda wajib diisi. Kolom pilihan memakai dropdown (lihat sheet Daftar Pilihan).', null],
            ['3. Maksimal '.number_format(AkuisisiImportColumns::MAX_ROWS, 0, ',', '.').' baris per file. File .csv dengan judul kolom yang sama juga diterima.', null],
            ['4. Upload di menu Import. Sistem menampilkan pratinjau dulu; baris error & nomor yang sudah ada tidak disimpan.', null],
            ['5. Skor, prioritas, dan jadwal follow-up dihitung otomatis buat lead BARU (kosongkan kolom Hasil Follow Up & Tanggal Next Follow Up).', null],
            ['6. Buat migrasi lead LAMA yang statusnya sudah berjalan: isi kolom Hasil Follow Up dan/atau Tanggal Next Follow Up. Baris itu otomatis ditandai data lama, jadwal/statusnya dipakai apa adanya (tidak dihitung ulang otomatis).', null],
            [null, null],
            ['Kolom', 'Aturan'],
            ['Tanggal Masuk *', 'DD/MM/YYYY, contoh 30/09/2026. Tidak boleh melewati hari ini.'],
            ['Nama Lead *', 'Maksimal 150 karakter.'],
            ['Nomor WhatsApp *', 'Awalan 0, 62, atau +62; disimpan sebagai 62xxx. Nomor yang sudah ada dilewati.'],
            ['Kota', 'Opsional.'],
            ['Channel Sumber *', 'Pilih dari dropdown.'],
            ['Status Bisnis / Respon / Minat Lead / Status Modal', 'Opsional, pilih dari dropdown. Menentukan skor prioritas (kosong = 1 poin).'],
            ['Tahapan Pipeline', 'Opsional; kosong = Profiling.'],
            ['Hasil Follow Up', 'Opsional, pilih dari dropdown. Isi HANYA untuk migrasi lead lama yang sudah pernah di-follow-up -- mengisi ini menandai baris sebagai data lama (legacy).'],
            ['Tanggal Next Follow Up', 'Opsional, DD/MM/YYYY. Isi HANYA untuk migrasi lead lama -- boleh tanggal lampau (sudah lewat) maupun akan datang.'],
            ['Catatan', 'Opsional, maksimal 2000 karakter. Tampil sebagai catatan terakhir sampai follow-up pertama.'],
            [null, null],
            ['Contoh pengisian lead BARU (jangan disalin ke sheet Data Lead):', null],
        ];
        foreach ($lines as $i => [$a, $b]) {
            $guide->setCellValue('A'.($i + 1), $a);
            $guide->setCellValue('B'.($i + 1), $b);
        }
        $exampleRow = count($lines) + 1;
        $guide->fromArray(AkuisisiImportColumns::headers(), null, "A{$exampleRow}");
        $guide->fromArray(['30/09/2026', 'Toko Skincare Glowing', '081234567890', 'Bogor', 'Ads WA', 'Sudah berbisnis kecantikan', 'Tanya Pricelist', 'Siap modal', 'Profiling', null, null, 'Tanya paket 5 juta'], null, 'A'.($exampleRow + 1));
        $legacyLabelRow = $exampleRow + 2;
        $guide->setCellValue('A'.$legacyLabelRow, 'Contoh pengisian migrasi lead LAMA:');
        $guide->getStyle('A'.$legacyLabelRow)->getFont()->setBold(true);
        $guide->fromArray(['01/06/2026', 'Toko Herbal Sentosa', '081298765432', 'Bandung', 'Referral', 'Sudah berbisnis kecantikan', 'Tanya Pricelist', 'Kurang dari 5 juta', 'Skema Kemitraan', 'Postponed', '15/11/2026', 'Masih pikir-pikir, follow up lagi pertengahan November'], null, 'A'.($legacyLabelRow + 1));
        foreach ([1, 10, $exampleRow, $legacyLabelRow] as $row) {
            $guide->getStyle("A{$row}:L{$row}")->getFont()->setBold(true);
        }
        $guide->getColumnDimension('A')->setWidth(48);
        $guide->getColumnDimension('B')->setWidth(90);

        $book->setActiveSheetIndex(0);

        return $book;
    }

    /**
     * Baris yang gagal/dilewati beserta alasannya, dengan kolom yang sama seperti template,
     * sehingga bisa diperbaiki lalu diupload ulang.
     */
    public function errorReport(array $problems): Spreadsheet
    {
        $book = $this->newBook();
        $sheet = $book->getActiveSheet()->setTitle(AkuisisiImportColumns::SHEET_NAME);
        $this->writeHeader($sheet, [...AkuisisiImportColumns::headers(), 'Baris di File', 'Alasan']);

        foreach ($problems as $i => $problem) {
            $row = $i + 2;
            $values = [...array_values(array_map(fn ($title) => $problem['values'][$title] ?? null, AkuisisiImportColumns::headers())), $problem['row'], implode('; ', $problem['messages'])];
            foreach ($values as $col => $value) {
                // Semua sebagai teks agar nomor & tanggal tampil persis seperti di file asal.
                $sheet->setCellValueExplicit([$col + 1, $row], $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
        }

        return $book;
    }

    private function newBook(): Spreadsheet
    {
        $book = new Spreadsheet;
        $book->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

        return $book;
    }

    private function writeHeader(Worksheet $sheet, array $headers, bool $requiredMarkers = false): void
    {
        $sheet->fromArray($headers, null, 'A1');
        $required = array_values(array_map(fn (array $column) => $column[1], AkuisisiImportColumns::all()));

        foreach (array_values($headers) as $i => $header) {
            $cell = [$i + 1, 1];
            $isRequired = $requiredMarkers && ($required[$i] ?? false);
            $sheet->getStyle($cell)->getFont()->setBold(true);
            $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($isRequired ? self::REQUIRED_FILL : self::HEADER_FILL);
            $sheet->getColumnDimensionByColumn($i + 1)->setWidth(max(14, mb_strlen($header) + 6));
        }
        $sheet->freezePane('A2');
    }
}
