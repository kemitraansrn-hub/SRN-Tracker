<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "Stabilitas" per mitra: how many of the 3 months in the PREVIOUS quarter
 * (relative to $referenceDate) had at least one order.
 *   3 bulan aktif -> Stabil
 *   1-2 bulan aktif -> Naik-turun
 *   0 bulan aktif -> Pasif
 */
class StabilitasService
{
    public static function label(int $blnAktif): string
    {
        return match (true) {
            $blnAktif >= 3 => 'Stabil',
            $blnAktif >= 1 => 'Naik-turun',
            default => 'Pasif',
        };
    }

    /**
     * @return array{bulan: int[], tahun: int, kuartal: int}
     */
    public static function previousQuarterRange(?Carbon $referenceDate = null): array
    {
        $now = $referenceDate ?? now();
        $currentQuarter = intdiv($now->month - 1, 3) + 1;
        $prevQuarter = $currentQuarter - 1;
        $tahun = $now->year;

        if ($prevQuarter === 0) {
            $prevQuarter = 4;
            $tahun--;
        }

        $startMonth = ($prevQuarter - 1) * 3 + 1;

        return [
            'bulan' => [$startMonth, $startMonth + 1, $startMonth + 2],
            'tahun' => $tahun,
            'kuartal' => $prevQuarter,
        ];
    }

    /**
     * Bulk-compute bln_aktif for every mitra with at least one order in the
     * previous quarter. Returns a collection keyed by mitra_id:
     * ['bln_aktif' => int, 'stabilitas' => string].
     */
    public static function bulkForPreviousQuarter(?Carbon $referenceDate = null): Collection
    {
        $range = self::previousQuarterRange($referenceDate);

        $rows = DB::table('orders')
            ->select('mitra_id', DB::raw('COUNT(DISTINCT MONTH(tanggal_order)) as bln_aktif'))
            ->whereYear('tanggal_order', $range['tahun'])
            ->whereIn(DB::raw('MONTH(tanggal_order)'), $range['bulan'])
            ->groupBy('mitra_id')
            ->get();

        return $rows->mapWithKeys(fn ($r) => [
            $r->mitra_id => [
                'bln_aktif' => (int) $r->bln_aktif,
                'stabilitas' => self::label((int) $r->bln_aktif),
            ],
        ]);
    }

    public static function forMitra(int $mitraId, ?Carbon $referenceDate = null): array
    {
        $range = self::previousQuarterRange($referenceDate);

        $blnAktif = DB::table('orders')
            ->where('mitra_id', $mitraId)
            ->whereYear('tanggal_order', $range['tahun'])
            ->whereIn(DB::raw('MONTH(tanggal_order)'), $range['bulan'])
            ->distinct()
            ->count(DB::raw('MONTH(tanggal_order)'));

        return [
            'bln_aktif' => $blnAktif,
            'stabilitas' => self::label($blnAktif),
            'kuartal' => $range['kuartal'],
            'tahun' => $range['tahun'],
        ];
    }
}
