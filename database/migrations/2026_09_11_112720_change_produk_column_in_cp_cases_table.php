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
            // Produk sekarang lookup ke Master Produk (buat ambil Harga HET
            // otomatis), bukan teks bebas lagi.
            $table->dropColumn('produk');
            $table->foreignId('produk_id')->nullable()->after('kode_barcode')->constrained('produk')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cp_cases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('produk_id');
            $table->string('produk')->after('kode_barcode');
        });
    }
};
