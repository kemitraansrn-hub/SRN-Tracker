<?php

namespace App\Services;

use App\Models\NewMitraFlag;
use App\Models\TargetBulanan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "Special Deal Performance" summary — per-segmen rollup of target vs
 * realisasi for the month, plus two extra buckets that don't come from
 * target_bulanan.segmen directly:
 *   - Reactivation: mitra with orders this month but no target_bulanan row
 *     (or a zero target) for this month, i.e. they weren't expected to buy.
 *   - New Mitra: a subset of that same pool the admin manually tags as new
 *     (there's no reliable automatic signal for "first ever order").
 * Ported from the "Spesial Deal Performance" sheet in the source workbook,
 * with Ach% standardized to Ach/Target for every row (the original sheet's
 * Reguler row computed Ach% from a slightly different numerator than the
 * Ach it displayed).
 */
class SpecialDealPerformanceService
{
    private const SEGMEN_LABELS = [
        'PARETO' => 'Pareto',
        'RTP (ROAD TO PARETO)' => 'RTP (Road To Pareto)',
        'SPECIAL REGULER' => 'Special Reguler',
    ];

    public static function summary(int $bulan, int $tahun, ?string $kaeCode = null): Collection
    {
        $targetSql = TargetBulanan::effectiveTargetSql();

        $mitraRows = DB::table('target_bulanan')
            ->join('mitra', 'mitra.id', '=', 'target_bulanan.mitra_id')
            ->leftJoin('orders', function ($join) use ($bulan, $tahun) {
                $join->on('orders.mitra_id', '=', 'mitra.id')
                    ->whereYear('orders.tanggal_order', $tahun)
                    ->whereMonth('orders.tanggal_order', $bulan);
            })
            ->where('target_bulanan.bulan', $bulan)
            ->where('target_bulanan.tahun', $tahun)
            ->when($kaeCode, fn ($q) => $q->where('mitra.kae_code', $kaeCode))
            ->groupBy('mitra.id', 'mitra.nama', 'mitra.kode_mitra', 'target_bulanan.segmen', 'target_bulanan.komit', 'target_bulanan.target', 'target_bulanan.stretch', 'target_bulanan.tier_dipakai')
            ->selectRaw("mitra.id as mitra_id, mitra.nama, mitra.kode_mitra, target_bulanan.segmen, $targetSql as target, COALESCE(SUM(orders.total_transaksi), 0) as omset")
            ->get()
            ->map(function ($r) {
                $r->pct = $r->target > 0 ? round($r->omset / $r->target * 100, 1) : null;

                return $r;
            });

        // Mitra kategori REAKTIVASI ditarik keluar dari baris segmen aslinya
        // (Pareto/RTP/Special Reguler/Reguler) biar gak dobel hitung — mereka
        // dapat baris "Reactivation" sendiri dengan target/ach asli dari
        // target_bulanan-nya (bukan lagi ditandai null kayak sebelumnya).
        $reaktivasiMitraIds = self::reaktivasiMitraIds($bulan, $tahun, $kaeCode);
        $mitraRowsNonReaktivasi = $mitraRows->whereNotIn('mitra_id', $reaktivasiMitraIds);

        $rows = collect();
        foreach (self::SEGMEN_LABELS as $segmenValue => $label) {
            $rows->push(self::buildRow($label, $mitraRowsNonReaktivasi->where('segmen', $segmenValue)));
        }

        $regulerAll = $mitraRowsNonReaktivasi->where('segmen', 'REGULER');
        $regulerCounted = $regulerAll->where('target', '>', 0);
        $rows->push(self::buildRow('Reguler', $regulerCounted));

        $rows->push(self::buildRow('Reactivation', $mitraRows->whereIn('mitra_id', $reaktivasiMitraIds)));

        // Mitra already accounted for in one of the rows above (Pareto/RTP/
        // Special Reguler always count regardless of target; Reguler only
        // counts if target > 0; Reactivation counts via kategori REAKTIVASI)
        // — anyone else with orders this month, including a REGULER row
        // with a zero target, falls into the New Mitra pool below.
        $countedMitraIds = $mitraRowsNonReaktivasi->whereIn('segmen', array_keys(self::SEGMEN_LABELS))->pluck('mitra_id')
            ->merge($regulerCounted->pluck('mitra_id'))
            ->merge($reaktivasiMitraIds)
            ->all();
        $newMitraIds = NewMitraFlag::where('bulan', $bulan)->where('tahun', $tahun)->pluck('mitra_id')->all();

        $noTargetOrders = DB::table('orders')
            ->join('mitra', 'mitra.id', '=', 'orders.mitra_id')
            ->whereYear('orders.tanggal_order', $tahun)
            ->whereMonth('orders.tanggal_order', $bulan)
            ->whereNotIn('mitra.id', $countedMitraIds)
            ->when($kaeCode, fn ($q) => $q->where('mitra.kae_code', $kaeCode))
            ->groupBy('mitra.id')
            ->selectRaw('mitra.id as mitra_id, SUM(orders.total_transaksi) as omset')
            ->get();

        $newMitra = $noTargetOrders->whereIn('mitra_id', $newMitraIds);

        $rows->push([
            'segmen' => 'New Mitra',
            'jumlah_mitra' => $newMitra->count(),
            'mitra_active' => $newMitra->count(),
            'mitra_belanja_full' => $newMitra->count(),
            'target' => null,
            'ach' => $newMitra->sum('omset'),
            'ach_pct' => null,
            'succes_rate' => $newMitra->count() > 0 ? 100.0 : null,
            'gap' => null,
            'mitra_belum_belanja' => collect(),
        ]);

        $rows->push([
            'segmen' => 'All Chanel',
            'jumlah_mitra' => $rows->sum('jumlah_mitra'),
            'mitra_active' => $rows->sum('mitra_active'),
            'mitra_belanja_full' => $rows->sum('mitra_belanja_full'),
            'target' => $rows->sum('target'),
            'ach' => $rows->sum('ach'),
            'ach_pct' => $rows->sum('target') > 0 ? round($rows->sum('ach') / $rows->sum('target') * 100, 1) : null,
            'succes_rate' => $rows->sum('jumlah_mitra') > 0 ? round($rows->sum('mitra_belanja_full') / $rows->sum('jumlah_mitra') * 100, 1) : null,
            'gap' => $rows->sum('target') > 0 ? $rows->sum('ach') - $rows->sum('target') : null,
            'mitra_belum_belanja' => $rows->flatMap(fn ($r) => $r['mitra_belum_belanja'])->sortBy('nama')->values(),
        ]);

        return $rows->map(fn ($r) => (object) $r);
    }

    /**
     * Mitra with orders this month that aren't counted in any Pareto/RTP/
     * Special Reguler/Reguler row (no target_bulanan row, or one with a
     * zero effective target) — the pool the admin picks "New Mitra" from;
     * everything left over is "Reactivation".
     */
    /**
     * List gabungan buat tabel "Reactivation & New Mitra" di Dashboard:
     * - Mitra kategori REAKTIVASI bulan ini (dari target_bulanan, sumber
     *   otoritatif dari Excel) — is_new_mitra selalu false, from_kategori
     *   true (gak ada tombol toggle, kategorinya sudah pasti dari import).
     * - Sisa mitra yang belanja bulan ini tapi gak ke-cover di segmen/
     *   kategori apa pun — pool buat admin manual tandai "New Mitra".
     */
    public static function reactivationCandidates(int $bulan, int $tahun, ?string $kaeCode = null): Collection
    {
        $countedMitraIds = self::countedMitraIds($bulan, $tahun, $kaeCode);
        $newMitraIds = NewMitraFlag::where('bulan', $bulan)->where('tahun', $tahun)->pluck('mitra_id');

        $reaktivasiRows = DB::table('mitra')
            ->join('target_bulanan', function ($join) use ($bulan, $tahun) {
                $join->on('target_bulanan.mitra_id', '=', 'mitra.id')
                    ->where('target_bulanan.bulan', $bulan)
                    ->where('target_bulanan.tahun', $tahun)
                    ->where('target_bulanan.kategori', 'REAKTIVASI');
            })
            ->leftJoin('orders', function ($join) use ($bulan, $tahun) {
                $join->on('orders.mitra_id', '=', 'mitra.id')
                    ->whereYear('orders.tanggal_order', $tahun)
                    ->whereMonth('orders.tanggal_order', $bulan);
            })
            ->when($kaeCode, fn ($q) => $q->where('mitra.kae_code', $kaeCode))
            ->groupBy('mitra.id', 'mitra.nama', 'mitra.kode_mitra', 'mitra.kae_code')
            ->selectRaw('mitra.id as mitra_id, mitra.nama, mitra.kode_mitra, mitra.kae_code, COALESCE(SUM(orders.total_transaksi), 0) as omset')
            ->get()
            ->map(function ($r) {
                $r->is_new_mitra = false;
                $r->from_kategori = true;

                return $r;
            });

        $manualPool = DB::table('orders')
            ->join('mitra', 'mitra.id', '=', 'orders.mitra_id')
            ->whereYear('orders.tanggal_order', $tahun)
            ->whereMonth('orders.tanggal_order', $bulan)
            ->whereNotIn('mitra.id', $countedMitraIds)
            ->when($kaeCode, fn ($q) => $q->where('mitra.kae_code', $kaeCode))
            ->groupBy('mitra.id', 'mitra.nama', 'mitra.kode_mitra', 'mitra.kae_code')
            ->selectRaw('mitra.id as mitra_id, mitra.nama, mitra.kode_mitra, mitra.kae_code, SUM(orders.total_transaksi) as omset')
            ->get()
            ->map(function ($r) use ($newMitraIds) {
                $r->is_new_mitra = $newMitraIds->contains($r->mitra_id);
                $r->from_kategori = false;

                return $r;
            });

        return $reaktivasiRows->concat($manualPool)->values();
    }

    /** Mitra IDs dengan target_bulanan.kategori = REAKTIVASI periode ini. */
    private static function reaktivasiMitraIds(int $bulan, int $tahun, ?string $kaeCode): array
    {
        return DB::table('target_bulanan')
            ->join('mitra', 'mitra.id', '=', 'target_bulanan.mitra_id')
            ->where('target_bulanan.bulan', $bulan)
            ->where('target_bulanan.tahun', $tahun)
            ->where('target_bulanan.kategori', 'REAKTIVASI')
            ->when($kaeCode, fn ($q) => $q->where('mitra.kae_code', $kaeCode))
            ->pluck('target_bulanan.mitra_id')
            ->all();
    }

    /**
     * Mitra IDs already covered by a Pareto/RTP/Special Reguler row (any
     * target), a Reguler row with target > 0, or kategori REAKTIVASI, for
     * the given period.
     */
    private static function countedMitraIds(int $bulan, int $tahun, ?string $kaeCode): array
    {
        $targetSql = TargetBulanan::effectiveTargetSql();

        $rows = DB::table('target_bulanan')
            ->join('mitra', 'mitra.id', '=', 'target_bulanan.mitra_id')
            ->where('target_bulanan.bulan', $bulan)
            ->where('target_bulanan.tahun', $tahun)
            ->when($kaeCode, fn ($q) => $q->where('mitra.kae_code', $kaeCode))
            ->selectRaw("mitra.id as mitra_id, target_bulanan.segmen, $targetSql as target")
            ->get();

        $counted = $rows->filter(fn ($r) => in_array($r->segmen, array_keys(self::SEGMEN_LABELS), true) || $r->target > 0)
            ->pluck('mitra_id');

        return $counted->merge(self::reaktivasiMitraIds($bulan, $tahun, $kaeCode))->unique()->values()->all();
    }

    private static function buildRow(string $label, Collection $mitraRows): array
    {
        $target = $mitraRows->sum('target');
        $ach = $mitraRows->sum('omset');
        $belanjaFull = $mitraRows->filter(fn ($r) => $r->pct !== null && $r->pct >= 100);

        return [
            'segmen' => $label,
            'jumlah_mitra' => $mitraRows->count(),
            'mitra_active' => $mitraRows->where('omset', '>', 0)->count(),
            'mitra_belanja_full' => $belanjaFull->count(),
            'target' => $target,
            'ach' => $ach,
            'ach_pct' => $target > 0 ? round($ach / $target * 100, 1) : null,
            'succes_rate' => $mitraRows->count() > 0
                ? round($belanjaFull->count() / $mitraRows->count() * 100, 1)
                : null,
            'gap' => $target > 0 ? $ach - $target : null,
            'mitra_belum_belanja' => $mitraRows->where('omset', 0)
                ->sortBy('nama')
                ->map(fn ($r) => ['nama' => $r->nama, 'kode_mitra' => $r->kode_mitra])
                ->values(),
            'mitra_belanja_full_list' => $belanjaFull->sortBy('nama')
                ->map(fn ($r) => ['nama' => $r->nama, 'kode_mitra' => $r->kode_mitra, 'pct' => $r->pct])
                ->values(),
        ];
    }
}
