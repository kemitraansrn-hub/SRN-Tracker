<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['mitra_id', 'lms_step_id', 'link_gdrive', 'completed_by'])]
class LmsStepCompletion extends Model
{
    public function step()
    {
        return $this->belongsTo(LmsStep::class, 'lms_step_id');
    }
}
