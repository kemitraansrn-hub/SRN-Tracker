<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('special_deals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mitra_id')->constrained('mitra');
            $table->foreignId('kae_user_id')->constrained('users');
            $table->text('deskripsi');
            $table->decimal('nilai', 15, 2)->nullable();
            $table->string('status')->default('diajukan'); // diajukan | berjalan | selesai | batal
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('special_deals');
    }
};
