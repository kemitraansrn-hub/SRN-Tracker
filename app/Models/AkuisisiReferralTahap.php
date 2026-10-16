<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'akuisisi_referral_id', 'tahap', 'status', 'konfirmasi_tanpa_retur',
    'catatan', 'paid_by', 'paid_at',
])]
class AkuisisiReferralTahap extends Model
{
    protected $casts = [
        'konfirmasi_tanpa_retur' => 'boolean',
        'paid_at' => 'datetime',
    ];

    public function akuisisiReferral()
    {
        return $this->belongsTo(AkuisisiReferral::class);
    }

    public function paidBy()
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
