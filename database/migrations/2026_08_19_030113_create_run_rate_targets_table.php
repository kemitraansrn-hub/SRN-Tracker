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
        Schema::create('run_rate_targets', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('bulan');
            $table->unsignedSmallInteger('tahun');
            $table->string('kae_code', 5)->default(''); // '' = target perusahaan (all channel), non-null so the unique index below is airtight
            $table->unsignedBigInteger('target');
            $table->timestamps();

            $table->unique(['bulan', 'tahun', 'kae_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('run_rate_targets');
    }
};
