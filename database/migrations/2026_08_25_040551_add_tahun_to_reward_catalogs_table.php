<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reward_catalogs', function (Blueprint $table) {
            $table->unsignedSmallInteger('tahun')->nullable()->after('nama');
        });

        // Reward yang sudah ada sebelum kolom ini ditambahkan (dari input
        // manual "Reward 2026") dianggap milik tahun 2026.
        DB::table('reward_catalogs')->whereNull('tahun')->update(['tahun' => 2026]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reward_catalogs', function (Blueprint $table) {
            $table->dropColumn('tahun');
        });
    }
};
