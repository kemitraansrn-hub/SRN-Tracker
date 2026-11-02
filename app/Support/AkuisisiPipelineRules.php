<?php

namespace App\Support;

/**
 * Aturan lunak tahapan pipeline: peringatan (bukan larangan) untuk kombinasi
 * yang tidak lazim. Dipakai form follow-up (dikirim ke JS sebagai JSON) dan
 * penanda "No Respon berulang". Diporting dari App\Support\PipelineRules di
 * PartnerAcquire CRM.
 */
class AkuisisiPipelineRules
{
    /** Jumlah No Respon berturut-turut di tahapan yang sama sebelum lead diberi penanda. */
    public const NO_RESPONSE_LIMIT = 3;

    /**
     * Kombinasi hasil follow-up × tahapan yang perlu dikonfirmasi CS.
     * {stage} diganti nama tahapan oleh JS.
     */
    public static function unusualCombinations(): array
    {
        return [
            [
                'status' => 'INVALID_NUMBER',
                'stages' => ['SKEMA', 'PITCHING'],
                'message' => 'Invalid Number di tahap {stage}? Nomor ini sebelumnya bisa dihubungi.',
            ],
            [
                'status' => 'CLOSING',
                'stages' => ['PROFILING', 'SKEMA'],
                'message' => 'Closing dari tahap {stage}, sebelum Pitching?',
            ],
        ];
    }
}
