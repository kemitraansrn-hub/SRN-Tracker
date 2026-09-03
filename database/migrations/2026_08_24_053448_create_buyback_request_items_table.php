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
        Schema::create('buyback_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyback_request_id')->constrained('buyback_requests')->cascadeOnDelete();
            $table->foreignId('produk_id')->nullable()->constrained('produk')->nullOnDelete();
            $table->string('nama_produk');
            $table->unsignedInteger('qty')->default(0);
            $table->decimal('harga', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->date('tanggal_ed');
            $table->unsignedSmallInteger('umur_produk_bulan');
            $table->decimal('nilai_buyback', 15, 2)->default(0);
            $table->timestamps();

            $table->index('produk_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('buyback_request_items');
    }
};
