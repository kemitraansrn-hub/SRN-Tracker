<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Satu mitra cuma punya satu Pengakuisisi (unique mitra_id) --
        // Program Akuisisi Mitra Offline, tanggal_akuisisi = tanggal
        // registrasi resmi mitra ke program ini (bukan created_at mitra,
        // yang bisa jauh lebih lama kalau mitra di-upload massal). Order
        // sebelum tanggal ini TIDAK dihitung sebagai transaksi hasil
        // akuisisi. Transaksi 1/2/3 + nominal bonus dihitung LIVE dari
        // tabel orders (lihat AkuisisiReferral::hitungTahap()), gak
        // disimpan di sini -- yang disimpan cuma status pembayaran per
        // tahap (lihat akuisisi_referral_tahaps).
        // nama_pengakuisisi SENGAJA free-text, bukan FK ke users -- orang
        // Pengakuisisi adalah pihak di luar sistem (gak punya akun login).
        Schema::create('akuisisi_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mitra_id')->unique()->constrained('mitra')->cascadeOnDelete();
            $table->string('nama_pengakuisisi');
            $table->date('tanggal_akuisisi');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('akuisisi_referrals');
    }
};
