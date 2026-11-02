<?php

namespace App\Services;

use App\Models\AkuisisiBusinessStatus;
use App\Models\AkuisisiCapitalStatus;
use App\Models\AkuisisiInterestLevel;
use App\Models\AkuisisiLead;
use App\Models\AkuisisiPriority;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Skor prioritas = poin status bisnis + poin respon/minat + poin status modal.
 * Komponen yang kosong bernilai 1 poin. Kategori ditentukan dari rentang skor
 * di tabel akuisisi_priorities. Diporting dari App\Services\LeadScoring di
 * PartnerAcquire CRM -- logic sama persis.
 */
class AkuisisiLeadScoring
{
    public const EMPTY_COMPONENT_SCORE = 1;

    /** Kolom lead yang memengaruhi skor. */
    public const SCORE_FIELDS = ['business_status_id', 'interest_level_id', 'capital_status_id'];

    /** Poin per id master, dibaca sekali per request (penting untuk import ribuan baris). */
    private array $componentScores = [];

    private ?Collection $priorities = null;

    public function score(?int $businessStatusId, ?int $interestLevelId, ?int $capitalStatusId): int
    {
        return $this->componentScore(AkuisisiBusinessStatus::class, $businessStatusId)
            + $this->componentScore(AkuisisiInterestLevel::class, $interestLevelId)
            + $this->componentScore(AkuisisiCapitalStatus::class, $capitalStatusId);
    }

    public function priorityFor(int $score): ?AkuisisiPriority
    {
        $this->priorities ??= AkuisisiPriority::all();

        return $this->priorities->first(fn (AkuisisiPriority $priority) => $score >= $priority->min_score && $score <= $priority->max_score);
    }

    /** @param class-string<Model> $model */
    private function componentScore(string $model, ?int $id): int
    {
        $this->componentScores[$model] ??= $model::pluck('score', 'id')->all();

        return $id ? ($this->componentScores[$model][$id] ?? self::EMPTY_COMPONENT_SCORE) : self::EMPTY_COMPONENT_SCORE;
    }

    /**
     * Lead baru dan lead biasa selalu dinilai. Data lama (is_legacy) mempertahankan
     * prioritas aslinya, kecuali komponen skornya diubah oleh CS.
     */
    public function shouldRecalculate(AkuisisiLead $lead): bool
    {
        if ($lead->exists && $lead->isDirty(self::SCORE_FIELDS)) {
            return true;
        }

        return ! $lead->is_legacy && (! $lead->exists || $lead->score === null);
    }

    public function apply(AkuisisiLead $lead): void
    {
        $lead->score = $this->score($lead->business_status_id, $lead->interest_level_id, $lead->capital_status_id);
        $lead->priority_id = $this->priorityFor($lead->score)?->id;
    }
}
