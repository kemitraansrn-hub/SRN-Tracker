<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mitra', function (Blueprint $table) {
            $table->id();
            $table->string('kode_mitra')->unique(); // RESELLER code, e.g. REB2025080142
            $table->string('nama');
            $table->string('no_hp')->nullable();
            $table->text('alamat')->nullable();
            $table->string('provinsi')->nullable();
            $table->string('kota')->nullable();
            $table->string('kecamatan')->nullable();
            $table->string('desa')->nullable();
            $table->string('kodepos')->nullable();
            $table->string('kae_code', 5)->nullable(); // snapshot ID SALESMAN: B, C, ...
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('aktif'); // aktif | nonaktif
            $table->timestamps();

            $table->index('kae_code');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mitra');
    }
};
