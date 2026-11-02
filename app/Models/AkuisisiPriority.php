<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'min_score', 'max_score', 'interval_days', 'sort_order'])]
class AkuisisiPriority extends Model
{
    public function leads(): HasMany
    {
        return $this->hasMany(AkuisisiLead::class, 'priority_id');
    }
}
