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
        Schema::create('ar_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ar_receivable_id')->constrained('ar_receivables')->cascadeOnDelete();
            $table->unsignedInteger('cicilan_ke');
            $table->unsignedBigInteger('jumlah_bayar');
            $table->date('tanggal_bayar');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ar_payments');
    }
};
