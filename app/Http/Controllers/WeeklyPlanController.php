<?php

namespace App\Http\Controllers;

use App\Models\Mitra;
use App\Models\TargetBulanan;
use App\Models\WeekPeriod;
use App\Services\AchievementStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WeeklyPlanController extends Controller
{
    private const WEEKS = ['W1', 'W2', 'W3', 'W4'];

    public function index(Request $request): View
    {
        $user = $request->user();
        $now = now();

        $mitraList = Mitra::where('status', 'aktif')
            ->when(! $user->isAdmin(), fn ($q) => $q->where('kae_code', $user->kae_code))
            ->orderBy('nama')
            ->get();

        // Sum omset per mitra per minggu-label across the last 6 months
        // (each month resolved against its own configured week periods) —
        // dipakai buat nyebar target bulanan proporsional per minggu.
        $historyTotals = [];
        for ($i = 1; $i <= 6; $i++) {
            $ref = $now->copy()->subMonthsNoOverflow($i);
            $case = WeekPeriod::sqlCase($ref->month, $ref->year, 'tanggal_order');

            $rows = DB::table('orders')
                ->whereYear('tanggal_order', $ref->year)
                ->whereMonth('tanggal_order', $ref->month)
                ->when(! $user->isAdmin(), fn ($q) => $q->where('kae_code', $user->kae_code))
                ->selectRaw("mitra_id, $case as minggu, SUM(total_transaksi) as total")
                ->groupBy('mitra_id', 'minggu')
                ->get();

            foreach ($rows as $r) {
                if (! $r->minggu) {
                    continue;
                }
                $historyTotals[$r->mitra_id][$r->minggu] = ($historyTotals[$r->mitra_id][$r->minggu] ?? 0) + $r->total;
            }
        }

        // Minggu Andalan: SEMUA minggu (bisa lebih dari satu) di mana mitra
        // beneran belanja bulan LALU saja (bukan gabungan 6 bulan seperti
        // historyTotals di atas). Contoh: Agustus belanja di W1 & W3 ->
        // Minggu Andalan-nya "W1, W3".
        $prevMonthRef = $now->copy()->subMonthNoOverflow();
        $casePrevMonth = WeekPeriod::sqlCase($prevMonthRef->month, $prevMonthRef->year, 'tanggal_order');
        $prevMonthRows = DB::table('orders')
            ->whereYear('tanggal_order', $prevMonthRef->year)
            ->whereMonth('tanggal_order', $prevMonthRef->month)
            ->when(! $user->isAdmin(), fn ($q) => $q->where('kae_code', $user->kae_code))
            ->selectRaw("mitra_id, $casePrevMonth as minggu, SUM(total_transaksi) as total")
            ->groupBy('mitra_id', 'minggu')
            ->get();

        $prevMonthTotals = [];
        foreach ($prevMonthRows as $r) {
            if (! $r->minggu) {
                continue;
            }
            $prevMonthTotals[$r->mitra_id][$r->minggu] = (float) $r->total;
        }

        $caseThisMonth = WeekPeriod::sqlCase($now->month, $now->year, 'tanggal_order');
        $actualRows = DB::table('orders')
            ->whereYear('tanggal_order', $now->year)
            ->whereMonth('tanggal_order', $now->month)
            ->when(! $user->isAdmin(), fn ($q) => $q->where('kae_code', $user->kae_code))
            ->selectRaw("mitra_id, $caseThisMonth as minggu, SUM(total_transaksi) as total")
            ->groupBy('mitra_id', 'minggu')
            ->get();

        $actualByMitra = [];
        foreach ($actualRows as $r) {
            if (! $r->minggu) {
                continue;
            }
            $actualByMitra[$r->mitra_id][$r->minggu] = (float) $r->total;
        }

        $currentWeekLabel = WeekPeriod::resolveWeek($now);
        $weekIndex = fn (?string $w) => $w ? (int) substr($w, 1) : null;

        $targetByMitra = TargetBulanan::where('bulan', $now->month)->where('tahun', $now->year)
            ->when(! $user->isAdmin(), fn ($q) => $q->whereHas('mitra', fn ($qq) => $qq->where('kae_code', $user->kae_code)))
            ->get()
            ->keyBy('mitra_id');

        $plan = $mitraList->map(function ($m) use ($historyTotals, $prevMonthTotals, $actualByMitra, $currentWeekLabel, $weekIndex, $targetByMitra) {
            $hist = $historyTotals[$m->id] ?? [];

            $histPrevMonth = $prevMonthTotals[$m->id] ?? [];
            $mingguAndalan = array_values(array_filter(self::WEEKS, fn ($w) => ($histPrevMonth[$w] ?? 0) > 0));

            $actual = $actualByMitra[$m->id] ?? [];
            $actualPerWeek = [];
            foreach (self::WEEKS as $w) {
                $actualPerWeek[$w] = $actual[$w] ?? 0;
            }

            // Target bulanan disebar proporsional sesuai porsi omset tiap
            // minggu dari histori 6 bulan; kalau belum ada histori, rata 4 minggu.
            $targetBulan = $targetByMitra->get($m->id)?->effectiveTarget() ?? 0.0;
            $totalHist = array_sum($hist);
            $targetPerWeek = [];
            foreach (self::WEEKS as $w) {
                $targetPerWeek[$w] = $totalHist > 0
                    ? round($targetBulan * (($hist[$w] ?? 0) / $totalHist))
                    : round($targetBulan / 4);
            }

            $realisasiBulan = array_sum($actualPerWeek);
            $pctBulan = $targetBulan > 0 ? round($realisasiBulan / $targetBulan * 100, 1) : null;

            $status = 'belum-ada-data';
            if (! empty($mingguAndalan)) {
                $sudahBelanjaDiAndalan = collect($mingguAndalan)->contains(fn ($w) => ($actual[$w] ?? 0) > 0);
                $mingguAndalanTerakhir = collect($mingguAndalan)->max($weekIndex);

                if ($sudahBelanjaDiAndalan) {
                    $status = 'oke';
                } elseif ($currentWeekLabel && in_array($currentWeekLabel, $mingguAndalan, true)) {
                    $status = 'berjalan';
                } elseif ($currentWeekLabel && $weekIndex($currentWeekLabel) > $mingguAndalanTerakhir) {
                    $status = 'terlewat';
                } else {
                    $status = 'menunggu';
                }
            }

            return (object) [
                'mitra' => $m,
                'segmen' => $targetByMitra->get($m->id)?->segmen,
                'minggu_andalan' => $mingguAndalan,
                'actual' => $actualPerWeek,
                'target_per_week' => $targetPerWeek,
                'target_bulan' => $targetBulan,
                'target_row' => $targetByMitra->get($m->id),
                'realisasi_bulan' => $realisasiBulan,
                'pct_bulan' => $pctBulan,
                'status' => $status,
                'status_pencapaian' => AchievementStatus::resolveWeeklyPlan($realisasiBulan, $targetBulan, $pctBulan),
            ];
        });

        $weekTotals = [];
        foreach (self::WEEKS as $w) {
            $target = $plan->sum(fn ($p) => $p->target_per_week[$w]);
            $realisasi = $plan->sum(fn ($p) => $p->actual[$w]);
            $weekTotals[$w] = [
                'target' => $target,
                'realisasi' => $realisasi,
                'pct' => $target > 0 ? round($realisasi / $target * 100, 1) : null,
            ];
        }

        $filteredPlan = $plan
            ->when($request->filled('q'), fn ($c) => $c->filter(
                fn ($p) => str_contains(mb_strtolower($p->mitra->nama), mb_strtolower(trim($request->input('q'))))
            ))
            ->when($request->filled('minggu_andalan'), fn ($c) => $c->filter(
                fn ($p) => $request->input('minggu_andalan') === 'none'
                    ? empty($p->minggu_andalan)
                    : in_array($request->input('minggu_andalan'), $p->minggu_andalan, true)
            ))
            ->when($request->filled('status_pencapaian'), fn ($c) => $c->where('status_pencapaian', $request->input('status_pencapaian')))
            ->when($request->filled('status_minggu'), fn ($c) => $c->where('status', $request->input('status_minggu')))
            ->when($request->filled('segmen'), fn ($c) => $c->where('segmen', $request->input('segmen')))
            ->values();

        // Derived from $plan (already KAE-scoped for non-admins) rather than
        // TargetBulanan::currentMonthSegments(), so a KAE never sees a
        // segmen option with zero mitra in their own list.
        $segmenOptions = $plan->pluck('segmen')->filter()->unique()->sort()->values();

        return view('weekly-plan.index', [
            'plan' => $filteredPlan,
            'weeks' => self::WEEKS,
            'weekTotals' => $weekTotals,
            'currentWeekLabel' => $currentWeekLabel,
            'periodeLabel' => $now->translatedFormat('F Y'),
            'segmenOptions' => $segmenOptions,
        ]);
    }
}
