<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'score', 'sort_order'])]
class AkuisisiInterestLevel extends Model
{
    public function leads(): HasMany
    {
        return $this->hasMany(AkuisisiLead::class, 'interest_level_id');
    }
}
