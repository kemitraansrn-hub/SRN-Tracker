<?php

namespace App\Models;

use App\Services\AkuisisiLeadScoring;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Lead calon mitra (menu Akuisisi > Database Lead). Diporting dari app
 * standalone PartnerAcquire CRM (App\Models\Lead di situ) -- logic SAMA
 * PERSIS, cuma namespace/tabel di-prefix `Akuisisi`/`akuisisi_` biar gak
 * nabrak model lain SRN Tracker.
 */
#[Fillable([
    'entry_date', 'name', 'whatsapp', 'city', 'channel_id',
    'business_status_id', 'interest_level_id', 'capital_status_id', 'score', 'priority_id', 'pipeline_stage_id',
    'last_followup_date', 'next_followup_date', 'latest_followup_status_id', 'latest_note', 'no_response_streak',
    'is_legacy', 'legacy_data', 'import_batch_id', 'created_by', 'updated_by',
])]
class AkuisisiLead extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        // Lead baru langsung dijadwalkan di tanggal masuknya agar muncul di To-Do. Data lama tidak.
        // Selama belum pernah di-follow-up, jadwalnya mengikuti tanggal masuk (juga saat tanggal masuk diedit).
        static::saving(function (AkuisisiLead $lead) {
            if ($lead->is_legacy || $lead->last_followup_date) {
                return;
            }

            if ((! $lead->exists && ! $lead->next_followup_date) || ($lead->exists && $lead->isDirty('entry_date'))) {
                $lead->next_followup_date = $lead->entry_date;
            }
        });

        static::saving(function (AkuisisiLead $lead) {
            $scoring = app(AkuisisiLeadScoring::class);

            if ($scoring->shouldRecalculate($lead)) {
                $scoring->apply($lead);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'last_followup_date' => 'date',
            'next_followup_date' => 'date',
            'is_legacy' => 'boolean',
            'legacy_data' => 'array',
        ];
    }

    /** Nama untuk ditampilkan; data lama bisa tanpa nama. */
    protected function displayName(): Attribute
    {
        return Attribute::get(fn () => $this->name ?: '(Tanpa nama)');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(AkuisisiChannel::class, 'channel_id');
    }

    public function businessStatus(): BelongsTo
    {
        return $this->belongsTo(AkuisisiBusinessStatus::class, 'business_status_id');
    }

    public function interestLevel(): BelongsTo
    {
        return $this->belongsTo(AkuisisiInterestLevel::class, 'interest_level_id');
    }

    public function capitalStatus(): BelongsTo
    {
        return $this->belongsTo(AkuisisiCapitalStatus::class, 'capital_status_id');
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(AkuisisiPriority::class, 'priority_id');
    }

    public function pipelineStage(): BelongsTo
    {
        return $this->belongsTo(AkuisisiPipelineStage::class, 'pipeline_stage_id');
    }

    public function latestFollowupStatus(): BelongsTo
    {
        return $this->belongsTo(AkuisisiFollowupStatus::class, 'latest_followup_status_id');
    }

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(AkuisisiImportBatch::class, 'import_batch_id');
    }

    public function followups(): HasMany
    {
        return $this->hasMany(AkuisisiFollowup::class, 'lead_id');
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
