<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('target_bulanan', function (Blueprint $table) {
            $table->string('tier_dipakai')->default('target')->after('stretch'); // komit | target | stretch
        });
    }

    public function down(): void
    {
        Schema::table('target_bulanan', function (Blueprint $table) {
            $table->dropColumn('tier_dipakai');
        });
    }
};
