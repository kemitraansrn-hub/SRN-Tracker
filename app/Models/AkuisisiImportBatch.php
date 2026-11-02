<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['type', 'file_name', 'total_rows', 'success_rows', 'failed_rows', 'error_report', 'created_by'])]
class AkuisisiImportBatch extends Model
{
    protected function casts(): array
    {
        return ['error_report' => 'array'];
    }

    public function leads(): HasMany
    {
        return $this->hasMany(AkuisisiLead::class, 'import_batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
