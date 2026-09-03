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
        Schema::table('target_bulanan', function (Blueprint $table) {
            $table->string('kategori')->nullable()->after('tier_dipakai');
            $table->text('keterangan')->nullable()->after('kategori');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('target_bulanan', function (Blueprint $table) {
            $table->dropColumn(['kategori', 'keterangan']);
        });
    }
};
