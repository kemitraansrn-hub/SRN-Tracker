<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['mitra_id', 'bulan', 'tahun', 'flagged_by'])]
class NewMitraFlag extends Model
{
    public function mitra()
    {
        return $this->belongsTo(Mitra::class);
    }
}
