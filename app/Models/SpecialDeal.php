<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'mitra_id', 'kae_user_id', 'deskripsi', 'nilai', 'status',
    'tanggal_mulai', 'tanggal_selesai',
])]
class SpecialDeal extends Model
{
    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
        ];
    }

    public function mitra()
    {
        return $this->belongsTo(Mitra::class);
    }

    public function kae()
    {
        return $this->belongsTo(User::class, 'kae_user_id');
    }
}
