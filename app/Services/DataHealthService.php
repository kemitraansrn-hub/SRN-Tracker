<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Data-quality checks surfaced in the "Cek Kesehatan Data" admin page —
 * built after a real incident where 14 orders imported with a blank source
 * date silently fell back to 1970-01-01 and dropped out of every monthly
 * report (Run Rate Mitra Active showed 110 instead of 117 for Juni 2026).
 * Each check is read-only; fixing anything found here is a separate,
 * deliberate action.
 */
class DataHealthService
{
    private const PREVIEW_LIMIT = 50;

    /**
     * @return array<int, array{key: string, label: string, description: string, count: int, rows: \Illuminate\Support\Collection}>
     */
    public static function run(): array
    {
        return [
            self::tanggalOrderTidakValid(),
            self::orderTanpaMitraValid(),
            self::orderTotalNolOrNegatif(),
            self::kodeMitraTidakSesuaiFormat(),
            self::produkNamaMencurigakan(),
            self::produkTidakPernahDipakai(),
        ];
    }

    /**
     * Runs $query twice: once for an exact COUNT, once (cloned, limited) for
     * a preview of rows to actually display, so a >50-hit check still
     * reports its true total instead of silently capping at the preview.
     */
    private static function countAndPreview(Builder $query): array
    {
        $count = (clone $query)->count();
        $rows = $query->limit(self::PREVIEW_LIMIT)->get();

        return [$count, $rows];
    }

    private static function tanggalOrderTidakValid(): array
    {
        $query = DB::table('orders')
            ->leftJoin('mitra', 'mitra.id', '=', 'orders.mitra_id')
            ->where(function ($q) {
                $q->where('orders.tanggal_order', '<', '2020-01-01')
                    ->orWhere('orders.tanggal_order', '>', now()->addDays(3)->toDateString());
            })
            ->select('orders.id', 'orders.no_order', 'orders.tanggal_order', 'orders.total_transaksi', 'orders.import_batch_id', 'mitra.nama as mitra_nama', 'mitra.kode_mitra')
            ->orderByDesc('orders.id');

        [$count, $rows] = self::countAndPreview($query);

        return [
            'key' => 'tanggal_order_invalid',
            'label' => 'Tanggal Order Tidak Valid',
            'description' => 'Order dengan tanggal sebelum 2020 (biasanya bekas cell tanggal kosong di file sumber, ke-import jadi 1970-01-01) atau lebih dari 3 hari ke depan. Order begini hilang dari semua laporan bulanan/mingguan karena tidak masuk rentang tanggal manapun.',
            'count' => $count,
            'rows' => $rows,
        ];
    }

    private static function orderTanpaMitraValid(): array
    {
        $query = DB::table('orders')
            ->whereNotIn('mitra_id', DB::table('mitra')->select('id'))
            ->select('id', 'no_order', 'tanggal_order', 'mitra_id', 'total_transaksi', 'import_batch_id')
            ->orderByDesc('id');

        [$count, $rows] = self::countAndPreview($query);

        return [
            'key' => 'order_orphan_mitra',
            'label' => 'Order Tanpa Mitra Valid',
            'description' => 'Order yang mitra_id-nya tidak cocok dengan mitra manapun (data mitra kemungkinan terhapus/salah saat import). Order begini hilang dari laporan per-mitra dan per-KAE.',
            'count' => $count,
            'rows' => $rows,
        ];
    }

    private static function orderTotalNolOrNegatif(): array
    {
        $query = DB::table('orders')
            ->leftJoin('mitra', 'mitra.id', '=', 'orders.mitra_id')
            ->where('orders.total_transaksi', '<=', 0)
            ->select('orders.id', 'orders.no_order', 'orders.tanggal_order', 'orders.total_transaksi', 'orders.status', 'mitra.nama as mitra_nama')
            ->orderByDesc('orders.id');

        [$count, $rows] = self::countAndPreview($query);

        return [
            'key' => 'order_total_invalid',
            'label' => 'Order dengan Total Rp0 / Negatif',
            'description' => 'Order berstatus valid tapi total_transaksi-nya nol atau minus — kemungkinan salah baca kolom TOTAL saat import, atau memang order retur/void yang perlu status berbeda.',
            'count' => $count,
            'rows' => $rows,
        ];
    }

    private static function kodeMitraTidakSesuaiFormat(): array
    {
        // Real reseller codes are RE + 1 letter + a 10-11 digit
        // year/sequence number (e.g. REA2025060001) — confirmed by
        // checking the actual digit-length distribution across all mitra:
        // a clean split at 10-11 digits vs. a handful of 1-digit outliers
        // (REC1..REC4, manually created placeholders), nothing in between.
        $query = DB::table('mitra')
            ->whereRaw("kode_mitra NOT REGEXP '^RE[A-Z][0-9]{10,}$'")
            ->select('id', 'kode_mitra', 'nama', 'kae_code', 'status')
            ->orderByDesc('id');

        [$count, $rows] = self::countAndPreview($query);

        return [
            'key' => 'kode_mitra_format',
            'label' => 'Kode Mitra Tidak Sesuai Format',
            'description' => 'kode_mitra yang tidak mengikuti pola RE + 1 huruf + 10-11 digit angka (mis. REA2025060001) — bisa karena formula XLOOKUP gagal saat import dan menukar kolom kode/nama/alamat, atau kode sementara/manual yang belum diganti ke kode reseller resmi.',
            'count' => $count,
            'rows' => $rows,
        ];
    }

    private static function produkNamaMencurigakan(): array
    {
        // Length alone isn't suspicious — real SKU codes can be short
        // (PCH, AG, SM...). What's actually suspicious: purely numeric
        // names (price misread as name) or known placeholder text.
        $placeholders = ['no product', 'tidak ada', 'n/a', 'na', 'kosong', 'none', '-', '--'];

        $query = DB::table('produk')
            ->where(function ($q) use ($placeholders) {
                $q->whereRaw("nama REGEXP '^[0-9.]+$'")
                    ->orWhereIn(DB::raw('LOWER(TRIM(nama))'), $placeholders);
            })
            ->select('id', 'brand', 'nama', 'status')
            ->orderByDesc('id');

        [$count, $rows] = self::countAndPreview($query);

        return [
            'key' => 'produk_nama_mencurigakan',
            'label' => 'Produk dengan Nama Mencurigakan',
            'description' => 'Nama produk yang murni angka atau teks placeholder ("NO PRODUCT", "N/A", dsb) — pernah terjadi karena kolom harga ke-baca sebagai nama produk saat import.',
            'count' => $count,
            'rows' => $rows,
        ];
    }

    private static function produkTidakPernahDipakai(): array
    {
        $query = DB::table('produk')
            ->whereNotIn('id', DB::table('order_items')->whereNotNull('produk_id')->select('produk_id'))
            ->whereNotIn('id', DB::table('npd_products')->select('produk_id'))
            ->select('id', 'brand', 'nama', 'status', 'created_at')
            ->orderByDesc('created_at');

        [$count, $rows] = self::countAndPreview($query);

        return [
            'key' => 'produk_tidak_terpakai',
            'label' => 'Produk Tidak Pernah Dipakai di Order',
            'description' => 'Produk di katalog yang tidak pernah muncul di satupun order_items dan tidak ditandai NPD — biasanya sisa dari import yang periodenya kemudian "ditimpa"/diganti oleh import lain, tapi entri katalognya tidak ikut terhapus. Cek nama & brand-nya sebelum dihapus manual, siapa tahu memang produk baru yang belum laku.',
            'count' => $count,
            'rows' => $rows,
        ];
    }
}
