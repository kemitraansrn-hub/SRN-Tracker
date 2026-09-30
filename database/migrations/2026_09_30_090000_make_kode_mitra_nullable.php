<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Upload Target Bulanan sekarang bisa bikin mitra baru cuma dari Nama Mitra
 * (kolom ID dihapus dari template) — kode_mitra-nya belum ada sampai admin
 * isi manual di Data Mitra, jadi kolomnya harus boleh kosong dulu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mitra', function (Blueprint $table) {
            $table->string('kode_mitra')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('mitra', function (Blueprint $table) {
            $table->string('kode_mitra')->nullable(false)->change();
        });
    }
};
