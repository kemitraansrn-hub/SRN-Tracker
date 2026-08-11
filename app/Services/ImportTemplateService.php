<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Generates blank .xlsx templates with the exact headers the importers
 * expect, so uploaders don't have to guess column names/order.
 */
class ImportTemplateService
{
    public function orderHarian(): Spreadsheet
    {
        $ss = new Spreadsheet();

        $mt = $ss->getActiveSheet();
        $mt->setTitle('Master Transaksi');
        $mt->fromArray([
            'TANGGAL', 'BULAN ORDER', 'ID TRANSAKSI (core)', 'ID TRANSAKSI (Perpack)', 'RESELLER', 'NAME', 'ADDRESS',
            'QTY', 'TOTAL', 'DISKON', 'DISKON CLAIM', 'DISKON RETURN', 'BIAYA PENDAFTARAN', 'DISKON RETURN ID',
            'ONGKIR', 'BIAYA PENANGANAN', 'TOTAL TRANSFER', 'STATUS PEMBAYARAN', 'STATUS',
        ], null, 'A1');
        $mt->fromArray([[
            '2026-08-07', 'August', '90001', '320260807000000', 'REB2025080001', 'Contoh Nama Mitra', 'Contoh Alamat',
            10, 2000000, 0, null, null, 0, null, 50000, null, 2050000, 'Lunas', 'Konfirmasi',
        ]], null, 'A2');
        $this->styleHeader($mt, 19);
        $this->styleExampleRow($mt, 2, 19);

        $md = $ss->createSheet();
        $md->setTitle('Master Detail Transaksi');
        $md->fromArray([
            'TANGGAL ORDER', 'BULAN ORDER', 'ID TRANSAKSI', 'ID TRANSAKSI (Perpack)', 'RESELLER', 'NAME', 'ADDRESS',
            'BRAND', 'PRODUK', 'HARGA', 'QTY', 'TOTAL', 'ID SALESMAN', 'ID CHANNEL',
        ], null, 'A1');
        $md->fromArray([[
            '2026-08-07', 'August', '90001', '320260807000000', 'REB2025080001', 'Contoh Nama Mitra', 'Contoh Alamat',
            'Reglow', 'New Reglow Serum (20ml)', 200000, 10, 2000000, 'B', 'RE',
        ]], null, 'A2');
        $this->styleHeader($md, 14);
        $this->styleExampleRow($md, 2, 14);

        $ss->setActiveSheetIndex(0);

        return $ss;
    }

    public function targetBulanan(): Spreadsheet
    {
        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Target Bulanan');
        $sheet->fromArray(['ID', 'Nama Mitra', 'KAE', 'Segmen', 'Komit (Rp)', 'Target (Rp)', 'Stretch (Rp)', 'Target MOU (Rp)', 'Tier Dipakai'], null, 'A1');
        $sheet->fromArray([[
            'REB2025080001', 'Contoh Nama Mitra', 'DITA', 'REGULER', 25000000, 28000000, 31000000, 26000000, 'Target',
        ]], null, 'A2');
        $this->styleHeader($sheet, 9);
        $this->styleExampleRow($sheet, 2, 9);

        return $ss;
    }

    private function styleHeader($sheet, int $columnCount): void
    {
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnCount);
        $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastCol}1")->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('EBE5EF');
        foreach (range(1, $columnCount) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setWidth(20);
        }
    }

    private function styleExampleRow($sheet, int $row, int $columnCount): void
    {
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnCount);
        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setItalic(true)->getColor()->setRGB('999999');
    }
}
