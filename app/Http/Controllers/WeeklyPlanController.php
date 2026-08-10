<?php

namespace App\Http\Controllers;

use App\Models\Mitra;
use App\Models\WeekPeriod;
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
        // (each month resolved against its own configured week periods).
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

        $plan = $mitraList->map(function ($m) use ($historyTotals, $actualByMitra, $currentWeekLabel, $weekIndex) {
            $hist = $historyTotals[$m->id] ?? [];
            $mingguAndalan = null;

            if (! empty($hist)) {
                arsort($hist);
                $mingguAndalan = array_key_first($hist);
            }

            $actual = $actualByMitra[$m->id] ?? [];
            $actualPerWeek = [];
            foreach (self::WEEKS as $w) {
                $actualPerWeek[$w] = $actual[$w] ?? 0;
            }

            $status = 'belum-ada-data';
            if ($mingguAndalan) {
                $actualAtAndalan = $actual[$mingguAndalan] ?? 0;
                if ($actualAtAndalan > 0) {
                    $status = 'oke';
                } elseif ($currentWeekLabel && $weekIndex($currentWeekLabel) > $weekIndex($mingguAndalan)) {
                    $status = 'terlewat';
                } elseif ($currentWeekLabel === $mingguAndalan) {
                    $status = 'berjalan';
                } else {
                    $status = 'menunggu';
                }
            }

            return (object) [
                'mitra' => $m,
                'minggu_andalan' => $mingguAndalan,
                'actual' => $actualPerWeek,
                'status' => $status,
            ];
        });

        return view('weekly-plan.index', [
            'plan' => $plan,
            'weeks' => self::WEEKS,
            'currentWeekLabel' => $currentWeekLabel,
            'periodeLabel' => $now->translatedFormat('F Y'),
        ]);
    }
}
