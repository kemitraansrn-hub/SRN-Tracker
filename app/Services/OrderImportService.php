<?php

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\Mitra;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Produk;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Parses the daily "Master Transaksi" + "Master Detail Transaksi" export and
 * turns it into orders + order_items, with replace-by-date protection.
 */
class OrderImportService
{
    private const HEADER_ALIASES_TRANSAKSI = [
        'tanggal' => ['TANGGAL'],
        'id_transaksi' => ['ID TRANSAKSI (CORE)', 'ID TRANSAKSI'],
        'id_transaksi_perpack' => ['ID TRANSAKSI (PERPACK)'],
        'reseller' => ['RESELLER'],
        'name' => ['NAME'],
        'address' => ['ADDRESS'],
        'total' => ['TOTAL'],
        'diskon' => ['DISKON'],
        'diskon_claim' => ['DISKON CLAIM'],
        'diskon_return' => ['DISKON RETURN'],
        'biaya_pendaftaran' => ['BIAYA PENDAFTARAN'],
        'ongkir' => ['ONGKIR'],
        'biaya_penanganan' => ['BIAYA PENANGANAN'],
        'total_transfer' => ['TOTAL TRANSFER'],
        'status_pembayaran' => ['STATUS PEMBAYARAN'],
        'status' => ['STATUS'],
    ];

    private const HEADER_ALIASES_DETAIL = [
        'tanggal' => ['TANGGAL ORDER', 'TANGGAL'],
        'id_transaksi' => ['ID TRANSAKSI'],
        'reseller' => ['RESELLER'],
        'name' => ['NAME'],
        'address' => ['ADDRESS'],
        'brand' => ['BRAND'],
        'produk' => ['PRODUK'],
        'harga' => ['HARGA'],
        'qty' => ['QTY'],
        'total' => ['TOTAL'],
        'id_salesman' => ['ID SALESMAN'],
        'id_channel' => ['ID CHANNEL'],
    ];

    /**
     * Parse the uploaded file and return a structured, validated payload
     * (nothing is written to the database yet).
     *
     * @return array{ok: bool, errors: array<int, string>, tanggal_data?: string, jumlah_baris?: int, headers?: array, orders?: array, items?: array}
     */
    public function parse(UploadedFile $file): array
    {
        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
        } catch (\Throwable $e) {
            return ['ok' => false, 'errors' => ['File tidak bisa dibaca: '.$e->getMessage()]];
        }

        $masterSheet = null;
        $detailSheet = null;

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $headerMap = $this->readHeaderMap($sheet);

