<?php

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;

/**
 * Tombol "Synchronize" di menu Import Data -- narik Order Harian langsung
 * dari Perpack/Reglow Open API, sebagai alternatif upload Excel manual
 * (upload manual tetap ada, fitur ini gak menggantikannya).
 *
 * Desain disepakati 2026-11-06, dibangun 2026-11-20 setelah kredensial +
 * endpoint rincian produk tersedia (lihat memory project_perpack_order_sync.md).
 * Dua pass per sync run:
 *  1. Order baru: dari tanggal sync sukses terakhir (atau awal bulan berjalan
 *     kalau belum pernah sync) sampai hari ini. Order berstatus "cancelled"
 *     di Perpack tidak pernah dimasukkan sama sekali.
 *  2. Reconcile pembatalan: re-cek 2 hari terakhir saja -- order yang sudah
 *     ada di SRN tapi sekarang "cancelled" di Perpack di-hard-delete (cascade
 *     ke order_items). Order lebih lama dari 2 hari tidak di-re-cek otomatis;
 *     kalau ada pembatalan telat ketahuan, fallback-nya hapus manual lewat
 *     menu Transaksi (keputusan produk, bukan keterbatasan teknis).
 *
 * "Tanggal sync terakhir" disimpan di kolom import_batches.tanggal_data yang
 * SUDAH ADA (jenis='order_sync', status='berhasil') -- sengaja tidak nambah
 * kolom/tabel baru.
 */
class PerpackOrderSyncService
{
    private const RECONCILE_DAYS = 2;

    public function __construct(
        private readonly PerpackApiClient $client,
        private readonly OrderImportService $orderImporter,
    ) {}

    /**
     * @return array{ok: bool, baru: int, dibatalkan: int, pesan: string}
     */
    public function sync(): array
    {
        $lastSynced = ImportBatch::query()
            ->where('jenis', 'order_sync')
            ->where('status', 'berhasil')
            ->max('tanggal_data');

        $pullStart = $lastSynced ? Carbon::parse($lastSynced) : now()->startOfMonth();
        $pullEnd = today();

        $batch = ImportBatch::create([
            'jenis' => 'order_sync',
            'tanggal_data' => $pullStart,
            'nama_file' => '(Perpack API)',
            'uploaded_by' => auth()->id(),
            'jumlah_baris' => 0,
            'status' => 'berhasil',
        ]);

        try {
            $jumlahBaru = $this->pullNewOrders($pullStart, $pullEnd, $batch);
            $jumlahDibatalkan = $this->reconcileCancellations();

            $batch->update([
                'tanggal_data' => $pullEnd,
                'jumlah_baris' => $jumlahBaru,
                'status' => 'berhasil',
                'catatan' => "{$jumlahBaru} order baru, {$jumlahDibatalkan} dibatalkan/dihapus. Rentang: {$pullStart->format('d/m/Y')} s/d {$pullEnd->format('d/m/Y')}.",
            ]);

            return [
                'ok' => true,
                'baru' => $jumlahBaru,
                'dibatalkan' => $jumlahDibatalkan,
                'pesan' => "Sinkronisasi berhasil: {$jumlahBaru} order baru, {$jumlahDibatalkan} order dibatalkan/dihapus.",
            ];
        } catch (\Throwable $e) {
            $batch->update([
                'status' => 'gagal',
                'catatan' => 'Gagal: '.$e->getMessage(),
            ]);

            return [
                'ok' => false,
                'baru' => 0,
                'dibatalkan' => 0,
                'pesan' => 'Sinkronisasi gagal: '.$e->getMessage(),
            ];
        }
    }

