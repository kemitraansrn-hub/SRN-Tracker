<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Performa Channel kini pakai daftar channel sendiri (lihat migration
 * create_akuisisi_channel_performance_channels_table), terpisah total dari
 * akuisisi_channels milik Database Lead CRM -- jadi channel_id di sini
 * dialihkan ke tabel baru itu. Belum ada data input manual yang perlu
 * dipertahankan (fitur baru dirilis), jadi tabelnya dikosongkan dulu
 * sebelum kolomnya diganti tipe & FK-nya.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('akuisisi_channel_performances')->truncate();

        Schema::table('akuisisi_channel_performances', function (Blueprint $table) {
            $table->dropForeign(['channel_id']);
        });

        Schema::table('akuisisi_channel_performances', function (Blueprint $table) {
            $table->dropUnique(['channel_id', 'bulan', 'tahun']);
        });

        Schema::table('akuisisi_channel_performances', function (Blueprint $table) {
            $table->dropColumn('channel_id');
        });

        Schema::table('akuisisi_channel_performances', function (Blueprint $table) {
            $table->unsignedBigInteger('channel_id')->after('id');
        });

        Schema::table('akuisisi_channel_performances', function (Blueprint $table) {
            $table->foreign('channel_id')->references('id')->on('akuisisi_channel_performance_channels')->restrictOnDelete()->cascadeOnUpdate();
            $table->unique(['channel_id', 'bulan', 'tahun']);
        });
    }

    public function down(): void
    {
        DB::table('akuisisi_channel_performances')->truncate();

        Schema::table('akuisisi_channel_performances', function (Blueprint $table) {
            $table->dropForeign(['channel_id']);
        });

        Schema::table('akuisisi_channel_performances', function (Blueprint $table) {
            $table->dropUnique(['channel_id', 'bulan', 'tahun']);
        });

        Schema::table('akuisisi_channel_performances', function (Blueprint $table) {
            $table->dropColumn('channel_id');
        });

        Schema::table('akuisisi_channel_performances', function (Blueprint $table) {
            $table->unsignedSmallInteger('channel_id')->after('id');
        });

        Schema::table('akuisisi_channel_performances', function (Blueprint $table) {
            $table->foreign('channel_id')->references('id')->on('akuisisi_channels')->restrictOnDelete()->cascadeOnUpdate();
            $table->unique(['channel_id', 'bulan', 'tahun']);
        });
    }
};
