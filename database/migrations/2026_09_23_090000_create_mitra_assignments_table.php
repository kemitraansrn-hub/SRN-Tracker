<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mitra_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mitra_id')->constrained('mitra')->cascadeOnDelete();
            $table->unsignedTinyInteger('bulan');
            $table->unsignedSmallInteger('tahun');
            $table->string('status_bulan'); // label Kurang Belanja / Mendekati / Belum Belanja / Warning, dibekukan saat assignment dibuat
            $table->string('status')->default('terjadwal'); // terjadwal | selesai
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['bulan', 'tahun']);
        });

        Schema::create('mitra_assignment_sesis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mitra_assignment_id')->constrained('mitra_assignments')->cascadeOnDelete();
            $table->unsignedTinyInteger('urutan'); // 1, 2, 3, ...
            $table->dateTime('jadwal_zoom');
            $table->string('status')->default('terjadwal'); // terjadwal | selesai
            $table->text('problem')->nullable();
            $table->text('solusi')->nullable();
            $table->text('action_plan')->nullable();
            $table->foreignId('filled_by')->nullable()->constrained('users');
            $table->dateTime('filled_at')->nullable();
            $table->timestamps();

            $table->unique(['mitra_assignment_id', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mitra_assignment_sesis');
        Schema::dropIfExists('mitra_assignments');
    }
};
