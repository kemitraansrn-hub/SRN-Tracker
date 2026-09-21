<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['mitra_id', 'platform'])]
class LmsEnrollment extends Model
{
    public function mitra()
    {
        return $this->belongsTo(Mitra::class);
    }
}
