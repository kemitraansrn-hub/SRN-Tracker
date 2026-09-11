<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'cp_case_id', 'tanggal_takedown', 'alasan_takedown', 'jumlah_follow_up',
    'tanggal_banding', 'status_banding', 'sp', 'keputusan_final', 'status_takedown_final',
])]
class CpTakedownBanding extends Model
{
    /** Kalimat baku default — sama persis di semua kasus, sesuai keputusan bisnis saat ini. */
    public const KEPUTUSAN_FINAL_DEFAULT = 'Toko di-take down sementara dari platform hingga banding disetujui';

    protected function casts(): array
    {
        return [
            'tanggal_takedown' => 'date',
            'tanggal_banding' => 'date',
        ];
    }

    public function cpCase()
    {
        return $this->belongsTo(CpCase::class);
    }
}
