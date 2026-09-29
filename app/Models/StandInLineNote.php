<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['mitra_id', 'bulan', 'tahun', 'catatan', 'created_by'])]
class StandInLineNote extends Model
{
    public function mitra()
    {
        return $this->belongsTo(Mitra::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
