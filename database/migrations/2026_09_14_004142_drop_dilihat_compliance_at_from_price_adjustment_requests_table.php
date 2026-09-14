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
        // Digantikan sistem notifikasi terpadu per-user (notification_dismissals)
        // — badge count sekarang murni live query, "sudah dilihat" dicatat per
        // user di tabel notification_dismissals, bukan 1 timestamp global per baris.
        Schema::table('price_adjustment_requests', function (Blueprint $table) {
            $table->dropColumn('dilihat_compliance_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('price_adjustment_requests', function (Blueprint $table) {
            $table->timestamp('dilihat_compliance_at')->nullable();
        });
    }
};
