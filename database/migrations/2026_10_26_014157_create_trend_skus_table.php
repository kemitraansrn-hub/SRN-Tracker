<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Produk yang ditandai buat di-track trend penjualannya (menu
        // Sales > Cek Trend SKU) -- ditandai dari tab "Input Trend SKU" di
        // menu Input NPD. tanggal_mulai_campaign = tanggal mulai
        // dicampaignkan di B2C, jadi titik penanda di line chart-nya.
        Schema::create('trend_skus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produk_id')->unique()->constrained('produk')->cascadeOnDelete();
            $table->date('tanggal_mulai_campaign');
            $table->foreignId('ditandai_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trend_skus');
    }
};
