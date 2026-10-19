<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Master data Pengakuisisi (Program Akuisisi Mitra Offline) -- data
        // kontak + rekening buat pembayaran bonus. Sebelumnya nama_pengakuisisi
        // di akuisisi_referrals cuma free-text, sekarang ditarik ke master
        // table sendiri biar bisa dipilih dari daftar (lihat
        // 2026_10_19_064704_add_pengakuisisi_id_to_akuisisi_referrals_table).
        Schema::create('pengakuisisis', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('bank')->nullable();
            $table->string('no_rekening')->nullable();
            $table->text('alamat')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengakuisisis');
    }
};
