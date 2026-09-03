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
        Schema::table('buyback_requests', function (Blueprint $table) {
            $table->decimal('grand_nilai_beli', 15, 2)->default(0)->after('tingkat_penyusutan');
            $table->decimal('grand_nilai_penyusutan', 15, 2)->default(0)->after('grand_nilai_beli');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('buyback_requests', function (Blueprint $table) {
            $table->dropColumn(['grand_nilai_beli', 'grand_nilai_penyusutan']);
        });
    }
};
