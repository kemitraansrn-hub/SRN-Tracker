<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('npd_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('produk_id')->constrained('produk')->cascadeOnDelete();
            $table->date('tanggal_ditandai');
            $table->foreignId('ditandai_oleh')->constrained('users');
            $table->timestamps();

            $table->unique('produk_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('npd_products');
    }
};
