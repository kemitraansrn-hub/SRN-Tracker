<?php

namespace App\Services\AkuisisiLeadImport;

use App\Models\AkuisisiImportBatch;
use App\Models\AkuisisiLead;
use App\Models\User;
use App\Services\AkuisisiLeadScoring;
use Illuminate\Support\Facades\DB;

/**
 * Menyimpan hasil validasi sebagai lead baru dalam satu transaksi. Lewat Eloquent agar jadwal
 * follow-up awal (lead baru) dihitung seperti input form. Diporting dari
 * App\Services\LeadImport\LeadImporter di PartnerAcquire CRM, DITAMBAH: skor & prioritas dihitung
 * eksplisit di sini (bukan diserahkan ke hook model) dari kolom business/interest/capital yang
 * diimport -- supaya baris `is_legacy` (lihat AkuisisiLeadImportValidator) TETAP dapat skor yang
 * benar sekali di awal, karena hook AkuisisiLead sengaja TIDAK menghitung ulang skor buat lead
 * legacy. Buat lead baru (non-legacy) ini cuma dobel hitung dengan hasil yang sama, gak masalah.
 * DITAMBAH lagi (2026-11-02): baris dengan `overwrite_lead_id` (lihat AkuisisiLeadImportValidator,
 * cuma lead legacy tanpa riwayat follow-up asli yang bisa lewat sini) MENIMPA seluruh kolom yang
 * diimport pada lead yang sudah ada itu, bukan bikin baris baru -- replace penuh, bukan patch
 * sebagian (kolom yang dikosongkan di file baru akan ikut kekosongan di lead-nya).
 */
class AkuisisiLeadImporter
{
    public function __construct(private AkuisisiLeadScoring $scoring) {}

    /** @return array{batch: AkuisisiImportBatch, created: int, overwritten: int} */
    public function import(array $result, string $fileName, User $user): array
    {
        return DB::transaction(function () use ($result, $fileName, $user) {
            $batch = AkuisisiImportBatch::create([
                'type' => 'import',
                'file_name' => $fileName,
                'total_rows' => $result['total'],
                'success_rows' => count($result['valid']),
                'failed_rows' => count($result['problems']),
                'error_report' => $result['problems'] ?: null,
                'created_by' => $user->id,
            ]);

            $created = 0;
            $overwritten = 0;

            foreach ($result['valid'] as $row) {
                $attributes = $row['attributes'];
                $score = $this->scoring->score($attributes['business_status_id'], $attributes['interest_level_id'], $attributes['capital_status_id']);
                $attributes['score'] = $score;
                $attributes['priority_id'] = $this->scoring->priorityFor($score)?->id;

                if ($leadId = $row['overwrite_lead_id'] ?? null) {
                    AkuisisiLead::findOrFail($leadId)->fill([
                        ...$attributes,
                        'import_batch_id' => $batch->id,
                        'updated_by' => $user->id,
                    ])->save();
                    $overwritten++;
                } else {
                    AkuisisiLead::create([
                        ...$attributes,
                        'import_batch_id' => $batch->id,
                        'created_by' => $user->id,
                        'updated_by' => $user->id,
                    ]);
                    $created++;
                }
            }

            return ['batch' => $batch, 'created' => $created, 'overwritten' => $overwritten];
        });
    }
}
