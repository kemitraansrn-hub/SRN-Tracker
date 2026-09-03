<?php

namespace App\Services;

use App\Models\WeekPeriod;
use Carbon\Carbon;

/**
 * Shared "pencapaian vs target" status used across Dashboard, Weekly Plan,
 * and Segmentasi Mitra. Kritis is paced against which week (W1-W4) of the
 * month we're currently in: expected pace is week_number x 25%, so being
 * under 25% in W1 is critical but the same % in W1 is still normal if
 * you're actually still in W1. Ach/Over RO are magnitude-only and apply any
 * time in the month regardless of pacing.
 */
class AchievementStatus
{
    /**
     * Current week (1-4) per the admin-configured Periode Mingguan for this
     * month, capped at 4 so a W5 overflow still uses the 100% checkpoint.
     */
    public static function currentWeekIndex(?Carbon $referenceDate = null): int
    {
        $now = $referenceDate ?? now();
        $label = WeekPeriod::resolveWeek($now);
        $index = $label ? (int) substr($label, 1) : 1;

        return max(1, min(4, $index));
    }

    public static function resolve(?float $pct, int $weekIndex): string
    {
        if ($pct === null) {
            return 'belum-ada-target';
        }

        if ($pct > 120) {
            return 'over-ro';
        }

        if ($pct >= 100) {
            return 'ach';
        }

        if ($pct < $weekIndex * 25) {
            return 'kritis';
        }

        return 'on-progress';
    }

    /**
     * Weekly Plan's own "status pencapaian" — flat thresholds against
     * %bulan, no week-of-month pacing (unlike resolve() above). Mirrors the
     * sheet formula: IF(realisasi=0, "Belum Belanja", IF(OR(pct>=100,
     * target=0), pct>120 ? "Over RO" : "Tercapai", IF(pct>=80, "Mendekati",
     * "Kurang Belanja"))).
     */
    public static function resolveWeeklyPlan(float $realisasiBulan, float $targetBulan, ?float $pct): string
    {
        if ($realisasiBulan == 0.0) {
            return 'belum-belanja';
        }

        if ($targetBulan == 0.0) {
            return 'tercapai';
        }

        if ($pct > 120) {
            return 'over-ro';
        }

        if ($pct >= 100) {
            return 'tercapai';
        }

        if ($pct >= 80) {
            return 'mendekati';
        }

        return 'kurang';
    }

    public static function label(string $status): string
    {
        return match ($status) {
            'over-ro' => 'Over RO',
            'ach' => 'Ach',
            'kritis' => 'Kritis',
            'on-progress' => 'On Progress',
            'belum-belanja' => 'Belum Belanja',
            'tercapai' => 'Tercapai',
            'mendekati' => 'Mendekati',
            'kurang' => 'Kurang Belanja',
            default => '—',
        };
    }

    /**
     * Chip color keyword matching the app's .chip-{good|warn|critical}
     * classes, or 'neutral'/'highlight' for the two statuses that don't map
     * to that 3-color set.
     */
    public static function color(string $status): string
    {
        return match ($status) {
            'over-ro' => 'highlight',
            'ach' => 'good',
            'kritis' => 'critical',
            'on-progress' => 'neutral',
            'tercapai' => 'good',
            'mendekati' => 'warn',
            'kurang' => 'critical',
            'belum-belanja' => 'neutral',
            default => 'neutral',
        };
    }
}
