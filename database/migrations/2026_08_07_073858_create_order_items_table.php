<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('produk_id')->nullable()->constrained('produk')->nullOnDelete();
            $table->string('brand')->nullable(); // disimpan mentah juga untuk agregasi cepat
            $table->string('nama_produk_raw'); // nama/kode apa adanya dari file import
            $table->unsignedInteger('qty')->default(0);
            $table->decimal('harga', 15, 2)->nullable(); // rata-rata alokasi per order, bukan harga resmi
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->timestamps();

            $table->index('produk_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
