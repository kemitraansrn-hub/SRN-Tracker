<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Monthly Rp target used by the "Run Rate Weekly" Dashboard card — one row
 * per (bulan, tahun) for the company (kae_code = '') and one per KAE.
 * Replaces what used to be a hardcoded company target constant.
 */
#[Fillable(['bulan', 'tahun', 'kae_code', 'target'])]
class RunRateTarget extends Model
{
    public static function companyTarget(int $bulan, int $tahun): ?float
    {
        $v = static::where('bulan', $bulan)->where('tahun', $tahun)->where('kae_code', '')->value('target');

        return $v !== null ? (float) $v : null;
    }

    public static function kaeTarget(int $bulan, int $tahun, ?string $kaeCode): ?float
    {
        if (! $kaeCode) {
            return null;
        }

        $v = static::where('bulan', $bulan)->where('tahun', $tahun)->where('kae_code', $kaeCode)->value('target');

        return $v !== null ? (float) $v : null;
    }

    /**
     * @return array<string, float> kae_code => target, for the given month
     */
    public static function kaeTargetsMap(int $bulan, int $tahun): array
    {
        return static::where('bulan', $bulan)->where('tahun', $tahun)->where('kae_code', '!=', '')
            ->pluck('target', 'kae_code')
            ->map(fn ($v) => (float) $v)
            ->all();
    }
}
