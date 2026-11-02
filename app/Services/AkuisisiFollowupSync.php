<?php

namespace App\Services;

use App\Models\AkuisisiLead;
use Illuminate\Support\Collection;

/**
 * Menyalin follow-up terbaru (tanggal paling akhir, lalu input paling akhir) ke
 * kolom status aktif lead, termasuk tahapan pipeline. Tahapan tidak dikosongkan
 * bila riwayat habis dihapus. Diporting dari App\Services\FollowupSync di
 * PartnerAcquire CRM -- logic sama persis.
 */
class AkuisisiFollowupSync
{
    public function sync(AkuisisiLead $lead): void
    {
        $history = $lead->followups()
            ->with('status:id,code')
            ->orderByDesc('followup_date')
            ->orderByDesc('id')
            ->get(['id', 'lead_id', 'followup_date', 'followup_status_id', 'pipeline_stage_id', 'next_followup_date', 'note']);
        $latest = $history->first();
        $lead->no_response_streak = $this->noResponseStreak($history);

        if ($latest) {
            $lead->fill([
                'last_followup_date' => $latest->followup_date,
                'next_followup_date' => $latest->next_followup_date,
                'latest_followup_status_id' => $latest->followup_status_id,
                'latest_note' => $latest->note,
            ]);

            if ($latest->pipeline_stage_id) {
                $lead->pipeline_stage_id = $latest->pipeline_stage_id;
            }
        } elseif (! $lead->is_legacy) {
            // Riwayat habis dihapus: kembali ke kondisi lead baru (dijadwalkan di tanggal masuk).
            // Data lama tetap memakai nilai migrasinya.
            $lead->fill([
                'last_followup_date' => null,
                'next_followup_date' => $lead->entry_date,
                'latest_followup_status_id' => null,
                'latest_note' => null,
            ]);
        }

        $lead->save();
    }

    /**
     * Jumlah No Respon berturut-turut dari follow-up terbaru, selama masih di tahapan yang sama.
     */
    private function noResponseStreak(Collection $history): int
    {
        $stage = $history->first()?->pipeline_stage_id;
        $streak = 0;

        foreach ($history as $followup) {
            if ($followup->status?->code !== 'NO_RESPON' || $followup->pipeline_stage_id !== $stage) {
                break;
            }
            $streak++;
        }

        return min($streak, 255);
    }
}
