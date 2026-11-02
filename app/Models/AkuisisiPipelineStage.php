<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'goal', 'exit_criteria', 'sort_order'])]
class AkuisisiPipelineStage extends Model
{
    public function leads(): HasMany
    {
        return $this->hasMany(AkuisisiLead::class, 'pipeline_stage_id');
    }
}
