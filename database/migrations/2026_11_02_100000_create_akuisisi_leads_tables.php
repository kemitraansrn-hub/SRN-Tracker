<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Skema CRM Database Lead (Akuisisi) -- diporting dari aplikasi
     * standalone "PartnerAcquire CRM" (lihat docs/database/schema.sql di
     * situ buat DDL sumber). Prefix `akuisisi_` konsisten sama
     * `akuisisi_referrals`/`akuisisi_referral_tahaps` yang sudah ada.
     *
     * TIDAK bawa tabel users/sessions/password_reset_tokens sendiri --
     * created_by/updated_by/created_by (import batch) semuanya FK ke
     * `users` SRN Tracker yang sudah ada (app sumber gak punya role/akun
     * CS terpisah lagi di sini).
     */
    public function up(): void
    {
        Schema::create('akuisisi_channels', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 30)->unique();
            $table->string('name', 50);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('akuisisi_business_statuses', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 30)->unique();
            $table->string('name', 60);
            $table->unsignedTinyInteger('score');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('akuisisi_interest_levels', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 30)->unique();
            $table->string('name', 60);
            $table->unsignedTinyInteger('score');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('akuisisi_capital_statuses', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 30)->unique();
            $table->string('name', 60);
            $table->unsignedTinyInteger('score');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('akuisisi_pipeline_stages', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 30)->unique();
            $table->string('name', 60);
            $table->string('goal')->nullable();
            $table->string('exit_criteria')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('akuisisi_followup_statuses', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 30)->unique();
            $table->string('name', 60);
            $table->boolean('stops_schedule')->default(false);
            $table->boolean('auto_schedule')->default(true);
            $table->boolean('is_archived')->default(false);
            $table->boolean('is_closed_deal')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('akuisisi_priorities', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 10)->unique();
            $table->string('name', 30);
            $table->unsignedTinyInteger('min_score');
            $table->unsignedTinyInteger('max_score');
            $table->unsignedSmallInteger('interval_days');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('akuisisi_import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('file_name');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('success_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->json('error_report')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('akuisisi_leads', function (Blueprint $table) {
            $table->id();
            $table->date('entry_date');
            $table->string('name', 150)->nullable(); // boleh kosong buat data lama (legacy)
            $table->string('whatsapp', 20)->unique();
            $table->string('city', 100)->nullable();
            $table->unsignedSmallInteger('channel_id');
            $table->unsignedSmallInteger('business_status_id')->nullable();
            $table->unsignedSmallInteger('interest_level_id')->nullable();
            $table->unsignedSmallInteger('capital_status_id')->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->unsignedSmallInteger('priority_id')->nullable();
            $table->unsignedSmallInteger('pipeline_stage_id')->nullable();
            $table->date('last_followup_date')->nullable();
            $table->date('next_followup_date')->nullable();
            $table->unsignedSmallInteger('latest_followup_status_id')->nullable();
            $table->text('latest_note')->nullable();
            $table->unsignedTinyInteger('no_response_streak')->default(0);
            $table->boolean('is_legacy')->default(false);
            $table->json('legacy_data')->nullable(); // kolom asli Excel data lama
            $table->unsignedBigInteger('import_batch_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('next_followup_date');
            $table->index('entry_date');
            $table->index('name');
            $table->index('deleted_at');

            $table->foreign('channel_id')->references('id')->on('akuisisi_channels')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('business_status_id')->references('id')->on('akuisisi_business_statuses')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('interest_level_id')->references('id')->on('akuisisi_interest_levels')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('capital_status_id')->references('id')->on('akuisisi_capital_statuses')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('priority_id')->references('id')->on('akuisisi_priorities')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('pipeline_stage_id')->references('id')->on('akuisisi_pipeline_stages')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('latest_followup_status_id')->references('id')->on('akuisisi_followup_statuses')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('import_batch_id')->references('id')->on('akuisisi_import_batches')->nullOnDelete();
        });

        Schema::create('akuisisi_followups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('akuisisi_leads')->cascadeOnDelete();
            $table->date('followup_date');
            $table->unsignedSmallInteger('followup_status_id');
            $table->unsignedSmallInteger('pipeline_stage_id')->nullable(); // tahapan saat follow-up dicatat
            $table->text('note')->nullable();
            $table->date('next_followup_date')->nullable();
            $table->boolean('is_next_manual')->default(false);
            $table->unsignedSmallInteger('priority_id')->nullable(); // snapshot prioritas saat follow-up dicatat
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['lead_id', 'followup_date']);

            $table->foreign('followup_status_id')->references('id')->on('akuisisi_followup_statuses')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('pipeline_stage_id')->references('id')->on('akuisisi_pipeline_stages')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('priority_id')->references('id')->on('akuisisi_priorities')->restrictOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('akuisisi_followups');
        Schema::dropIfExists('akuisisi_leads');
        Schema::dropIfExists('akuisisi_import_batches');
        Schema::dropIfExists('akuisisi_priorities');
        Schema::dropIfExists('akuisisi_followup_statuses');
        Schema::dropIfExists('akuisisi_pipeline_stages');
        Schema::dropIfExists('akuisisi_capital_statuses');
        Schema::dropIfExists('akuisisi_interest_levels');
        Schema::dropIfExists('akuisisi_business_statuses');
        Schema::dropIfExists('akuisisi_channels');
    }
};
