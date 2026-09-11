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
            $table->dropColumn(['approval_takedown', 'banding']);
            $table->foreignId('takedown_decided_by')->nullable()->after('status_takedown')->constrained('users')->nullOnDelete();
            $table->timestamp('takedown_decided_at')->nullable()->after('takedown_decided_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cp_cases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('takedown_decided_by');
            $table->dropColumn('takedown_decided_at');
            $table->boolean('approval_takedown')->default(false);
            $table->boolean('banding')->default(false);
        });
    }
};
