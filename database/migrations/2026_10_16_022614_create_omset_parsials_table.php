<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Satu baris aktif aja (mirip BuybackSetting/GoogleCalendarToken) --
        // angka manual "omset yang belum masuk database karena masih
        // parsial", ditambahkan ke omset real cuma buat tampilan di card
        // Dashboard "MTD vs Bulan Lalu". TIDAK terhubung ke orders/mitra
        // sama sekali, sengaja berdiri sendiri biar gak ngaruh ke
        // perhitungan omset asli di menu lain.
        Schema::create('omset_parsials', function (Blueprint $table) {
            $table->id();
            $table->decimal('jumlah', 15, 2);
            $table->string('keterangan')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('omset_parsials');
    }
};
