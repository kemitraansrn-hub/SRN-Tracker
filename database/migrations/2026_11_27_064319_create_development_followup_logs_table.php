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
        Schema::create('development_followup_logs', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal_fu');
            $table->string('minggu', 5); // W1-W4
            $table->foreignId('mitra_id')->constrained('mitra');
            $table->foreignId('user_id')->constrained('users');
            $table->string('status_followup'); // FU 1 / FU 2 / FU 3 (pemicu status Red Zone), dihitung otomatis, bukan input form
            $table->string('alasan_kendala')->nullable();
            $table->text('catatan')->nullable();
            $table->time('jam_mulai')->nullable();
            $table->time('jam_selesai')->nullable();
            $table->unsignedInteger('total_menit')->nullable();
            $table->timestamps();

            $table->index('mitra_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('development_followup_logs');
    }
};
