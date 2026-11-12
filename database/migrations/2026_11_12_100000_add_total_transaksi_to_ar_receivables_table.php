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
        Schema::table('ar_receivables', function (Blueprint $table) {
            $table->unsignedBigInteger('total_transaksi')->after('order_id');
            $table->date('tanggal_kirim')->after('total_transaksi');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ar_receivables', function (Blueprint $table) {
            $table->dropColumn(['total_transaksi', 'tanggal_kirim']);
        });
    }
};