            if ($this->matchesDetail($headerMap)) {
                $detailSheet = ['sheet' => $sheet, 'map' => $this->resolveAliases($headerMap, self::HEADER_ALIASES_DETAIL)];
            } elseif ($this->matchesTransaksi($headerMap)) {
                $masterSheet = ['sheet' => $sheet, 'map' => $this->resolveAliases($headerMap, self::HEADER_ALIASES_TRANSAKSI)];
            }
        }

        if (! $masterSheet || ! $detailSheet) {
            return ['ok' => false, 'errors' => [
                'Format file tidak dikenali. File harus punya sheet "Master Transaksi" (kolom RESELLER, TOTAL, STATUS PEMBAYARAN) dan sheet "Master Detail Transaksi" (kolom BRAND, PRODUK, ID SALESMAN).',
            ]];
        }

        $orders = $this->extractRows($masterSheet['sheet'], $masterSheet['map']);
        $items = $this->extractRows($detailSheet['sheet'], $detailSheet['map']);

        if (empty($orders)) {
            return ['ok' => false, 'errors' => ['Sheet Master Transaksi tidak berisi data.']];
        }

        $dates = collect($orders)->pluck('tanggal')->filter()->unique();

        if ($dates->count() > 1) {
            return ['ok' => false, 'errors' => [
                'File ini berisi lebih dari 1 tanggal order ('.$dates->sort()->implode(', ').'). Untuk import harian, upload satu file per tanggal.',
            ]];
        }

        if ($dates->isEmpty()) {
            return ['ok' => false, 'errors' => ['Tidak bisa membaca kolom TANGGAL pada Master Transaksi.']];
        }

        return [
            'ok' => true,
            'errors' => [],
            'tanggal_data' => $dates->first(),
            'jumlah_baris' => count($orders) + count($items),
            'orders' => $orders,
            'items' => $items,
        ];
    }

    /**
     * Persist a previously-parsed payload. If $replace is true, existing
     * orders for the same tanggal_data are removed first.
     */
    public function commit(array $parsed, User $user, string $namaFile, bool $replace): ImportBatch
    {
        return DB::transaction(function () use ($parsed, $user, $namaFile, $replace) {
            $tanggalData = $parsed['tanggal_data'];

            if ($replace) {
                Order::whereDate('tanggal_order', $tanggalData)->get()->each->delete();
            }

            $batch = ImportBatch::create([
                'jenis' => 'order_harian',
                'tanggal_data' => $tanggalData,
                'nama_file' => $namaFile,
                'uploaded_by' => $user->id,
                'jumlah_baris' => $parsed['jumlah_baris'],
                'status' => $replace ? 'ditimpa' : 'berhasil',
            ]);

            $mitraByKode = [];
            $resolveMitra = function (string $kode, string $nama, ?string $alamat, ?string $kaeCode) use (&$mitraByKode) {
                $mitra = $mitraByKode[$kode] ?? Mitra::firstOrNew(['kode_mitra' => $kode]);

                $mitra->nama = $nama ?: ($mitra->nama ?? $kode);
                if ($alamat) {
                    $mitra->alamat = $alamat;
                }
                if ($kaeCode) {
                    $mitra->kae_code = $kaeCode;
                }
                if (! $mitra->exists) {
                    $mitra->status = 'aktif';
                }
                if ($mitra->isDirty()) {
                    $mitra->save();
                }

                return $mitraByKode[$kode] = $mitra;
            };

            $orderIdByNoOrder = [];

            foreach ($parsed['orders'] as $row) {
                if (! $row['reseller'] || ! $row['id_transaksi']) {
                    continue;
                }

                $mitra = $resolveMitra($row['reseller'], $row['name'] ?? $row['reseller'], $row['address'] ?? null, null);

                $order = Order::create([
                    'import_batch_id' => $batch->id,
                    'no_order' => $row['id_transaksi'],
                    'no_order_perpack' => $row['id_transaksi_perpack'] ?? null,
                    'tanggal_order' => $row['tanggal'],
                    'mitra_id' => $mitra->id,
                    'total_transaksi' => $row['total'] ?? 0,
                    'diskon' => $row['diskon'] ?? 0,
                    'diskon_claim' => $row['diskon_claim'] ?? null,
                    'diskon_return' => $row['diskon_return'] ?? null,
                    'biaya_pendaftaran' => $row['biaya_pendaftaran'] ?? 0,
                    'ongkir' => $row['ongkir'] ?? 0,
                    'biaya_penanganan' => $row['biaya_penanganan'] ?? null,
                    'total_transfer' => $row['total_transfer'] ?? null,
                    'status_pembayaran' => $row['status_pembayaran'] ?? null,
                    'status' => $row['status'] ?? null,
                ]);

                $orderIdByNoOrder[$order->no_order] = $order->id;
            }

            $produkByKey = [];
            $resolveProduk = function (string $brand, string $nama) use (&$produkByKey) {
                $key = mb_strtolower($brand.'|'.$nama);
                if (isset($produkByKey[$key])) {
                    return $produkByKey[$key];
                }

                $produk = Produk::firstOrCreate(
                    ['brand' => $brand, 'nama' => $nama],
                    ['status' => 'aktif']
                );

                return $produkByKey[$key] = $produk;
            };

            foreach ($parsed['items'] as $row) {
                $orderId = $orderIdByNoOrder[$row['id_transaksi'] ?? ''] ?? null;

                if (! $orderId || ! $row['produk']) {
                    continue;
                }

                $mitra = $resolveMitra(
                    $row['reseller'],
                    $row['name'] ?? $row['reseller'],
                    $row['address'] ?? null,
                    $row['id_salesman'] ?? null
                );

                $produk = $this->isRealProductName($row['produk'])
                    ? $resolveProduk($row['brand'] ?? 'Lainnya', $row['produk'])
                    : null;

                OrderItem::create([
                    'order_id' => $orderId,
                    'produk_id' => $produk?->id,
                    'brand' => $row['brand'] ?? null,
                    'nama_produk_raw' => $row['produk'],
                    'qty' => $row['qty'] ?? 0,
                    'harga' => $row['harga'] ?? null,
                    'subtotal' => $row['total'] ?? 0,
                ]);

                if (isset($row['id_salesman']) && $row['id_salesman']) {
                    Order::where('id', $orderId)->update(['kae_code' => $row['id_salesman']]);
                }
                if (isset($row['id_channel']) && $row['id_channel']) {
                    Order::where('id', $orderId)->update(['id_channel' => $row['id_channel']]);
                }
            }

            return $batch;
        });
    }

    /**
     * A handful of raw PRODUK values are still un-decoded short codes
     * (PCH, AG, MC, FF, SSN, ...). Keep them on the order item as raw text
     * but don't create bogus "product" master records for them yet.
     */
    private function isRealProductName(string $value): bool
    {
        return mb_strlen(trim($value)) > 4;
    }

    private function readHeaderMap(Worksheet $sheet): array
    {
        $map = [];
        $highestColumn = $sheet->getHighestDataColumn(1);
        $columnIndex = 1;

        foreach ($sheet->getRowIterator(1, 1) as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $value = trim((string) $cell->getValue());
                if ($value !== '') {
                    $map[strtoupper($value)] = $columnIndex;
                }
                $columnIndex++;
            }
        }

        return $map;
    }

    private function matchesDetail(array $headerMap): bool
    {
        return isset($headerMap['BRAND'], $headerMap['PRODUK'], $headerMap['ID SALESMAN']);
    }

    private function matchesTransaksi(array $headerMap): bool
    {
        return isset($headerMap['RESELLER'], $headerMap['TOTAL'], $headerMap['STATUS PEMBAYARAN'])
            && ! isset($headerMap['BRAND'], $headerMap['PRODUK']);
    }

    private function resolveAliases(array $headerMap, array $aliasGroups): array
    {
        $resolved = [];

        foreach ($aliasGroups as $key => $aliases) {
            foreach ($aliases as $alias) {
                if (isset($headerMap[$alias])) {
                    $resolved[$key] = $headerMap[$alias];
                    break;
                }
            }
        }

        return $resolved;
    }

    private function extractRows(Worksheet $sheet, array $columnMap): array
    {
        $rows = [];
        $highestRow = $sheet->getHighestDataRow();

        for ($r = 2; $r <= $highestRow; $r++) {
            $row = [];
            $isEmpty = true;

            foreach ($columnMap as $key => $colIndex) {
                $coordinate = Coordinate::stringFromColumnIndex($colIndex).$r;
                $cell = $sheet->getCell($coordinate);
                $value = $this->readCellValue($cell, $key);

                if ($value !== null && $value !== '') {
                    $isEmpty = false;
                }

                $row[$key] = $value;
            }

            if (! $isEmpty) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    private function readCellValue(Cell $cell, string $key): mixed
    {
        $raw = $cell->getValue();

        if ($raw === null) {
            return $key === 'tanggal' ? null : null;
        }

        if ($key === 'tanggal') {
            // Stored as a plain date string (not a Carbon instance) because
            // this row array gets cached/serialized between the "conflict
            // detected" and "confirm replace" requests.
            return $this->resolveDate($cell)?->toDateString();
        }

        if (in_array($key, ['total', 'diskon', 'diskon_claim', 'diskon_return', 'biaya_pendaftaran', 'ongkir', 'biaya_penanganan', 'total_transfer', 'harga', 'qty'], true)) {
            if ($raw === '' || $raw === null) {
                return null;
            }

            return is_numeric($raw) ? (float) $raw : null;
        }

        return trim((string) $raw);
    }

    private function resolveDate(Cell $cell): ?Carbon
    {
        $value = $cell->getValue();

        if ($value === null || $value === '') {
            return null;
        }

        if (ExcelDate::isDateTime($cell)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject($value));
        }

        if (is_numeric($value) && $value > 25000) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value));
        }

        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y', 'm/d/Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, trim((string) $value))->startOfDay();
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
