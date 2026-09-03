<?php

namespace App\Services;

use App\Models\NpdProduct;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * YTD "kesehatan mitra" analysis (RFM-style): order frequency/monetary
 * stats, recency, payment reliability, brand contribution, weekly ordering
 * pattern, top/bottom moving SKUs, and Portfolio & Assortment Health (White
 * Space, Hero SKU Concentration, NPD Adoption, tier) — all derived from
 * orders/order_items, no manual input required except NPD tagging.
 */
class MitraHealthService
{
    /**
     * @param  int[]  $mitraIds
     * @return Collection keyed by mitra_id
     */
    public static function bulkForYtd(array $mitraIds, ?Carbon $referenceDate = null): Collection
    {
        if (empty($mitraIds)) {
            return collect();
        }

        $now = $referenceDate ?? now();
        $startOfYear = $now->copy()->startOfYear()->toDateString();
        $today = $now->toDateString();

        $orderStats = DB::table('orders')
            ->whereIn('mitra_id', $mitraIds)
            ->whereBetween('tanggal_order', [$startOfYear, $today])
            ->groupBy('mitra_id')
            ->selectRaw('
                mitra_id,
                COUNT(*) as jumlah_order,
                SUM(total_transaksi) as total_omset,
                MAX(tanggal_order) as order_terakhir,
                SUM(CASE WHEN status_pembayaran = "Lunas" THEN 1 ELSE 0 END) as jumlah_lunas
            ')
            ->get()
            ->keyBy('mitra_id');

        $mingguFavorit = DB::table('orders')
            ->whereIn('mitra_id', $mitraIds)
            ->whereBetween('tanggal_order', [$startOfYear, $today])
            ->selectRaw("mitra_id, CONCAT('W', LEAST(4, CEIL(DAY(tanggal_order) / 7))) as minggu, COUNT(*) as jml")
            ->groupBy('mitra_id', 'minggu')
            ->get()
            ->groupBy('mitra_id')
            ->map(fn (Collection $rows) => $rows->sortByDesc('jml')->first()->minggu);

        $brandRows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.mitra_id', $mitraIds)
            ->whereBetween('orders.tanggal_order', [$startOfYear, $today])
            ->selectRaw('orders.mitra_id as mitra_id, COALESCE(order_items.brand, "Lainnya") as brand, SUM(order_items.subtotal) as total')
            ->groupBy('orders.mitra_id', 'brand')
            ->get()
            ->groupBy('mitra_id')
            ->map(function (Collection $rows) {
                $sum = (float) $rows->sum('total');

                return $rows->sortByDesc('total')->values()->map(fn ($r) => [
                    'brand' => $r->brand,
                    'pct' => $sum > 0 ? round(((float) $r->total) / $sum * 100, 1) : 0,
                ]);
            });

        $skuRows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.mitra_id', $mitraIds)
            ->whereBetween('orders.tanggal_order', [$startOfYear, $today])
            ->whereNotNull('order_items.produk_id')
            ->selectRaw('orders.mitra_id as mitra_id, order_items.nama_produk_raw as sku, SUM(order_items.qty) as total_qty')
            ->groupBy('orders.mitra_id', 'sku')
            ->get()
            ->groupBy('mitra_id')
            ->map(function (Collection $rows) {
                $sorted = $rows->sortByDesc('total_qty')->values();

                return [
                    'top5' => $sorted->take(5),
                    'bottom5' => $sorted->count() > 5 ? $sorted->reverse()->take(5)->values() : collect(),
                ];
            });

        // Hero SKU Concentration: top 3 SKU by omzet (Rp), not qty — a
        // separate ranking from top5_sku above, which is qty-based.
        $heroRows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.mitra_id', $mitraIds)
            ->whereBetween('orders.tanggal_order', [$startOfYear, $today])
            ->whereNotNull('order_items.produk_id')
            ->selectRaw('orders.mitra_id as mitra_id, order_items.nama_produk_raw as sku, SUM(order_items.subtotal) as total')
            ->groupBy('orders.mitra_id', 'sku')
            ->get()
            ->groupBy('mitra_id')
            ->map(function (Collection $rows) {
                $sorted = $rows->sortByDesc('total')->values();
                $total = (float) $rows->sum('total');
                $top3 = (float) $sorted->take(3)->sum('total');

                return [
                    'top3_sku' => $sorted->take(3),
                    'pct' => $total > 0 ? round($top3 / $total * 100, 1) : null,
                ];
            });

        // White Space: within brands the mitra has actually bought from,
        // what share of the catalog's real (produk-resolved) SKUs did they
        // never touch. Scoped per purchased brand so a mitra who
        // specializes in one brand isn't unfairly penalized for not
        // carrying the others.
        $catalogProdukByBrand = DB::table('produk')
            ->where('status', 'aktif')
            ->select('id', 'brand', 'nama')
            ->get()
            ->groupBy('brand');

        $boughtProdukIdsByMitra = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.mitra_id', $mitraIds)
            ->whereBetween('orders.tanggal_order', [$startOfYear, $today])
            ->whereNotNull('order_items.produk_id')
            ->select('orders.mitra_id', 'order_items.produk_id')
            ->distinct()
            ->get()
            ->groupBy('mitra_id')
            ->map(fn (Collection $rows) => $rows->pluck('produk_id')->flip());

        $brandsPurchasedByMitra = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('produk', 'produk.id', '=', 'order_items.produk_id')
            ->whereIn('orders.mitra_id', $mitraIds)
            ->whereBetween('orders.tanggal_order', [$startOfYear, $today])
            ->select('orders.mitra_id', 'produk.brand')
            ->distinct()
            ->get()
            ->groupBy('mitra_id')
            ->map(fn (Collection $rows) => $rows->pluck('brand'));

        $whiteSpaceData = $brandsPurchasedByMitra->map(function (Collection $brands, $mitraId) use ($catalogProdukByBrand, $boughtProdukIdsByMitra) {
            $boughtIds = $boughtProdukIdsByMitra->get($mitraId, collect());
            $catalogTotal = 0;
            $boughtTotal = 0;
            $missing = collect();
            $byBrand = collect();

            foreach ($brands as $brand) {
                $catalog = $catalogProdukByBrand->get($brand, collect());
                $catalogCount = $catalog->count();
                $inBrandBought = $catalog->filter(fn ($p) => $boughtIds->has($p->id));
                $boughtCount = $inBrandBought->count();
                $missingInBrand = $catalog->reject(fn ($p) => $boughtIds->has($p->id))->values();

                $catalogTotal += $catalogCount;
                $boughtTotal += $boughtCount;
                $missing = $missing->merge($missingInBrand);

                $byBrand->push([
                    'brand' => $brand,
                    'catalog' => $catalogCount,
                    'bought' => $boughtCount,
                    'missing' => $catalogCount - $boughtCount,
                    'pct' => $catalogCount > 0 ? round(($catalogCount - $boughtCount) / $catalogCount * 100, 1) : null,
                    'missing_sku' => $missingInBrand,
                ]);
            }

            return [
                'pct' => $catalogTotal > 0 ? round(($catalogTotal - $boughtTotal) / $catalogTotal * 100, 1) : null,
                'missing_sku' => $missing->values(),
                'by_brand' => $byBrand->sortByDesc('pct')->values(),
            ];
        });

        $whiteSpace = $whiteSpaceData->map(fn ($d) => $d['pct']);

        // NPD Adoption: null when there's no currently-active NPD product
        // to evaluate against (nothing to have adopted or missed).
        $activeNpdIds = NpdProduct::activeProdukIds();
        $npdAdopters = empty($activeNpdIds) ? collect() : DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.mitra_id', $mitraIds)
            ->whereBetween('orders.tanggal_order', [$startOfYear, $today])
            ->whereIn('order_items.produk_id', $activeNpdIds)
            ->distinct()
            ->pluck('orders.mitra_id');

        return collect($mitraIds)->mapWithKeys(function ($id) use ($orderStats, $mingguFavorit, $brandRows, $skuRows, $heroRows, $whiteSpace, $whiteSpaceData, $activeNpdIds, $npdAdopters, $now) {
            $o = $orderStats->get($id);
            $jumlahOrder = (int) ($o->jumlah_order ?? 0);
            $totalOmset = (float) ($o->total_omset ?? 0);
            $recencyHari = ($o && $o->order_terakhir) ? Carbon::parse($o->order_terakhir)->startOfDay()->diffInDays($now->copy()->startOfDay()) : null;

            $npdStatus = empty($activeNpdIds) ? null : ($npdAdopters->contains($id) ? 'adopted' : 'not-adopted');
            $whiteSpacePct = $whiteSpace->get($id);
            $heroPct = $heroRows->get($id)['pct'] ?? null;

            return [$id => [
                'jumlah_order' => $jumlahOrder,
                'total_omset' => $totalOmset,
                'avg_order' => $jumlahOrder > 0 ? $totalOmset / $jumlahOrder : 0,
                'recency_hari' => $recencyHari,
                'rasio_lunas' => $jumlahOrder > 0 ? round(($o->jumlah_lunas / $jumlahOrder) * 100, 1) : null,
                'minggu_favorit' => $mingguFavorit->get($id),
                'brand_kontribusi' => $brandRows->get($id, collect()),
                'top5_sku' => $skuRows->get($id)['top5'] ?? collect(),
                'bottom5_sku' => $skuRows->get($id)['bottom5'] ?? collect(),
                'white_space_pct' => $whiteSpacePct,
                'white_space_missing_sku' => $whiteSpaceData->get($id)['missing_sku'] ?? collect(),
                'white_space_by_brand' => $whiteSpaceData->get($id)['by_brand'] ?? collect(),
                'white_space_status' => self::whiteSpaceStatus($whiteSpacePct),
                'hero_sku_pct' => $heroPct,
                'hero_sku_status' => self::heroSkuStatus($heroPct),
                'top3_sku_omzet' => $heroRows->get($id)['top3_sku'] ?? collect(),
                'npd_status' => $npdStatus,
                'lapsing' => $recencyHari !== null && $recencyHari > 60,
                'tier' => self::resolveTier($whiteSpacePct, $heroPct),
            ]];
        });
    }

    /**
     * Segment-wide Top N fast-moving SKU by qty (YTD), across all given
     * mitra — used for the "Ringkasan Portfolio & Assortment Health" card
     * on Segmentasi, distinct from the per-mitra top5_sku above.
     *
     * @param  int[]  $mitraIds
     * @return Collection<int, array{sku: string, qty: int}>
     */
    public static function topFastMovingSku(array $mitraIds, int $limit = 5, ?Carbon $referenceDate = null): Collection
    {
        if (empty($mitraIds)) {
            return collect();
        }

        $now = $referenceDate ?? now();
        $startOfYear = $now->copy()->startOfYear()->toDateString();
        $today = $now->toDateString();

        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.mitra_id', $mitraIds)
            ->whereBetween('orders.tanggal_order', [$startOfYear, $today])
            ->whereNotNull('order_items.produk_id')
            ->selectRaw('order_items.nama_produk_raw as sku, SUM(order_items.qty) as qty')
            ->groupBy('sku')
            ->orderByDesc('qty')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => ['sku' => $r->sku, 'qty' => (int) $r->qty]);
    }

