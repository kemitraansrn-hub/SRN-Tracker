<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'mitra_id', 'bulan', 'tahun', 'segmen', 'komit', 'target',
    'stretch', 'tier_dipakai', 'target_mou', 'kategori', 'keterangan', 'import_batch_id',
])]
class TargetBulanan extends Model
{
    protected $table = 'target_bulanan';

    public const TIERS = ['komit', 'target', 'stretch'];

    public function mitra()
    {
        return $this->belongsTo(Mitra::class);
    }

    public function importBatch()
    {
        return $this->belongsTo(ImportBatch::class);
    }

    /**
     * The Rp value that actually counts as "the" target this month,
     * based on which tier (komit/target/stretch) is selected for this mitra.
     */
    public function effectiveTarget(): float
    {
        $tier = in_array($this->tier_dipakai, self::TIERS, true) ? $this->tier_dipakai : 'target';

        return (float) ($this->{$tier} ?? $this->target ?? 0);
    }

    /**
     * Raw SQL CASE expression mirroring effectiveTarget(), for use in
     * SELECT/GROUP BY when target_bulanan is joined in a query builder.
     */
    public static function effectiveTargetSql(string $alias = 'target_bulanan'): string
    {
        return "CASE $alias.tier_dipakai
            WHEN 'komit' THEN $alias.komit
            WHEN 'stretch' THEN $alias.stretch
            ELSE $alias.target
        END";
    }

    /**
     * Distinct segmen values that have target data for the current month,
     * used to build the Segmentasi Mitra sidebar without hardcoding names.
     */
    public static function currentMonthSegments(): \Illuminate\Support\Collection
    {
        $now = now();

        return static::where('bulan', $now->month)
            ->where('tahun', $now->year)
            ->whereNotNull('segmen')
            ->distinct()
            ->orderBy('segmen')
            ->pluck('segmen');
    }
}
