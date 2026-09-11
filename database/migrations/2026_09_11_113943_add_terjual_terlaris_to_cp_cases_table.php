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
        Schema::table('cp_cases', function (Blueprint $table) {
            $table->unsignedInteger('terjual')->nullable()->after('platform');
            $table->unsignedInteger('terlaris')->nullable()->after('terjual');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cp_cases', function (Blueprint $table) {
            $table->dropColumn(['terjual', 'terlaris']);
        });
    }
};
