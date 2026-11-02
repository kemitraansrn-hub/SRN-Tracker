<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Master data CRM Database Lead (Akuisisi) -- nilai persis sama kayak
 * app sumber (PartnerAcquire CRM, docs/database/schema.sql). Idempotent
 * (upsert by code) biar aman dijalanin ulang.
 */
class AkuisisiMasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::table('akuisisi_channels')->upsert([
            ['code' => 'ADS', 'name' => 'Ads', 'sort_order' => 1, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'ADS_WA', 'name' => 'Ads WA', 'sort_order' => 2, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'LINK_BIO', 'name' => 'Link Bio', 'sort_order' => 3, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'DM_IG', 'name' => 'DM IG', 'sort_order' => 4, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'WEBINAR', 'name' => 'Webinar', 'sort_order' => 5, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'OFFLINE_EVENT', 'name' => 'Offline Event', 'sort_order' => 6, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'REFERRAL', 'name' => 'Referral', 'sort_order' => 7, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'OTHERS', 'name' => 'Others', 'sort_order' => 8, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ], ['code'], ['name', 'sort_order', 'is_active', 'updated_at']);

        DB::table('akuisisi_business_statuses')->upsert([
            ['code' => 'BEAUTY', 'name' => 'Sudah berbisnis kecantikan', 'score' => 3, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'OTHER', 'name' => 'Sudah berbisnis lain', 'score' => 2, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'NONE', 'name' => 'Belum berbisnis', 'score' => 1, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
        ], ['code'], ['name', 'score', 'sort_order', 'updated_at']);

        DB::table('akuisisi_interest_levels')->upsert([
            ['code' => 'ASKING', 'name' => 'Cuma Tanya-tanya', 'score' => 1, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'PRICELIST', 'name' => 'Tanya Pricelist', 'score' => 2, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'PROPOSAL', 'name' => 'Minta Proposal', 'score' => 3, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
        ], ['code'], ['name', 'score', 'sort_order', 'updated_at']);

        DB::table('akuisisi_capital_statuses')->upsert([
            ['code' => 'READY', 'name' => 'Siap modal', 'score' => 3, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'LT_5JT', 'name' => 'Kurang dari 5 juta', 'score' => 2, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'NONE', 'name' => 'Tidak punya modal', 'score' => 1, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
        ], ['code'], ['name', 'score', 'sort_order', 'updated_at']);

        DB::table('akuisisi_pipeline_stages')->upsert([
            ['code' => 'PROFILING', 'name' => 'Profiling', 'goal' => 'Hubungi lead, gali status bisnis, minat, dan modal.', 'exit_criteria' => 'Lead merespons, status bisnis & modal sudah diketahui, dan ingin tahu skema kemitraan.', 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'SKEMA', 'name' => 'Skema Kemitraan', 'goal' => 'Jelaskan skema & pricelist, jawab pertanyaan lead.', 'exit_criteria' => 'Lead minta proposal, memilih paket, atau membahas budget secara konkret.', 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'PITCHING', 'name' => 'Pitching', 'goal' => 'Kirim proposal dan dorong keputusan.', 'exit_criteria' => 'Selesai: Closing, atau gugur (Not Interested / Tidak Ada Modal).', 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
        ], ['code'], ['name', 'goal', 'exit_criteria', 'sort_order', 'updated_at']);

        // Grup "Masih berjalan" (is_archived=0) dan "Hasil akhir" (is_archived=1)
        DB::table('akuisisi_followup_statuses')->upsert([
            ['code' => 'DISKUSI', 'name' => 'Respon / Diskusi', 'stops_schedule' => false, 'auto_schedule' => true, 'is_archived' => false, 'is_closed_deal' => false, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'NO_RESPON', 'name' => 'No Respon', 'stops_schedule' => false, 'auto_schedule' => true, 'is_archived' => false, 'is_closed_deal' => false, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'POSTPONED', 'name' => 'Postponed', 'stops_schedule' => false, 'auto_schedule' => true, 'is_archived' => false, 'is_closed_deal' => false, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'CLOSING', 'name' => 'Closing', 'stops_schedule' => true, 'auto_schedule' => false, 'is_archived' => true, 'is_closed_deal' => true, 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'NOT_INTERESTED', 'name' => 'Not Interested', 'stops_schedule' => false, 'auto_schedule' => false, 'is_archived' => true, 'is_closed_deal' => false, 'sort_order' => 5, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'NO_CAPITAL', 'name' => 'Tidak Ada Modal', 'stops_schedule' => false, 'auto_schedule' => false, 'is_archived' => true, 'is_closed_deal' => false, 'sort_order' => 6, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'INVALID_NUMBER', 'name' => 'Invalid Number', 'stops_schedule' => true, 'auto_schedule' => false, 'is_archived' => true, 'is_closed_deal' => false, 'sort_order' => 7, 'created_at' => $now, 'updated_at' => $now],
        ], ['code'], ['name', 'stops_schedule', 'auto_schedule', 'is_archived', 'is_closed_deal', 'sort_order', 'updated_at']);

        // Skor = bisnis + respon/minat + modal (masing-masing 1-3), total 3-9
        DB::table('akuisisi_priorities')->upsert([
            ['code' => 'HOT', 'name' => 'Hot Lead', 'min_score' => 8, 'max_score' => 9, 'interval_days' => 2, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'WARM', 'name' => 'Warm Lead', 'min_score' => 5, 'max_score' => 7, 'interval_days' => 7, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'COLD', 'name' => 'Cold Lead', 'min_score' => 3, 'max_score' => 4, 'interval_days' => 30, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
        ], ['code'], ['name', 'min_score', 'max_score', 'interval_days', 'sort_order', 'updated_at']);
    }
}
