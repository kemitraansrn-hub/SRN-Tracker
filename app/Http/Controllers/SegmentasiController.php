<?php

namespace App\Http\Controllers;

use App\Models\TargetBulanan;
use App\Services\AchievementStatus;
use App\Services\MitraHealthService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SegmentasiController extends Controller
{
    public function show(Request $request, string $segmen): View
    {
        $user = $request->user();

        $bulan = $request->integer('bulan') ?: now()->month;
        $tahun = $request->integer('tahun') ?: now()->year;
        $periode = Carbon::create($tahun, $bulan, 1);
        $isBulanIni = $periode->isSameMonth(now());

        // Bulan yang lagi berjalan: pakai posisi minggu & tanggal hari ini
        // yang sebenarnya (buat pacing achievement). Bulan lain (sudah
        // lewat): anggap minggu ke-4 (target penuh sebulan), dan YTD
        // dihitung sampai akhir bulan yang dipilih, bukan sampai hari ini.
        $weekIdx = $isBulanIni ? AchievementStatus::currentWeekIndex(now()) : 4;
        $ytdReference = $isBulanIni ? now() : $periode->copy()->endOfMonth();

        $targetSql = TargetBulanan::effectiveTargetSql();

        $rows = DB::table('target_bulanan')
            ->join('mitra', 'mitra.id', '=', 'target_bulanan.mitra_id')
            ->leftJoin('orders', function ($join) use ($periode) {
                $join->on('orders.mitra_id', '=', 'mitra.id')
                    ->whereYear('orders.tanggal_order', $periode->year)
                    ->whereMonth('orders.tanggal_order', $periode->month);
            })
            ->where('target_bulanan.bulan', $periode->month)
            ->where('target_bulanan.tahun', $periode->year)
            ->where('target_bulanan.segmen', $segmen)
            ->when(! $user->canViewAll(), fn ($q) => $q->where('mitra.kae_code', $user->kae_code))
            ->groupBy('mitra.id', 'mitra.nama', 'mitra.kode_mitra', 'mitra.kae_code', 'target_bulanan.komit', 'target_bulanan.target', 'target_bulanan.stretch', 'target_bulanan.tier_dipakai')
            ->selectRaw("mitra.id, mitra.nama, mitra.kode_mitra, mitra.kae_code, $targetSql as target, COALESCE(SUM(orders.total_transaksi), 0) as omset")
            ->orderByDesc('omset')
            ->get()
            ->map(function ($r) use ($weekIdx) {
                $r->pct = $r->target > 0 ? round($r->omset / $r->target * 100, 1) : 0;
                $r->status = AchievementStatus::resolve($r->pct, $weekIdx);

                return $r;
            });

        $mitraIds = $rows->pluck('id')->all();
        $kesehatanMitra = MitraHealthService::bulkForYtd($mitraIds, $ytdReference);

        return view('segmentasi.show', [
            'segmen' => $segmen,
            'mitraList' => $rows,
            'totalOmset' => $rows->sum('omset'),
            'totalTarget' => $rows->sum('target'),
            'periodeLabel' => $periode->translatedFormat('F Y'),
            'bulan' => $periode->month,
            'tahun' => $periode->year,
            'kesehatanMitra' => $kesehatanMitra,
            'segmentSummary' => $this->buildSegmentSummary($kesehatanMitra, $mitraIds),
            'tahunYtd' => $periode->year,
        ]);
    }

    /**
     * @param  int[]  $mitraIds
     * @return array{tier_counts: array<string, int>, avg_white_space: ?float, avg_hero_sku: ?float, npd_adopted: int, npd_not_adopted: int, npd_active: bool, top_fast_moving: \Illuminate\Support\Collection}
     */
    private function buildSegmentSummary($kesehatanMitra, array $mitraIds): array
    {
        $tierCounts = ['champion' => 0, 'explorer' => 0, 'traditional' => 0, 'cherry_picker' => 0];
        foreach ($kesehatanMitra as $h) {
            if (isset($tierCounts[$h['tier']])) {
                $tierCounts[$h['tier']]++;
            }
        }

        $whiteSpaceValues = $kesehatanMitra->pluck('white_space_pct')->filter(fn ($v) => $v !== null);
        $heroSkuValues = $kesehatanMitra->pluck('hero_sku_pct')->filter(fn ($v) => $v !== null);
        $npdStatuses = $kesehatanMitra->pluck('npd_status')->filter();

        // Segment-wide White Space: for each SKU that shows up as "belum
        // dibeli" for at least one mitra, count how many mitra in the
        // segment are missing it — the SKUs with the highest count are the
        // segment's biggest untapped cross-sell opportunities.
        $missingTally = [];
        foreach ($kesehatanMitra as $h) {
            foreach ($h['white_space_missing_sku'] as $s) {
                $missingTally[$s->id] ??= ['id' => $s->id, 'brand' => $s->brand, 'nama' => $s->nama, 'count' => 0];
                $missingTally[$s->id]['count']++;
            }
        }
        $topMissingSku = collect($missingTally)->sortByDesc('count')->take(10)->values();

        return [
            'tier_counts' => $tierCounts,
            'avg_white_space' => $whiteSpaceValues->isNotEmpty() ? round($whiteSpaceValues->avg(), 1) : null,
            'avg_hero_sku' => $heroSkuValues->isNotEmpty() ? round($heroSkuValues->avg(), 1) : null,
            'npd_adopted' => $npdStatuses->filter(fn ($s) => $s === 'adopted')->count(),
            'npd_not_adopted' => $npdStatuses->filter(fn ($s) => $s === 'not-adopted')->count(),
            'npd_active' => $npdStatuses->isNotEmpty(),
            'top_fast_moving' => MitraHealthService::topFastMovingSku($mitraIds, 5),
            'top_missing_sku' => $topMissingSku,
        ];
    }
}
