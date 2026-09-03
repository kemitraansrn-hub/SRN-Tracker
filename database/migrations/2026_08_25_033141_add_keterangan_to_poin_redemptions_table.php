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
        Schema::table('poin_redemptions', function (Blueprint $table) {
            // 'sesuai-reward' = terima barang reward apa adanya; 'di-uangkan'
            // = reward barang dicairkan jadi cash — di kasus itu $note
            // menyimpan snapshot harga_reward & budget_reward saat itu.
            $table->string('keterangan')->default('sesuai-reward')->after('nama_reward');
            $table->text('note')->nullable()->after('keterangan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('poin_redemptions', function (Blueprint $table) {
            $table->dropColumn(['keterangan', 'note']);
        });
    }
};
