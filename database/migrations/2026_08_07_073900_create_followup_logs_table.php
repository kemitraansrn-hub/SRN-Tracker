<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('followup_logs', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal_fu');
            $table->string('minggu', 5); // W1-W4
            $table->foreignId('mitra_id')->constrained('mitra');
            $table->foreignId('kae_user_id')->constrained('users');
            $table->string('status_followup')->nullable();
            $table->string('status_belanja')->nullable();
            $table->decimal('nominal_belanja', 15, 2)->nullable();
            $table->string('alasan_kendala')->nullable(); // kategori terstruktur, bukan free-text
            $table->text('catatan')->nullable();
            $table->time('jam_mulai')->nullable();
            $table->time('jam_selesai')->nullable();
            $table->unsignedInteger('total_menit')->nullable();
            $table->timestamps();

            $table->index(['mitra_id', 'minggu']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('followup_logs');
    }
};
