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
        // Ada produk paketan yang gak ada di Master Produk — produk_id jadi
        // opsional, dan nama_produk_manual nampung nama produk yang diketik
        // manual kalau memang gak ketemu di database.
        Schema::table('price_adjustment_items', function (Blueprint $table) {
            $table->dropForeign(['produk_id']);
        });

        Schema::table('price_adjustment_items', function (Blueprint $table) {
            $table->foreignId('produk_id')->nullable()->change();
            $table->string('nama_produk_manual')->nullable()->after('produk_id');
        });

        Schema::table('price_adjustment_items', function (Blueprint $table) {
            $table->foreign('produk_id')->references('id')->on('produk')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('price_adjustment_items', function (Blueprint $table) {
            $table->dropForeign(['produk_id']);
            $table->dropColumn('nama_produk_manual');
        });

        Schema::table('price_adjustment_items', function (Blueprint $table) {
            $table->foreignId('produk_id')->nullable(false)->change();
        });

        Schema::table('price_adjustment_items', function (Blueprint $table) {
            $table->foreign('produk_id')->references('id')->on('produk');
        });
    }
};
