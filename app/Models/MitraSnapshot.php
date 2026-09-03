<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'mitra_id', 'bulan', 'tahun', 'kode_mitra', 'nama', 'kae_code',
    'segmen', 'target_bulan', 'realisasi_bulan', 'pct', 'status', 'created_by',
])]
class MitraSnapshot extends Model
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
