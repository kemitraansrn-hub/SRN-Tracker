<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Admin-configurable date ranges for W1-W4(-W5) of a given bulan/tahun,
 * so "which week does this date fall into" isn't hardcoded to a fixed
 * day-of-month chunking and can match however the business actually
 * defines its weeks that month.
 */
#[Fillable(['bulan', 'tahun', 'minggu', 'tanggal_mulai', 'tanggal_selesai'])]
class WeekPeriod extends Model
{
    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
        ];
    }

    public static function forMonth(int $bulan, int $tahun): Collection
    {
        return static::where('bulan', $bulan)->where('tahun', $tahun)
            ->orderBy('minggu')->get();
    }

    /**
     * Sensible starting point to pre-fill the settings form with when a
     * month hasn't been configured yet: 7-day chunks, last chunk absorbing
     * whatever's left over.
     */
    public static function defaultsFor(int $bulan, int $tahun): Collection
    {
        $lastDay = Carbon::create($tahun, $bulan, 1)->endOfMonth()->day;
        $ranges = [];
        $starts = [1, 8, 15, 22];

        foreach ($starts as $i => $start) {
            if ($start > $lastDay) {
                break;
            }
            $end = $i === count($starts) - 1 ? $lastDay : min($start + 6, $lastDay);
            $ranges[] = [
                'minggu' => 'W'.($i + 1),
                'tanggal_mulai' => Carbon::create($tahun, $bulan, $start)->toDateString(),
                'tanggal_selesai' => Carbon::create($tahun, $bulan, $end)->toDateString(),
            ];
        }

        return collect($ranges);
    }

    public static function resolveWeek(Carbon $date): ?string
    {
        $period = static::where('bulan', $date->month)->where('tahun', $date->year)
            ->whereDate('tanggal_mulai', '<=', $date->toDateString())
            ->whereDate('tanggal_selesai', '>=', $date->toDateString())
            ->first();

        if ($period) {
            return $period->minggu;
        }

        // No custom config for this month yet: fall back to a plain 7-day chunk.
        return 'W'.min(4, (int) ceil($date->day / 7));
    }

    /**
     * Raw SQL CASE expression mapping $dateColumn to 'W1'..'W5' (or NULL if
     * outside any configured range), for use in SELECT/GROUP BY. Falls back
     * to CEIL(DAY(...)/7) when the month has no custom periods saved.
     */
    public static function sqlCase(int $bulan, int $tahun, string $dateColumn = 'tanggal_order'): string
    {
        $periods = static::forMonth($bulan, $tahun);

        if ($periods->isEmpty()) {
            return "CONCAT('W', LEAST(4, CEIL(DAY($dateColumn) / 7)))";
        }

        $case = 'CASE';
        foreach ($periods as $p) {
            $start = DB::getPdo()->quote($p->tanggal_mulai->toDateString());
            $end = DB::getPdo()->quote($p->tanggal_selesai->toDateString());
            $label = DB::getPdo()->quote($p->minggu);
            $case .= " WHEN DATE($dateColumn) BETWEEN $start AND $end THEN $label";
        }
        $case .= ' ELSE NULL END';

        return $case;
    }
}
