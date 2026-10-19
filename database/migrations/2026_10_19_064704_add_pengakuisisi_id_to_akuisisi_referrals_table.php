<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('akuisisi_referrals', function (Blueprint $table) {
            // Nullable & nama_pengakuisisi TETAP dipertahankan (bukan
            // dihapus) -- baris lama yang dibuat sebelum master data ini
            // ada cuma punya nama_pengakuisisi (free-text) tanpa
            // pengakuisisi_id, dan tampilan/export tetap baca
            // nama_pengakuisisi (otomatis disalin dari master pas
            // pengakuisisi_id dipilih, lihat AkuisisiReferralController).
            $table->foreignId('pengakuisisi_id')->nullable()->after('mitra_id')
                ->constrained('pengakuisisis')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('akuisisi_referrals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pengakuisisi_id');
        });
    }
};
