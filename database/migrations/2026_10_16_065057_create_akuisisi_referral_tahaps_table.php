<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Status pembayaran per tahap (1/2/3) -- baris ini cuma dibuat pas
        // admin pertama kali tandai status (lazy), bukan otomatis dibuat
        // 3 baris begitu akuisisi_referrals dibuat. Nominal/bulan/dll
        // tetap dihitung live dari orders tiap kali ditampilkan (ini cuma
        // nyimpen STATUS-nya aja, biar gak basi kalau ada koreksi data
        // Order belakangan).
        Schema::create('akuisisi_referral_tahaps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('akuisisi_referral_id')->constrained('akuisisi_referrals')->cascadeOnDelete();
            $table->unsignedTinyInteger('tahap'); // 1, 2, atau 3
            $table->string('status')->default('belum_bayar'); // belum_bayar | sudah_bayar | dibatalkan
            $table->boolean('konfirmasi_tanpa_retur')->default(false);
            $table->text('catatan')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['akuisisi_referral_id', 'tahap']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('akuisisi_referral_tahaps');
    }
};
