<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'tanggal_fu', 'minggu', 'mitra_id', 'kae_user_id', 'status_followup',
    'status_belanja', 'nominal_belanja', 'alasan_kendala', 'catatan',
    'jam_mulai', 'jam_selesai', 'total_menit',
])]
class FollowupLog extends Model
{
    protected function casts(): array
    {
        return [
            'tanggal_fu' => 'date',
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
