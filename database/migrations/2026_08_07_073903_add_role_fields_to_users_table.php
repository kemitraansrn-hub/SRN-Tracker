<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('kae')->after('email'); // admin | kae
            $table->string('kae_code', 5)->nullable()->after('role'); // B, C, ...
            $table->string('status')->default('aktif')->after('kae_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'kae_code', 'status']);
        });
    }
};
