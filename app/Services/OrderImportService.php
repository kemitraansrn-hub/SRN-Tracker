<?php

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\Mitra;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Produk;
use App\Models\User;
use App\Services\Concerns\ParsesSpreadsheetHeaders;
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
    use ParsesSpreadsheetHeaders;

    private const HEADER_ALIASES_TRANSAKSI = [
        'tanggal' => ['TANGGAL', 'TANGGAL ORDER'],
        'bulan_order' => ['BULAN ORDER', 'BULAN'],
        'id_transaksi' => ['ID TRANSAKSI (CORE)', 'ID TRANSAKSI'],
        'id_transaksi_perpack' => ['ID TRANSAKSI (PERPACK)'],
        'reseller' => ['RESELLER'],
        'name' => ['NAME'],
        'address' => ['ADDRESS'],
        'qty' => ['QTY'],
        'total' => ['TOTAL'],
        'diskon' => ['DISKON'],
        'diskon_claim' => ['DISKON CLAIM'],
        'diskon_return' => ['DISKON RETURN'],
        'biaya_pendaftaran' => ['BIAYA PENDAFTARAN'],
        'diskon_return_id' => ['DISKON RETURN ID'],
        'ongkir' => ['ONGKIR'],
        'biaya_penanganan' => ['BIAYA PENANGANAN'],
        'total_transfer' => ['TOTAL TRANSFER'],
        'status_pembayaran' => ['STATUS PEMBAYARAN'],
        'status' => ['STATUS'],
    ];

    private const HEADER_ALIASES_DETAIL = [
        'tanggal' => ['TANGGAL ORDER', 'TANGGAL'],
        'id_transaksi' => ['ID TRANSAKSI'],
        'id_transaksi_perpack' => ['ID TRANSAKSI (PERPACK)'],
        'reseller' => ['RESELLER'],
        'name' => ['NAME'],
        'address' => ['ADDRESS'],
        'brand' => ['BRAND'],
        'sku' => ['SKU'],
        'produk' => ['NAMA PRODUK', 'PRODUK'],
        'harga' => ['HARGA'],
        'qty' => ['QTY'],
        'total' => ['TOTAL'],
        'id_salesman' => ['ID SALESMAN'],
        'id_channel' => ['ID CHANNEL'],
    ];

    /**
     * Short codes some source files use instead of SKU/full product name —
     * mapped straight to the real kode_sku (per "MASTER PRODUK SRN.xlsx",
     * confirmed with user 2026-08-26) so resolveProduk() matches the
     * existing catalog row by SKU instead of spawning a fresh "MC"/"FF"/...
     * duplicate by name every time this code shows up in a future import.
     * Confirmed with the user 2026-08-19: PCH is deliberately excluded
     * (freebie pouch, no real SKU), the rest are real Reglow products.
     */
    private const PRODUK_ALIASES = [
        'Reglow' => [
            'SM' => 'RG-UGSM',
            'TN' => 'RG-SPB-150',
            'MC' => 'RG-CB-30',
            'FF' => 'RG-AH-100',
            'SSN' => 'RG-SL-30',
            'AG' => 'RG-DA-15',
        ],
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
                'Format file tidak dikenali. File harus punya sheet "Master Transaksi" (kolom RESELLER, TOTAL, STATUS PEMBAYARAN) dan sheet "Master Detail Transaksi" (kolom BRAND, NAMA PRODUK, ID SALESMAN).',
            ]];
        }

        $orders = $this->extractRows($masterSheet['sheet'], $masterSheet['map']);
        $items = $this->extractRows($detailSheet['sheet'], $detailSheet['map']);

        if (empty($orders)) {
            return ['ok' => false, 'errors' => ['Sheet Master Transaksi tidak berisi data.']];
        }

        if ($alignmentError = $this->detectColumnShift($orders)) {
            return ['ok' => false, 'errors' => [$alignmentError]];
        }

        if ($bulanError = $this->detectBulanMismatch($orders)) {
            return ['ok' => false, 'errors' => [$bulanError]];
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

        if ($duplicateError = $this->detectDuplicateOrderIds($orders, $dates->first(), $dates->first())) {
            return ['ok' => false, 'errors' => [$duplicateError]];
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
                // Bulk delete: order_items cascade at the DB level (FK
                // ON DELETE CASCADE), so there's no need to load every
                // Order model and delete them one by one — that pattern is
                // what caused a "Lock wait timeout" on large re-imports.
                Order::whereDate('tanggal_order', $tanggalData)->delete();
            }

            $batch = ImportBatch::create([
                'jenis' => 'order_harian',
                'tanggal_data' => $tanggalData,
                'nama_file' => $namaFile,
                'uploaded_by' => $user->id,
                'jumlah_baris' => $parsed['jumlah_baris'],
                'status' => $replace ? 'ditimpa' : 'berhasil',
            ]);

            $this->persistRows($parsed, $batch);

            return $batch;
        });
    }

    /**
     * Parse the uploaded file for the bulk/historical import path: unlike
     * parse(), this accepts a file spanning MANY dates at once (e.g. a full
     * YTD export) instead of enforcing one date per file.
     *
     * @return array{ok: bool, errors: array<int, string>, tanggal_mulai?: string, tanggal_selesai?: string, jumlah_baris?: int, orders?: array, items?: array}
     */
    public function parseHistoris(UploadedFile $file): array
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
                'Format file tidak dikenali. File harus punya sheet "Master Transaksi" (kolom RESELLER, TOTAL, STATUS PEMBAYARAN) dan sheet "Master Detail Transaksi" (kolom BRAND, NAMA PRODUK, ID SALESMAN).',
            ]];
        }

        $orders = $this->extractRows($masterSheet['sheet'], $masterSheet['map']);
        $items = $this->extractRows($detailSheet['sheet'], $detailSheet['map']);

        if (empty($orders)) {
            return ['ok' => false, 'errors' => ['Sheet Master Transaksi tidak berisi data.']];
        }

        if ($alignmentError = $this->detectColumnShift($orders)) {
            return ['ok' => false, 'errors' => [$alignmentError]];
        }

        if ($bulanError = $this->detectBulanMismatch($orders)) {
            return ['ok' => false, 'errors' => [$bulanError]];
        }

        $dates = collect($orders)->pluck('tanggal')->filter()->unique()->sort()->values();

        if ($dates->isEmpty()) {
            return ['ok' => false, 'errors' => ['Tidak bisa membaca kolom TANGGAL pada Master Transaksi.']];
        }

        if ($duplicateError = $this->detectDuplicateOrderIds($orders, $dates->first(), $dates->last())) {
            return ['ok' => false, 'errors' => [$duplicateError]];
        }

        return [
            'ok' => true,
            'errors' => [],
            'tanggal_mulai' => $dates->first(),
            'tanggal_selesai' => $dates->last(),
            'jumlah_tanggal' => $dates->count(),
            'jumlah_baris' => count($orders) + count($items),
            'orders' => $orders,
            'items' => $items,
        ];
    }

    /**
     * Persist a previously-parsed historical payload. If $replace is true,
     * existing orders anywhere inside the file's date range are removed first.
     */
    public function commitHistoris(array $parsed, User $user, string $namaFile, bool $replace): ImportBatch
    {
        set_time_limit(0);

        return DB::transaction(function () use ($parsed, $user, $namaFile, $replace) {
            $mulai = $parsed['tanggal_mulai'];
            $selesai = $parsed['tanggal_selesai'];

            if ($replace) {
                // Bulk delete: order_items cascade at the DB level (FK
                // ON DELETE CASCADE), so there's no need to load every
                // Order model and delete them one by one — that pattern is
                // what caused a "Lock wait timeout" on large re-imports.
                Order::whereBetween('tanggal_order', [$mulai, $selesai])->delete();
            }

            $batch = ImportBatch::create([
                'jenis' => 'order_historis',
                'nama_file' => $namaFile,
                'uploaded_by' => $user->id,
                'jumlah_baris' => $parsed['jumlah_baris'],
                'status' => $replace ? 'ditimpa' : 'berhasil',
                'catatan' => 'Periode: '.Carbon::parse($mulai)->format('d/m/Y').' s/d '.Carbon::parse($selesai)->format('d/m/Y'),
            ]);

            $this->persistRows($parsed, $batch);

            return $batch;
        });
    }

    /**
     * Shared row-insertion logic used by both the daily and historical
     * import commit paths: resolves/creates mitra + produk master records
     * and inserts the orders + order_items rows for one parsed payload.
     */
    private function persistRows(array $parsed, ImportBatch $batch): void
    {
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
            $joinKey = ($row['id_transaksi'] ?? null) ?: ($row['id_transaksi_perpack'] ?? null);

            if (! $row['reseller'] || ! $joinKey || ! $row['tanggal']) {
                continue;
            }

            $mitra = $resolveMitra($row['reseller'], $row['name'] ?? $row['reseller'], $row['address'] ?? null, null);

            $order = Order::create([
                'import_batch_id' => $batch->id,
                'no_order' => $joinKey,
                'no_order_perpack' => $row['id_transaksi_perpack'] ?? null,
                'tanggal_order' => $row['tanggal'],
                'mitra_id' => $mitra->id,
                'total_transaksi' => $row['total'] ?? 0,
                'qty' => $row['qty'] ?? null,
                'diskon' => $row['diskon'] ?? 0,
                'diskon_claim' => $row['diskon_claim'] ?? null,
                'diskon_return' => $row['diskon_return'] ?? null,
                'diskon_return_id' => $row['diskon_return_id'] ?? null,
                'biaya_pendaftaran' => $row['biaya_pendaftaran'] ?? 0,
                'ongkir' => $row['ongkir'] ?? 0,
                'biaya_penanganan' => $row['biaya_penanganan'] ?? null,
                'total_transfer' => $row['total_transfer'] ?? null,
                'status_pembayaran' => $row['status_pembayaran'] ?? null,
                'status' => $row['status'] ?? null,
            ]);

            $orderIdByNoOrder[$joinKey] = $order->id;
        }

        $produkByKey = [];
        $resolveProduk = function (string $brand, string $nama, ?string $sku) use (&$produkByKey) {
            $sku ??= self::PRODUK_ALIASES[$brand][$nama] ?? null;
            $cacheKey = $sku ? 'sku:'.mb_strtolower($sku) : mb_strtolower($brand.'|'.$nama);
            if (isset($produkByKey[$cacheKey])) {
                return $produkByKey[$cacheKey];
            }

            $produk = $sku ? Produk::where('kode_sku', $sku)->first() : null;
            $produk ??= Produk::where('brand', $brand)->where('nama', $nama)->first();

            if ($produk) {
                if ($sku && ! $produk->kode_sku) {
                    $produk->kode_sku = $sku;
                    $produk->save();
                }
            } else {
                $produk = Produk::create(['brand' => $brand, 'nama' => $nama, 'kode_sku' => $sku, 'status' => 'aktif', 'auto_created' => true]);
            }

            return $produkByKey[$cacheKey] = $produk;
        };

        foreach ($parsed['items'] as $row) {
            $joinKey = ($row['id_transaksi'] ?? null) ?: ($row['id_transaksi_perpack'] ?? null);
            $orderId = $orderIdByNoOrder[$joinKey ?? ''] ?? null;

            if (! $orderId || ! $row['produk']) {
                continue;
            }

            $mitra = $resolveMitra(
                $row['reseller'],
                $row['name'] ?? $row['reseller'],
                $row['address'] ?? null,
                $row['id_salesman'] ?? null
            );

            $sku = $row['sku'] ?? null;
            $produk = $this->isRealProductName($row['produk'], $row['harga'] ?? null, $row['total'] ?? null)
                ? $resolveProduk($row['brand'] ?? 'Lainnya', $row['produk'], $sku)
                : null;

            OrderItem::create([
                'order_id' => $orderId,
                'produk_id' => $produk?->id,
                'brand' => $row['brand'] ?? null,
                'nama_produk_raw' => $row['produk'],
                'sku_raw' => $sku,
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
    }

    /**
     * Short codes (PCH, AG, MC, FF, SSN, ...) are real product names —
     * length alone isn't a reliable filter. What actually distinguishes a
     * real, sellable SKU from a freebie/placeholder line is: it has real
     * value attached. A $0 line (e.g. "PCH" pouch giveaways) never spawns a
     * catalog record, even if its name looks legitimate; the raw text is
     * still kept on the order item either way, just without a produk_id.
     *
     * Prefer the unit price (HARGA); fall back to the line TOTAL when HARGA
     * is blank — some daily-import templates only fill QTY + TOTAL and
     * leave HARGA empty, and a missing HARGA must not be read as "no
     * value" when TOTAL clearly shows real revenue.
     */
    private function isRealProductName(string $value, $harga = null, $subtotal = null): bool
    {
        $trimmed = trim($value);

        if ($trimmed === '' || mb_strlen($trimmed) < 2 || is_numeric($trimmed)) {
            return false;
        }

        // 'pch' (pouch) is deliberately here too: confirmed with the user
        // it's a free giveaway, never a real sellable SKU, regardless of
        // what a stray non-zero total on one row might suggest.
        static $placeholders = ['no product', 'tidak ada', 'n/a', 'na', 'kosong', 'none', '-', '--', 'pch'];
        if (in_array(mb_strtolower($trimmed), $placeholders, true)) {
            return false;
        }

        if ($harga !== null && (float) $harga > 0) {
            return true;
        }

        return $subtotal !== null && (float) $subtotal > 0;
    }

    /**
     * Guards against the specific failure mode that once silently wiped real
     * order data: a source file with a hidden/unlabeled extra column shifts
     * every value one column to the right of what its header claims, so
     * e.g. STATUS PEMBAYARAN ends up holding a raw number and RESELLER ends
     * up holding a long numeric Perpack-style id instead of a mitra code.
     * Both are things that never legitimately happen, so a high hit rate is
     * treated as certain misalignment rather than a coincidence.
     */
    private function detectColumnShift(array $orders): ?string
    {
        $sample = array_slice($orders, 0, 200);
        $checked = 0;
        $statusNumeric = 0;
        $resellerLikeId = 0;

        foreach ($sample as $row) {
            $status = $row['status_pembayaran'] ?? null;
            $reseller = $row['reseller'] ?? null;

            if ($status === null && $reseller === null) {
                continue;
            }
            $checked++;

            if ($status !== null && $status !== '' && is_numeric($status)) {
                $statusNumeric++;
            }
            if ($reseller !== null && is_numeric($reseller) && strlen((string) $reseller) > 10) {
                $resellerLikeId++;
            }
        }

        if ($checked === 0) {
            return null;
        }

        if ($statusNumeric / $checked > 0.15 || $resellerLikeId / $checked > 0.15) {
            return 'Kolom di file ini sepertinya bergeser (mis. STATUS PEMBAYARAN atau RESELLER berisi angka panjang, bukan teks yang wajar). '
                .'Ini biasanya karena ada kolom tersembunyi/tidak diberi header di antara kolom-kolom Master Transaksi. '
                .'Cek ulang urutan kolom sesuai template sebelum upload lagi — belum ada data yang disimpan.';
        }

        return null;
    }

    private const NAMA_BULAN_KE_ANGKA = [
        // Indonesia
        'januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4, 'mei' => 5, 'juni' => 6,
        'juli' => 7, 'agustus' => 8, 'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12,
        // English (beberapa file sumber nulis nama bulan bahasa Inggris)
        'january' => 1, 'february' => 2, 'march' => 3, 'may' => 5, 'june' => 6,
        'july' => 7, 'august' => 8, 'october' => 10, 'december' => 12,
    ];

    /**
     * Cross-check kolom TANGGAL (angka serial Excel, dikonversi apa adanya)
     * terhadap kolom BULAN ORDER (teks, kadang Indonesia kadang Inggris)
     * kalau ada di file — nangkep kasus di mana sumbernya sendiri salah
     * nyimpen tanggal (mis. 10/09 kebaca format Amerika jadi 9 Oktober)
     * sebelum data salah itu masuk database. Dibandingin sebagai ANGKA
     * bulan (bukan nama teks) biar gak kejebak beda bahasa. Kalau file gak
     * punya kolom BULAN ORDER, atau isinya nama bulan yang gak dikenali,
     * cross-check ini dilewat (gak reject, biar aman kalau formatnya beda).
     */
    private function detectBulanMismatch(array $orders): ?string
    {
        $mismatches = [];

        foreach ($orders as $row) {
            $bulanText = mb_strtolower(trim((string) ($row['bulan_order'] ?? '')));
            $tanggal = $row['tanggal'] ?? null;

            if ($bulanText === '' || ! $tanggal || ! isset(self::NAMA_BULAN_KE_ANGKA[$bulanText])) {
                continue;
            }

            $bulanDariTanggal = (int) Carbon::parse($tanggal)->format('n');

            if ($bulanDariTanggal !== self::NAMA_BULAN_KE_ANGKA[$bulanText]) {
                $mismatches[] = ($row['id_transaksi'] ?? $row['id_transaksi_perpack'] ?? '?')
                    .' (kolom TANGGAL = '.$tanggal.', kolom BULAN ORDER = '.$row['bulan_order'].')';
            }

            if (count($mismatches) >= 5) {
                break;
            }
        }

        if (empty($mismatches)) {
            return null;
        }

        return 'Kolom TANGGAL gak cocok sama kolom BULAN ORDER di beberapa baris — kemungkinan tanggalnya kebaca salah format (mis. 10/09 kebaca jadi 9 Oktober alih-alih 10 September). '
            .'Contoh: '.implode('; ', $mismatches).'. '
            .'Perbaiki dulu kolom TANGGAL di file sumbernya sebelum upload lagi — belum ada data yang disimpan.';
    }

    /**
     * A UNIQUE constraint violation on orders.no_order used to surface as a
     * raw SQL crash mid-transaction (safe — the transaction rolls back, but
     * a poor error message). Two real causes seen in practice: (1) Master
     * Detail Transaksi's per-product rows got pasted into Master Transaksi
     * by mistake, so the same order ID repeats once per product line; (2)
     * the file reuses an ID that's already in the database from an earlier
     * import on a different date (the same-date "data already exists"
     * replace-confirmation flow doesn't catch this, since it only compares
     * dates, not individual IDs).
     */
    private function detectDuplicateOrderIds(array $orders, string $rangeStart, string $rangeEnd): ?string
    {
        $counts = [];
        foreach ($orders as $row) {
            $key = ($row['id_transaksi'] ?? null) ?: ($row['id_transaksi_perpack'] ?? null);
            if (! $key) {
                continue;
            }
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        $withinFileDupes = collect($counts)->filter(fn ($c) => $c > 1);

        if ($withinFileDupes->isNotEmpty()) {
            $example = $withinFileDupes->keys()->first();

            return 'Sheet Master Transaksi punya ID Transaksi yang muncul berkali-kali (contoh: "'.$example.'" muncul '.$withinFileDupes->first().'x, seharusnya cuma 1x). '
                .'Master Transaksi itu 1 baris = 1 order — kalau baris-barisnya berisi data per produk (BRAND/SKU/HARGA beda-beda tiap baris), berarti data Master Detail Transaksi tertukar masuk ke sheet Master Transaksi. '
                .'Belum ada data yang disimpan.';
        }

        // Orders inside [$rangeStart, $rangeEnd] are expected to collide —
        // that's exactly what the "data already exists, timpa?" confirm
        // flow is for. Only an ID that collides with a DIFFERENT date's
        // order (a gap that flow doesn't cover, since it only compares
        // dates, not individual IDs) is worth failing loudly over.
        $allIds = collect(array_keys($counts));
        $existingIds = $allIds->isEmpty() ? collect() : Order::whereIn('no_order', $allIds)
            ->where(function ($q) use ($rangeStart, $rangeEnd) {
                $q->whereDate('tanggal_order', '<', $rangeStart)
                    ->orWhereDate('tanggal_order', '>', $rangeEnd);
            })
            ->pluck('no_order');

        if ($existingIds->isNotEmpty()) {
            return 'ID Transaksi berikut sudah ada di database dari import sebelumnya dengan tanggal yang berbeda dari file ini: '.$existingIds->take(5)->implode(', ')
                .($existingIds->count() > 5 ? ' (dan '.($existingIds->count() - 5).' lainnya)' : '')
                .'. Belum ada data yang disimpan.';
        }

        return null;
    }

    private function matchesDetail(array $headerMap): bool
    {
        return isset($headerMap['BRAND'], $headerMap['ID SALESMAN'])
            && (isset($headerMap['PRODUK']) || isset($headerMap['NAMA PRODUK']));
    }

    private function matchesTransaksi(array $headerMap): bool
    {
        return isset($headerMap['RESELLER'], $headerMap['TOTAL'], $headerMap['STATUS PEMBAYARAN'])
            && ! isset($headerMap['BRAND'], $headerMap['PRODUK']);
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
        try {
            $raw = $cell->isFormula() ? $cell->getCalculatedValue() : $cell->getValue();
        } catch (\Throwable) {
            $raw = $cell->getValue();
        }

        if ($raw === null) {
            return $key === 'tanggal' ? null : null;
        }

        if ($key === 'tanggal') {
            // Stored as a plain date string (not a Carbon instance) because
            // this row array gets cached/serialized between the "conflict
            // detected" and "confirm replace" requests.
            return $this->resolveDate($cell, $raw)?->toDateString();
        }

        if (in_array($key, ['total', 'diskon', 'diskon_claim', 'diskon_return', 'biaya_pendaftaran', 'ongkir', 'biaya_penanganan', 'total_transfer', 'harga', 'qty'], true)) {
            if ($raw === '' || $raw === null) {
                return null;
            }

            if (is_numeric($raw)) {
                return (float) $raw;
            }

            // Some source cells store large numbers as text in Indonesian
            // scientific notation with a comma decimal separator (e.g.
            // "1,06E+08" for 106 juta) instead of a period — is_numeric()
            // rejects the comma outright, silently zeroing real revenue.
            if (is_string($raw)) {
                $normalized = str_replace(',', '.', trim($raw));

                if (is_numeric($normalized)) {
                    return (float) $normalized;
                }
            }

            return null;
        }

        if (in_array($key, ['id_transaksi', 'id_transaksi_perpack'], true) && is_numeric($raw) && ! is_string($raw)) {
            // These are IDs, not quantities — PhpSpreadsheet hands back a
            // PHP float for numeric-typed cells, and casting a large float
            // straight to string (e.g. the ~15-digit "Perpack" id) produces
            // scientific notation ("3.20260102E+14") instead of the full
            // digit string. Format it as a plain integer instead.
            return sprintf('%.0f', $raw);
        }

        return trim((string) $raw);
    }

    private function resolveDate(Cell $cell, mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (ExcelDate::isDateTime($cell) && is_numeric($value)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value));
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
