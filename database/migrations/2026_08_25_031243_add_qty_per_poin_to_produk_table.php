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
            // Null/0 = produk ini tidak menghasilkan poin. Contoh: nilai 3
            // berarti tiap 3 pcs terjual (akumulatif YTD) = 1 poin mitra.
            $table->unsignedInteger('qty_per_poin')->nullable()->after('harga');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('produk', function (Blueprint $table) {
            $table->dropColumn('qty_per_poin');
        });
    }
};
