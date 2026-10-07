<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_adjustment_requests', function (Blueprint $table) {
            // Default 'SKU Slow Moving' biar pengajuan lama (semuanya punya
            // SKU) otomatis terklasifikasi benar tanpa perlu backfill manual.
            $table->string('jenis_pengajuan', 30)->default('SKU Slow Moving')->after('marketplace');
        });
    }

    public function down(): void
    {
        Schema::table('price_adjustment_requests', function (Blueprint $table) {
            $table->dropColumn('jenis_pengajuan');
        });
    }
};