    private function pullNewOrders(Carbon $start, Carbon $end, ImportBatch $batch): int
    {
        $query = [
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'date_type' => 'created_at',
        ];

        $orderRows = $this->client->listOrders($query);
        $candidateRows = array_filter($orderRows, fn (array $row) => ($row['status'] ?? null) !== 'cancelled');

        if ($candidateRows === []) {
            return 0;
        }

        $existingNoOrder = Order::query()
            ->whereIn('no_order', array_column($candidateRows, 'reference_no'))
            ->pluck('no_order')
            ->flip();

        $newRows = array_filter($candidateRows, fn (array $row) => ! isset($existingNoOrder[$row['reference_no']]));

        if ($newRows === []) {
            return 0;
        }

        $detailRows = $this->client->listOrderDetails($query);
        $detailByOrderId = [];
        foreach ($detailRows as $detail) {
            $detailByOrderId[$detail['order_id']][] = $detail;
        }

        $count = 0;
        foreach ($newRows as $row) {
            $this->insertOrder($row, $detailByOrderId[$row['id']] ?? [], $batch);
            $count++;
        }

        return $count;
    }

    private function insertOrder(array $row, array $detailRows, ImportBatch $batch): void
    {
        $salesmanCode = $detailRows[0]['salesman_code'] ?? null;

        $mitra = $this->orderImporter->resolveMitra(
            $row['reseller_id'],
            $row['reseller_name'] ?? $row['reseller_id'],
            null,
            $salesmanCode ?: null,
        );

        $amount = (float) ($row['amount'] ?? 0);
        $amountPaid = (float) ($row['amount_paid'] ?? 0);
        $tanggalOrder = $this->resolveTanggalOrder($row);

        $order = Order::create([
            'import_batch_id' => $batch->id,
            'no_order' => $row['reference_no'],
            'no_order_perpack' => $row['reference_no'],
            'tanggal_order' => $tanggalOrder,
            'mitra_id' => $mitra->id,
            'total_transaksi' => $amount,
            'qty' => $row['qty_order'] ?? $row['qty'] ?? null,
            'diskon' => $row['discount_order'] ?? 0,
            'diskon_claim' => $row['discount_claim'] ?? null,
            'diskon_return' => $row['discount_return'] ?? null,
            'biaya_pendaftaran' => 0,
            'ongkir' => $row['shipping_fee'] ?? 0,
            'total_transfer' => $amountPaid,
            'status_pembayaran' => $amountPaid >= $amount ? 'Lunas' : 'Tempo',
            'status' => null,
            'kae_code' => $salesmanCode ?: null,
            'id_channel' => isset($row['channel_id']) ? (string) $row['channel_id'] : null,
        ]);

        foreach ($detailRows as $detail) {
            $produkNama = $detail['product_name'] ?? null;
            if (! $produkNama) {
                continue;
            }

            $sku = $detail['product_sku'] ?? null;
            $produk = $this->orderImporter->isRealProductName($produkNama)
                ? $this->orderImporter->resolveProduk($detail['client_name'] ?? 'Lainnya', $produkNama, $sku)
                : null;

            OrderItem::create([
                'order_id' => $order->id,
                'produk_id' => $produk?->id,
                'brand' => $detail['client_name'] ?? null,
                'nama_produk_raw' => $produkNama,
                'sku_raw' => $sku,
                'qty' => $detail['qty_order'] ?? 0,
                'harga' => $detail['sell_price'] ?? null,
                'subtotal' => $detail['total'] ?? 0,
            ]);
        }
    }

    private function resolveTanggalOrder(array $row): string
    {
        $orderDate = $row['order_date'] ?? null;
        if ($orderDate && ! str_starts_with($orderDate, '0001-01-01')) {
            return Carbon::parse($orderDate)->format('Y-m-d');
        }

        return Carbon::parse($row['created_at'])->format('Y-m-d');
    }

    private function reconcileCancellations(): int
    {
        $start = today()->subDays(self::RECONCILE_DAYS);
        $end = today();

        $rows = $this->client->listOrders([
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'date_type' => 'created_at',
        ]);

        $cancelledRefs = array_column(
            array_filter($rows, fn (array $row) => ($row['status'] ?? null) === 'cancelled'),
            'reference_no',
        );

        if ($cancelledRefs === []) {
            return 0;
        }

        $toDelete = Order::query()->whereIn('no_order', $cancelledRefs)->get();
        foreach ($toDelete as $order) {
            $order->delete();
        }

        return $toDelete->count();
    }
}
