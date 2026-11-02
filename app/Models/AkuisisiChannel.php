<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'sort_order', 'is_active'])]
class AkuisisiChannel extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function leads(): HasMany
    {
        return $this->hasMany(AkuisisiLead::class, 'channel_id');
    }
}
