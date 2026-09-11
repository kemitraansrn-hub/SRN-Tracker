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
        Schema::table('produk', function (Blueprint $table) {
            // Harga Eceran Tertinggi — acuan resmi buat "Harga SOP" di Tracking CP,
            // beda dari kolom `harga` (Harga Jual) yang udah ada.
            $table->decimal('harga_het', 15, 2)->nullable()->after('harga');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('produk', function (Blueprint $table) {
            $table->dropColumn('harga_het');
        });
    }
};
