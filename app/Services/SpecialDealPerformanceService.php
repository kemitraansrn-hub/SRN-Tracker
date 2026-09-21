<?php

namespace App\Services;

use App\Models\NewMitraFlag;
use App\Models\SpecialDeal;
use App\Models\TargetBulanan;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "Special Deal Performance" summary — per-segmen rollup of target vs
 * realisasi for the month. Yang dihitung HANYA mitra yang punya data di
 * menu Special Deal (tabel special_deals, kuartal berjalan), BUKAN semua
 * mitra di Data Mitra — jadi mitra yang baru masuk lewat Upload Master
 * Mitra tapi belum punya Special Deal gak ikut kehitung di sini, dan
 * begitu Special Deal-nya diajukan/diubah, tabel ini otomatis ikut berubah.
 * Segmen (Pareto/RTP/Special Reguler) juga dari Special Deal; mitra yang
 * Special Deal-nya bersegmen selain itu (Reguler) masuk baris Reguler.
 * Kalau ada lebih dari satu Special Deal buat mitra+kuartal yang sama,
 * yang dipakai yang paling baru diinput (created_at terakhir).
 * Dua baris tambahan di luar segmen biasa punya sumber sendiri:
 *   - Reactivation: mitra dengan target_bulanan.kategori = REAKTIVASI
 *     bulan ini (sumber otoritatif dari Excel import).
 *   - New Mitra: mitra yang ditandai manual admin (NewMitraFlag) — gak ada
 *     sinyal otomatis yang reliable buat "order pertama kali".
 * Target/omset tiap mitra tetap dari target_bulanan & orders bulan ini.
 * Ported from the "Spesial Deal Performance" sheet in the source workbook,
 * with Ach% standardized to Ach/Target for every row (the original sheet's
 * Reguler row computed Ach% from a slightly different numerator than the
 * Ach it displayed).
 */
class SpecialDealPerformanceService
{
    private const SEGMEN_LABELS = [
        'PARETO' => 'Pareto',
        'RTP' => 'RTP (Road To Pareto)',
        'SPECIAL REGULER' => 'Special Reguler',
    ];

    public static function summary(int $bulan, int $tahun, ?string $kaeCode = null): Collection
    {
        $targetSql = TargetBulanan::effectiveTargetSql();

        // segmen per mitra buat kuartal berjalan, sumber dari menu Special Deal.
        $segmenByMitraId = SpecialDeal::segmenByMitraId(Carbon::create($tahun, $bulan, 1));
        $specialDealMitraIds = $segmenByMitraId->keys()->all();

        $reaktivasiIdsAll = DB::table('target_bulanan')
            ->where('bulan', $bulan)->where('tahun', $tahun)->where('kategori', 'REAKTIVASI')
            ->pluck('mitra_id')->all();
        $newMitraIds = NewMitraFlag::where('bulan', $bulan)->where('tahun', $tahun)->pluck('mitra_id')->all();

        // Universe = mitra yang punya Special Deal, ditambah mitra Reactivation
        // & New Mitra (dua baris itu punya sumber sendiri di luar Special Deal).
        $universeIds = collect($specialDealMitraIds)->merge($reaktivasiIdsAll)->merge($newMitraIds)->unique()->values()->all();

        // Left join target_bulanan & orders, jadi mitra Special Deal yang
        // belum punya baris target tetap kebawa (target default 0, omset
        // default 0).
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
            ->whereIn('mitra.id', $universeIds)
            ->when($kaeCode, fn ($q) => $q->where('mitra.kae_code', $kaeCode))
            ->groupBy('mitra.id', 'mitra.nama', 'mitra.kode_mitra', 'target_bulanan.kategori', 'target_bulanan.komit', 'target_bulanan.target', 'target_bulanan.stretch', 'target_bulanan.tier_dipakai')
            ->selectRaw("mitra.id as mitra_id, mitra.nama, mitra.kode_mitra, target_bulanan.kategori, COALESCE($targetSql, 0) as target, COALESCE(SUM(orders.total_transaksi), 0) as omset")
            ->get()
            ->map(function ($r) use ($segmenByMitraId) {
                $r->segmen = $segmenByMitraId[$r->mitra_id] ?? null;
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

        $newMitra = $mitraRowsNonReaktivasi->whereIn('mitra_id', $newMitraIds);

        // Reguler = mitra yang punya Special Deal tapi segmennya bukan
        // Pareto/RTP/Special Reguler (umumnya REGULER), bukan kategori
        // REAKTIVASI, dan gak ditandai New Mitra. Mitra tanpa Special Deal
        // sama sekali gak dihitung di sini.
        $specialSegmenIds = $mitraRowsNonReaktivasi->whereIn('segmen', array_keys(self::SEGMEN_LABELS))->pluck('mitra_id')->all();
        $regulerRows = $mitraRowsNonReaktivasi
            ->whereIn('mitra_id', $specialDealMitraIds)
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
     * - Sisa mitra yang belanja bulan ini tapi gak punya Special Deal
     *   kuartal ini dan bukan REAKTIVASI — pool buat admin manual tandai
     *   "New Mitra". Yang gak ditandai gak ikut kehitung di summary()
     *   (yang dihitung di sana cuma mitra Special Deal, Reactivation, dan
     *   New Mitra).
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
     * Mitra IDs yang sudah ter-cover di summary(): punya Special Deal di
     * kuartal periode ini, atau kategori REAKTIVASI.
     */
    private static function countedMitraIds(int $bulan, int $tahun, ?string $kaeCode): array
    {
        $specialDealIds = SpecialDeal::segmenByMitraId(Carbon::create($tahun, $bulan, 1))->keys();

        return $specialDealIds->merge(self::reaktivasiMitraIds($bulan, $tahun, $kaeCode))->unique()->values()->all();
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
