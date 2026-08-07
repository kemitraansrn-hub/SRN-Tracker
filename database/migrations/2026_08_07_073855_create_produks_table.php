<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produk', function (Blueprint $table) {
            $table->id();
            $table->string('kode_sku')->nullable()->unique(); // termasuk kode singkatan (PCH, AG, MC, dst) sampai dipetakan resmi
            $table->string('nama');
            $table->string('brand'); // Amura | Purela | Reglow | B.U.T
            $table->string('kategori')->nullable();
            $table->decimal('harga', 15, 2)->nullable(); // pending price list resmi
            $table->string('status')->default('aktif');
            $table->timestamps();

            $table->index('brand');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produk');
    }
};
