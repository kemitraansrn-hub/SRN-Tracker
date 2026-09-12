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
        Schema::create('price_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_adjustment_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('produk_id')->constrained('produk');
            // Snapshot HET pas pengajuan dibuat — auto-fill dari Master Produk
            // tapi bisa dikoreksi manual di form, gak nyambung balik ke master.
            $table->decimal('harga_het', 15, 2);
            $table->decimal('harga_diskon', 15, 2);
            // 1 SKU = 1 link etalase Shopee-nya sendiri (beda dari link_toko
            // di price_adjustment_requests yang emang link toko secara umum).
            $table->string('link_etalase');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_adjustment_items');
    }
};
