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
        Schema::create('kota_kabupatens', function (Blueprint $table) {
            $table->id();
            $table->string('nama'); // "Kabupaten Aceh Selatan" | "Kota Banda Aceh"
            $table->string('provinsi');
            $table->timestamps();

            $table->index('provinsi');
            $table->index('nama');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kota_kabupatens');
    }
};
