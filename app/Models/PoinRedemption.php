<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'mitra_id', 'tahun', 'reward_catalog_id', 'nama_reward', 'keterangan', 'note', 'poin_per_unit', 'qty', 'poin_terpakai',
    'status', 'approved_by', 'approved_at', 'created_by',
])]
class PoinRedemption extends Model
{
    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }

    public function mitra()
    {
        return $this->belongsTo(Mitra::class);
    }

    public function rewardCatalog()
    {
        return $this->belongsTo(RewardCatalog::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function keteranganLabel(): string
    {
        return $this->keterangan === 'di-uangkan' ? 'Di Uangkan' : 'Sesuai dengan Reward';
    }
}
