<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Kuartal kalender standar (Q1 Jan-Mar, Q2 Apr-Jun, Q3 Jul-Sep, Q4 Okt-Des).
 * Satu-satunya tempat logika ini dihitung — dipakai Tracking Performance dan
 * Stand in Line biar konsisten, karena penomoran minggu (W1, W2, dst) di
 * kedua menu itu reset tiap kuartal, bukan tiap bulan. Presisi pakai
 * potongan bulan kalender murni (bukan tanggal persis minggu pertama/
 * terakhir kuartal yang kadang nyebrang 1-4 hari ke bulan tetangga) —
 * keputusan sadar, bukan keterbatasan: user mengonfirmasi presisi bulan
 * kalender cukup dibanding harus bikin tabel acuan minggu per tahun.
 */
class Quarter
{
    public const LABELS = [1 => 'Q1', 2 => 'Q2', 3 => 'Q3', 4 => 'Q4'];

    public static function ofMonth(int $bulan): int
    {
        return (int) ceil($bulan / 3);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    public static function bounds(int $kuartal, int $tahun): array
    {
        $startMonth = ($kuartal - 1) * 3 + 1;
        $start = Carbon::create($tahun, $startMonth, 1)->startOfDay();
        $end = $start->copy()->addMonths(2)->endOfMonth();

        return [$start, $end];
    }

    /** @return array{0: int, 1: Carbon, 2: Carbon} */
    public static function boundsForMonth(int $bulan, int $tahun): array
    {
        $kuartal = self::ofMonth($bulan);
        [$start, $end] = self::bounds($kuartal, $tahun);

        return [$kuartal, $start, $end];
    }
}
