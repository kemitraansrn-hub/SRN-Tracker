<?php

namespace App\Services;

use App\Models\NewMitraFlag;
use App\Models\TargetBulanan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "Special Deal Performance" summary — per-segmen rollup of target vs
 * realisasi for the month, buat SEMUA mitra aktif (bukan cuma yang punya
 * baris target_bulanan), plus dua baris tambahan di luar segmen biasa:
 *   - Reactivation: mitra dengan target_bulanan.kategori = REAKTIVASI
 *     bulan ini (sumber otoritatif dari Excel import).
 *   - New Mitra: mitra yang ditandai manual admin (NewMitraFlag) — gak ada
 *     sinyal otomatis yang reliable buat "order pertama kali".
 * Sisanya (gak Pareto/RTP/Special Reguler/REAKTIVASI/New Mitra) jatuh ke
 * baris Reguler apa adanya, termasuk yang target-nya 0 atau gak punya
 * baris target_bulanan sama sekali — biar kalau ternyata belanja, omsetnya
 * otomatis kehitung di situ tanpa perlu ditandai manual dulu.
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

        // Basis-nya SEMUA mitra aktif (bukan cuma yang punya baris
        // target_bulanan) — left join target_bulanan & orders, jadi mitra
        // tanpa target sama sekali tetap kebawa (target default 0, omset
        // default 0) dan otomatis nyantol ke baris Reguler di bawah kalau
        // gak masuk kategori/segmen khusus mana pun.
        $mitraRows = DB::table('mitra')
            ->leftJoin('target_bulanan', function ($join) use ($bulan, $tahun) {
                $join->on('target_bulanan.mitra_id', '=', 'mitra.id')
                    ->where('target_bulanan.bulan', $bulan)
                    ->where('target_bulanan.tahun', $tahun);
            })
            ->leftJoin('orders', function ($join) use ($bulan, $tahun) {
                $join->on('orders.mitra_id', '=', 'mitra.id')
                    ->whereYear('orders.tanggal_order', $tahun)
                    ->whereMonth('orders.tanggal_order', $bulan);
            })
            ->where('mitra.status', 'aktif')
            ->when($kaeCode, fn ($q) => $q->where('mitra.kae_code', $kaeCode))
            ->groupBy('mitra.id', 'mitra.nama', 'mitra.kode_mitra', 'target_bulanan.segmen', 'target_bulanan.kategori', 'target_bulanan.komit', 'target_bulanan.target', 'target_bulanan.stretch', 'target_bulanan.tier_dipakai')
            ->selectRaw("mitra.id as mitra_id, mitra.nama, mitra.kode_mitra, target_bulanan.segmen, target_bulanan.kategori, COALESCE($targetSql, 0) as target, COALESCE(SUM(orders.total_transaksi), 0) as omset")
            ->get()
            ->map(function ($r) {
                $r->target = (float) $r->target;
                $r->omset = (float) $r->omset;
                $r->pct = $r->target > 0 ? round($r->omset / $r->target * 100, 1) : null;

                return $r;
            });

        // Mitra kategori REAKTIVASI ditarik keluar dari baris segmen aslinya
        // (Pareto/RTP/Special Reguler/Reguler) biar gak dobel hitung — mereka
        // dapat baris "Reactivation" sendiri dengan target/ach asli dari
        // target_bulanan-nya (bukan lagi ditandai null kayak sebelumnya).
        $reaktivasiMitraIds = $mitraRows->where('kategori', 'REAKTIVASI')->pluck('mitra_id')->all();
        $mitraRowsNonReaktivasi = $mitraRows->whereNotIn('mitra_id', $reaktivasiMitraIds);

        $rows = collect();
        foreach (self::SEGMEN_LABELS as $segmenValue => $label) {
            $rows->push(self::buildRow($label, $mitraRowsNonReaktivasi->where('segmen', $segmenValue)));
        }

        $newMitraIds = NewMitraFlag::where('bulan', $bulan)->where('tahun', $tahun)->pluck('mitra_id')->all();
        $newMitra = $mitraRowsNonReaktivasi->whereIn('mitra_id', $newMitraIds);

        // Reguler = catch-all: semua mitra aktif yang gak masuk Pareto/RTP/
        // Special Reguler, gak kategori REAKTIVASI, dan gak ditandai New
        // Mitra — termasuk yang target_bulanan-nya 0 atau gak punya baris
        // sama sekali. Mereka tetap kehitung (target 0, omset ikut real
        // kalau ada) biar kalau bulan ini/depan ternyata belanja, langsung
        // nambah ke pencapaian Reguler tanpa perlu ditandai manual dulu.
        $specialSegmenIds = $mitraRowsNonReaktivasi->whereIn('segmen', array_keys(self::SEGMEN_LABELS))->pluck('mitra_id')->all();
        $regulerRows = $mitraRowsNonReaktivasi
            ->whereNotIn('mitra_id', $specialSegmenIds)
            ->whereNotIn('mitra_id', $newMitraIds);

        $rows->push(self::buildRow('Reguler', $regulerRows));
        $rows->push(self::buildRow('Reactivation', $mitraRows->whereIn('mitra_id', $reaktivasiMitraIds)));

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
     * List gabungan buat tabel "Reactivation & New Mitra" di Dashboard —
     * cuma mitra yang BENERAN BELANJA bulan ini (omset > 0), beda dengan
     * baris "Reactivation" di summary() yang nampilin semua mitra kategori
     * REAKTIVASI apa adanya termasuk yang belum belanja:
     * - Mitra kategori REAKTIVASI bulan ini (dari target_bulanan, sumber
     *   otoritatif dari Excel) DENGAN omset > 0 — is_new_mitra selalu
     *   false, from_kategori true (gak ada tombol toggle, kategorinya
     *   sudah pasti dari import).
     * - Sisa mitra yang belanja bulan ini tapi gak ke-cover di segmen/
     *   kategori apa pun — pool buat admin manual tandai "New Mitra". Yang
     *   gak ditandai dari pool ini tetap dilipat ke baris Reguler di
     *   summary() (lihat komentar di sana), jadi tetap tercatat di ringkasan.
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
            ->havingRaw('COALESCE(SUM(orders.total_transaksi), 0) > 0')
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
