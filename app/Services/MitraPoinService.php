<?php

namespace App\Services;

use App\Models\PoinRedemption;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Poin Mitra: earned from qty sold per produk (produk.qty_per_poin = how
 * many pcs make 1 poin — a fraction like 0.5 means each pc is worth 2 poin,
 * for bundle SKUs that award more than 1 poin per unit), computed live from
 * orders — never stored as a ledger, so a year "resets" simply by filtering
 * to that year's orders.
 * Conversion is cumulative across the year: a product's running qty total
 * (Jan..current month) is what gets divided by its rate and rounded to the
 * nearest whole poin (matching the source spreadsheet's convention), so
 * leftover pcs that don't yet round up to a full poin carry forward into
 * next month instead of being lost at each month boundary. Rounding a
 * monotonically non-decreasing cumulative value is itself non-decreasing,
 * so a month's delta against the previous month's rounded total is never
 * negative.
 */
class MitraPoinService
{
    /**
     * @param  int[]  $mitraIds
     * @return Collection keyed by mitra_id => ['monthly' => [1..12 => poin], 'total' => poin]
     */
    public static function bulkForYear(array $mitraIds, int $tahun): Collection
    {
        $empty = fn () => ['monthly' => array_fill(1, 12, 0), 'total' => 0];

        if (empty($mitraIds)) {
            return collect();
        }

        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('produk', 'produk.id', '=', 'order_items.produk_id')
            ->whereIn('orders.mitra_id', $mitraIds)
            ->whereYear('orders.tanggal_order', $tahun)
            ->whereNotNull('produk.qty_per_poin')
            ->where('produk.qty_per_poin', '>', 0)
            ->selectRaw('orders.mitra_id as mitra_id, order_items.produk_id as produk_id, produk.qty_per_poin as qty_per_poin, MONTH(orders.tanggal_order) as bulan, SUM(order_items.qty) as qty')
            ->groupBy('orders.mitra_id', 'order_items.produk_id', 'produk.qty_per_poin', 'bulan')
            ->get();

        $monthlyByMitra = [];

        foreach ($rows->groupBy(fn ($r) => $r->mitra_id.'|'.$r->produk_id) as $group) {
            $mitraId = (int) $group->first()->mitra_id;
            $rate = (float) $group->first()->qty_per_poin;
            $qtyByMonth = $group->pluck('qty', 'bulan');

            $cumQty = 0;
            $prevPoin = 0;
            for ($m = 1; $m <= 12; $m++) {
                $cumQty += (int) ($qtyByMonth->get($m) ?? 0);
                $poinSoFar = (int) round($cumQty / $rate);
                $earnedThisMonth = $poinSoFar - $prevPoin;
                $monthlyByMitra[$mitraId][$m] = ($monthlyByMitra[$mitraId][$m] ?? 0) + $earnedThisMonth;
                $prevPoin = $poinSoFar;
            }
        }

        return collect($mitraIds)->mapWithKeys(function ($id) use ($monthlyByMitra, $empty) {
            $monthly = $monthlyByMitra[$id] ?? null;

            if (! $monthly) {
                return [$id => $empty()];
            }

            $full = array_fill(1, 12, 0);
            foreach ($monthly as $m => $poin) {
                $full[$m] = $poin;
            }

            return [$id => ['monthly' => $full, 'total' => array_sum($full)]];
        });
    }

    public static function forMitra(int $mitraId, int $tahun): array
    {
        return static::bulkForYear([$mitraId], $tahun)->get($mitraId) ?? ['monthly' => array_fill(1, 12, 0), 'total' => 0];
    }

    /**
     * Poin masih tersedia = poin diperoleh tahun ini - poin yang sudah
     * "dijanjikan" ke penukaran (on-check ATAU approved, keduanya
     * mengurangi saldo supaya poin tidak bisa dijanjikan dobel).
     */
    public static function saldo(int $mitraId, int $tahun): int
    {
        $earned = static::forMitra($mitraId, $tahun)['total'];
        $used = PoinRedemption::where('mitra_id', $mitraId)
            ->where('tahun', $tahun)
            ->whereIn('status', ['on-check', 'approved'])
            ->sum('poin_terpakai');

        return $earned - $used;
    }
}
