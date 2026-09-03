<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buyback_requests', function (Blueprint $table) {
            $table->foreignId('approved_by_head_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_by_head_at')->nullable()->after('approved_by_head_id');
            $table->foreignId('approved_by_finance_id')->nullable()->after('approved_by_head_at')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_by_finance_at')->nullable()->after('approved_by_finance_id');
        });
    }

    public function down(): void
    {
        Schema::table('buyback_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by_head_id');
            $table->dropColumn('approved_by_head_at');
            $table->dropConstrainedForeignId('approved_by_finance_id');
            $table->dropColumn('approved_by_finance_at');
        });
    }
};
