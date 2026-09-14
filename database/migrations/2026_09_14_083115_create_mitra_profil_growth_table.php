<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mitra_profil_growth', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mitra_id')->unique()->constrained('mitra')->cascadeOnDelete();

            // Identitas Mitra
            $table->string('status')->nullable();
            $table->date('tanggal_onboarding')->nullable();

            // Kekuatan Finansial & Operasional
            $table->decimal('modal_bisnis', 15, 2)->nullable();
            $table->decimal('modal_srn', 15, 2)->nullable();
            $table->decimal('cost', 15, 2)->nullable();
            $table->string('tim_sendiri')->nullable();
            $table->json('platform_jualan')->nullable();
            $table->string('jam_aktif')->nullable();

            // Channel Fokus
            $table->string('tipe_channel')->nullable();
            $table->json('channel_fokus_1')->nullable();
            $table->json('channel_fokus_2')->nullable();

            // Klasifikasi Mitra
            $table->unsignedTinyInteger('motivasi')->nullable();
            $table->unsignedTinyInteger('kemampuan')->nullable();
            $table->unsignedTinyInteger('keaktifan')->nullable();
            $table->date('deadline_setup_channel')->nullable();

            // KPI Awal
            $table->unsignedInteger('target_traffic')->nullable();
            $table->unsignedInteger('target_leads')->nullable();

            // Integrasi LMS
            $table->string('lms_status')->nullable();

            // Status (Catatan)
            $table->text('catatan')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mitra_profil_growth');
    }
};
