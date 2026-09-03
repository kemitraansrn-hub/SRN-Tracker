<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mitra_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mitra_id')->constrained('mitra')->cascadeOnDelete();
            $table->unsignedTinyInteger('bulan');
            $table->unsignedSmallInteger('tahun');
            $table->string('kode_mitra');
            $table->string('nama');
            $table->string('kae_code')->nullable();
            $table->string('segmen');
            $table->decimal('target_bulan', 15, 2);
            $table->decimal('realisasi_bulan', 15, 2);
            $table->decimal('pct', 6, 2);
            $table->string('status'); // kurang | warning
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['bulan', 'tahun', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mitra_snapshots');
    }
};
