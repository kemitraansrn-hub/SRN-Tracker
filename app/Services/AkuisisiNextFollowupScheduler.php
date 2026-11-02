<?php

namespace App\Services;

use App\Models\AkuisisiFollowupStatus;
use App\Models\AkuisisiPriority;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Jadwal next follow-up otomatis: tanggal follow-up + interval prioritas
 * (Hot H+2, Warm H+7, Cold H+30 -- diatur di tabel akuisisi_priorities).
 * - stops_schedule (Closing, Invalid Number): tidak pernah dijadwalkan, juga tidak manual.
 * - auto_schedule = false (hasil akhir lain): tidak dijadwalkan otomatis, tapi CS boleh isi manual.
 *
 * Diporting dari App\Services\NextFollowupScheduler di PartnerAcquire CRM.
 */
class AkuisisiNextFollowupScheduler
{
    public function suggest(?AkuisisiPriority $priority, AkuisisiFollowupStatus $status, CarbonInterface $followupDate): ?Carbon
    {
        if ($status->stops_schedule || ! $status->auto_schedule || ! $priority) {
            return null;
        }

        return Carbon::parse($followupDate)->startOfDay()->addDays($priority->interval_days);
    }

    /**
     * Tentukan jadwal akhir sebuah follow-up.
     *
     * @return array{next_followup_date: ?Carbon, is_next_manual: bool}
     */
    public function resolve(
        ?AkuisisiPriority $priority,
        AkuisisiFollowupStatus $status,
        CarbonInterface $followupDate,
        bool $auto,
        ?CarbonInterface $manualDate,
    ): array {
        if ($status->stops_schedule) {
            return ['next_followup_date' => null, 'is_next_manual' => false];
        }

        if ($auto && ($priority || ! $status->auto_schedule)) {
            return ['next_followup_date' => $this->suggest($priority, $status, $followupDate), 'is_next_manual' => false];
        }

        return [
            'next_followup_date' => $manualDate ? Carbon::parse($manualDate)->startOfDay() : null,
            'is_next_manual' => $manualDate !== null,
        ];
    }
}
