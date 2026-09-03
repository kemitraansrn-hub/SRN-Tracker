<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nama', 'tahun', 'poin_dibutuhkan', 'harga_reward', 'budget_reward', 'status', 'created_by'])]
class RewardCatalog extends Model
{
    public function redemptions()
    {
        return $this->hasMany(PoinRedemption::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
