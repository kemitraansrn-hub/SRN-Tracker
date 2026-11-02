<?php

namespace App\Models;

use App\Services\AkuisisiFollowupSync;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riwayat follow-up 1 AkuisisiLead. Diporting dari App\Models\Followup
 * di PartnerAcquire CRM -- logic sama persis.
 */
#[Fillable([
    'lead_id', 'followup_date', 'followup_status_id', 'pipeline_stage_id', 'note', 'next_followup_date',
    'is_next_manual', 'priority_id', 'created_by', 'updated_by',
])]
class AkuisisiFollowup extends Model
{
    protected static function booted(): void
    {
        // Status aktif lead selalu mengikuti follow-up terbaru, apa pun cara riwayatnya berubah.
        $sync = fn (AkuisisiFollowup $followup) => app(AkuisisiFollowupSync::class)->sync($followup->lead);

        static::saved($sync);
        static::deleted($sync);
    }

    protected function casts(): array
    {
        return [
            'followup_date' => 'date',
            'next_followup_date' => 'date',
            'is_next_manual' => 'boolean',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(AkuisisiLead::class, 'lead_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(AkuisisiFollowupStatus::class, 'followup_status_id');
    }

    public function pipelineStage(): BelongsTo
    {
        return $this->belongsTo(AkuisisiPipelineStage::class, 'pipeline_stage_id');
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(AkuisisiPriority::class, 'priority_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
