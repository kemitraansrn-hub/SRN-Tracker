<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'kode_mitra', 'nama', 'no_hp', 'alamat', 'provinsi', 'kota',
    'kecamatan', 'desa', 'kodepos', 'kae_code', 'user_id', 'status',
])]
class Mitra extends Model
{
    protected $table = 'mitra';

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function targetBulanan()
    {
        return $this->hasMany(TargetBulanan::class);
    }

    public function followupLogs()
    {
        return $this->hasMany(FollowupLog::class);
    }

    public function specialDeals()
    {
        return $this->hasMany(SpecialDeal::class);
    }
}
