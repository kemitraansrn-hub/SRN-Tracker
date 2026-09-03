<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['bulan', 'tahun', 'segmen', 'mitra_id', 'tanggal_plan_ro', 'plan_ro', 'created_by'])]
class ForecastRo extends Model
{
    protected function casts(): array
    {
        return [
            'tanggal_plan_ro' => 'date',
        ];
    }

    public function mitra()
    {
        return $this->belongsTo(Mitra::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
