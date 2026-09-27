<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('one_on_ones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mitra_id')->constrained('mitra')->cascadeOnDelete();
            $table->unsignedTinyInteger('bulan');
            $table->unsignedSmallInteger('tahun');
            $table->string('status_belanja'); // Kurang Belanja | Belum Belanja | Over RO, dibekukan saat 1 on 1 dibuat
            $table->string('status')->default('terjadwal'); // terjadwal | selesai
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['bulan', 'tahun']);
        });

        Schema::create('one_on_one_sesis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('one_on_one_id')->constrained('one_on_ones')->cascadeOnDelete();
            $table->unsignedTinyInteger('urutan'); // 1, 2, 3, ...
            $table->dateTime('jadwal_zoom');
            $table->string('status')->default('terjadwal'); // terjadwal | selesai
            $table->text('problem')->nullable();
            $table->text('solusi')->nullable();
            $table->text('action_plan')->nullable();
            $table->foreignId('filled_by')->nullable()->constrained('users');
            $table->dateTime('filled_at')->nullable();
            $table->timestamps();

            $table->unique(['one_on_one_id', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('one_on_one_sesis');
        Schema::dropIfExists('one_on_ones');
    }
};
