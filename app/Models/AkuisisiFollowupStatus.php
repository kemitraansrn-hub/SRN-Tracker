<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'stops_schedule', 'auto_schedule', 'is_archived', 'is_closed_deal', 'sort_order'])]
class AkuisisiFollowupStatus extends Model
{
    protected function casts(): array
    {
        return [
            'stops_schedule' => 'boolean',
            'auto_schedule' => 'boolean',
            'is_archived' => 'boolean',
            'is_closed_deal' => 'boolean',
        ];
    }

    public function followups(): HasMany
    {
        return $this->hasMany(AkuisisiFollowup::class, 'followup_status_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(AkuisisiLead::class, 'latest_followup_status_id');
    }
}
