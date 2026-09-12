<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'mitra_id', 'toko', 'marketplace', 'link_toko', 'tanggal_mulai', 'tanggal_selesai',
    'status_approval', 'diajukan_oleh', 'disetujui_oleh', 'catatan',
])]
class PriceAdjustmentRequest extends Model
{
    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'dilihat_compliance_at' => 'datetime',
        ];
    }

    public function mitra()
    {
        return $this->belongsTo(Mitra::class);
    }

    public function pengaju()
    {
        return $this->belongsTo(User::class, 'diajukan_oleh');
    }

    public function penyetuju()
    {
        return $this->belongsTo(User::class, 'disetujui_oleh');
    }

    public function items()
    {
        return $this->hasMany(PriceAdjustmentItem::class);
    }
}