    private static function whiteSpaceStatus(?float $pct): ?string
    {
        if ($pct === null) {
            return null;
        }

        return match (true) {
            $pct < 25 => 'sehat',
            $pct <= 50 => 'moderat',
            default => 'kritis',
        };
    }

    private static function heroSkuStatus(?float $pct): ?string
    {
        if ($pct === null) {
            return null;
        }

        return match (true) {
            $pct < 60 => 'sehat',
            $pct <= 75 => 'peringatan',
            default => 'berisiko',
        };
    }

    /**
     * Clean 2x2 quadrant off just White Space (portfolio breadth) x Hero
     * SKU Concentration (spend concentration) — every mitra with data
     * lands in exactly one quadrant, no overlap/gaps. NPD adoption and
     * lapsing are shown as separate badges alongside the tier instead of
     * gating conditions, since they're a different dimension (behavior
     * over time, not portfolio shape) and forcing them into the tier
     * logic left some healthy, active mitra unclassified.
     */
    private static function resolveTier(?float $whiteSpace, ?float $heroPct): ?string
    {
        if ($whiteSpace === null || $heroPct === null) {
            return null;
        }

        $wsHigh = $whiteSpace >= 40;
        $heroHigh = $heroPct >= 65;

        return match (true) {
            ! $wsHigh && ! $heroHigh => 'champion',
            ! $wsHigh && $heroHigh => 'explorer',
            $wsHigh && ! $heroHigh => 'traditional',
            default => 'cherry_picker',
        };
    }

    public static function tierLabel(?string $tier): string
    {
        return match ($tier) {
            'champion' => 'Champion / Balanced Partner',
            'explorer' => 'Explorer / Concentrated Trial',
            'traditional' => 'Traditional / Steady Niche',
            'cherry_picker' => 'Cherry Picker',
            default => 'Belum Ada Data',
        };
    }

    public static function tierColor(?string $tier): string
    {
        return match ($tier) {
            'champion' => 'good',
            'explorer' => 'accent',
            'traditional' => 'neutral',
            'cherry_picker' => 'critical',
            default => 'neutral',
        };
    }
}
