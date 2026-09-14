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
        Schema::table('mitra_profil_growth', function (Blueprint $table) {
            $table->string('no_wa')->nullable()->after('status');
            $table->string('domisili_kota')->nullable()->after('no_wa');
            $table->string('pengalaman_jualan')->nullable()->after('jam_aktif');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mitra_profil_growth', function (Blueprint $table) {
            $table->dropColumn(['no_wa', 'domisili_kota', 'pengalaman_jualan']);
        });
    }
};
