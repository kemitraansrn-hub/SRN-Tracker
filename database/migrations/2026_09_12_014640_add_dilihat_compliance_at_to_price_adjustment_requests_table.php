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
        Schema::table('price_adjustment_requests', function (Blueprint $table) {
            // Ditandai begitu Compliance buka halaman Price Adjustment
            // Monitoring — dipakai buat "matikan" badge notifikasi keputusan
            // yang udah dilihat, biar gak nyangkut merah terus.
            $table->timestamp('dilihat_compliance_at')->nullable()->after('disetujui_oleh');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('price_adjustment_requests', function (Blueprint $table) {
            $table->dropColumn('dilihat_compliance_at');
        });
    }
};
