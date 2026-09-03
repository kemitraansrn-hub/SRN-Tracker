<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * "Run Rate Mitra Active" — per KAE, per month, count of distinct mitra who
 * ordered at least once that month (YTD Jan..current month), compared
 * against each KAE's configured monthly active-mitra target.
 */
class RunRateService
{
    private const WORKING_DAYS = 25;

    /**
     * @return array{months: int[], monthLabels: array<int,string>, currentMonth: int, rows: array, total: array}
     */
    public static function mitraActiveTable(int $tahun, int $bulanAkhir): array
    {
        $months = range(1, $bulanAkhir);

        $kaeUsers = User::where('role', 'kae')->whereNotNull('kae_code')->orderBy('name')->get(['name', 'kae_code', 'target_mitra_aktif']);

        $countRows = DB::table('orders')
            ->join('mitra', 'mitra.id', '=', 'orders.mitra_id')
            ->whereNotNull('mitra.kae_code')
            ->whereBetween('orders.tanggal_order', [
                Carbon::create($tahun, 1, 1)->startOfDay(),
                Carbon::create($tahun, $bulanAkhir, 1)->endOfMonth()->endOfDay(),
            ])
            ->selectRaw('mitra.kae_code, MONTH(orders.tanggal_order) as bulan, COUNT(DISTINCT orders.mitra_id) as jml')
            ->groupBy('mitra.kae_code', DB::raw('MONTH(orders.tanggal_order)'))
            ->get()
            ->groupBy('kae_code');

        $rows = [];
        $totalCounts = array_fill_keys($months, 0);

        foreach ($kaeUsers as $u) {
            $byMonth = $countRows->get($u->kae_code, collect())->keyBy('bulan');
            $counts = [];

            foreach ($months as $m) {
                $counts[$m] = (int) ($byMonth->get($m)->jml ?? 0);
                $totalCounts[$m] += $counts[$m];
            }

            $sumYtd = array_sum($counts);
            $target = $u->target_mitra_aktif;
            $vsTarget = ($target && $target > 0)
                ? round($sumYtd / ($target * count($months)) * 100, 2)
                : null;

            $rows[] = [
                'kae_code' => $u->kae_code,
                'nama' => $u->name,
                'counts' => $counts,
                'target' => $target,
                'vs_target' => $vsTarget,
            ];
        }

        $totalTarget = (int) $kaeUsers->sum('target_mitra_aktif');
        $totalVsTarget = $totalTarget > 0
            ? round(array_sum($totalCounts) / ($totalTarget * count($months)) * 100, 2)
            : null;

        $avgDaily = [];
        $growth = [];
        $prev = null;
        foreach ($months as $m) {
            $avgDaily[$m] = round($totalCounts[$m] / self::WORKING_DAYS, 2);
            $growth[$m] = ($prev !== null && $prev > 0) ? round((($totalCounts[$m] - $prev) / $prev) * 100, 2) : null;
            $prev = $totalCounts[$m];
        }

        return [
            'months' => $months,
            'monthLabels' => self::monthLabels(),
            'currentMonth' => $bulanAkhir,
            'rows' => $rows,
            'total' => [
                'counts' => $totalCounts,
                'target' => $totalTarget,
                'vs_target' => $totalVsTarget,
            ],
            'avg_daily' => $avgDaily,
            'growth' => $growth,
        ];
    }

    /**
     * @return array<int,string>
     */
    private static function monthLabels(): array
    {
        return [
            1 => 'Jan', 2 => 'Feb', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
            7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
    }
}
