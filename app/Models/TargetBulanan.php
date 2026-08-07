<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'mitra_id', 'bulan', 'tahun', 'segmen', 'komit', 'target',
    'stretch', 'target_mou', 'import_batch_id',
])]
class TargetBulanan extends Model
{
    protected $table = 'target_bulanan';

    public function mitra()
    {
        return $this->belongsTo(Mitra::class);
    }

    public function importBatch()
    {
        return $this->belongsTo(ImportBatch::class);
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
