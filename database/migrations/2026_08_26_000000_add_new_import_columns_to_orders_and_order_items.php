<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('qty')->nullable()->after('total_transaksi');
            $table->string('diskon_return_id')->nullable()->after('diskon_return');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('sku_raw')->nullable()->after('nama_produk_raw');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['qty', 'diskon_return_id']);
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('sku_raw');
        });
    }
};
