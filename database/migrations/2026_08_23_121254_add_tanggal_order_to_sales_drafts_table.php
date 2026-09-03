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
        Schema::table('sales_drafts', function (Blueprint $table) {
            $table->date('tanggal_order')->nullable()->after('mitra_id');
        });

        // Backfill existing rows (created before this column existed) with
        // their creation date, so nothing is left blank.
        DB::table('sales_drafts')->whereNull('tanggal_order')->update([
            'tanggal_order' => DB::raw('DATE(created_at)'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_drafts', function (Blueprint $table) {
            $table->dropColumn('tanggal_order');
        });
    }
};
