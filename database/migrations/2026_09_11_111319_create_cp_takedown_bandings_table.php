<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dibuat OTOMATIS (bukan input manual) begitu satu cp_cases mencapai
     * approval_takedown = true DAN banding = true — bukan "data pindah"
     * secara harfiah, cuma 1 baris tambahan yang nyimpen detail proses
     * banding-nya. Relasi 1-1 ke cp_cases (unique cp_case_id).
     */
    public function up(): void
    {
        Schema::create('cp_takedown_bandings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cp_case_id')->unique()->constrained('cp_cases')->cascadeOnDelete();

            $table->date('tanggal_takedown')->nullable();
            $table->text('alasan_takedown')->nullable();
            $table->unsignedTinyInteger('jumlah_follow_up')->nullable();

            $table->date('tanggal_banding')->nullable();
            $table->string('status_banding')->nullable(); // Pending | Approved | Rejected
            $table->string('sp')->nullable(); // SP1 | SP2 | SP3 (Surat Peringatan)
            $table->text('keputusan_final')->nullable();
            $table->string('status_takedown_final')->nullable(); // Pending | Approved | Rejected

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cp_takedown_bandings');
    }
};
