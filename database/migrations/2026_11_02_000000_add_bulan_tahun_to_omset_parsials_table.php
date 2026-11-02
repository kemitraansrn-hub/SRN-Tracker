<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Omset Parsial sekarang di-lock per bulan -- ganti bulan otomatis
        // mulai dari 0 (gak ada baris buat bulan baru itu), tapi baris bulan
        // lama tetap ada (gak kehapus/ketimpa) buat histori. Baris lama (pola
        // single-row "latest wins") di-backfill ke bulan berjalan saat
        // migration ini dijalankan -- asumsi paling masuk akal karena
        // sebelumnya cuma ada 1 baris aktif yang berarti angka BULAN INI.
        Schema::table('omset_parsials', function (Blueprint $table) {
            $table->unsignedTinyInteger('bulan')->nullable()->after('id');
            $table->unsignedSmallInteger('tahun')->nullable()->after('bulan');
        });

        DB::table('omset_parsials')->whereNull('bulan')->update([
            'bulan' => now()->month,
            'tahun' => now()->year,
        ]);

        Schema::table('omset_parsials', function (Blueprint $table) {
            $table->unsignedTinyInteger('bulan')->nullable(false)->change();
            $table->unsignedSmallInteger('tahun')->nullable(false)->change();
            $table->unique(['bulan', 'tahun']);
        });
    }

    public function down(): void
    {
        Schema::table('omset_parsials', function (Blueprint $table) {
            $table->dropUnique(['bulan', 'tahun']);
            $table->dropColumn(['bulan', 'tahun']);
        });
    }
};
