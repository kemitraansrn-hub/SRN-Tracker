<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_adjustment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mitra_id')->constrained('mitra');
            $table->string('toko');
            $table->string('marketplace');
            $table->string('link_toko')->nullable();

            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');

            $table->string('status_approval')->default('Pending'); // Pending | Approved | Rejected
            $table->foreignId('diajukan_oleh')->constrained('users'); // KAE
            $table->foreignId('disetujui_oleh')->nullable()->constrained('users')->nullOnDelete(); // Head
            $table->text('catatan')->nullable();

            $table->timestamps();

            $table->index(['mitra_id', 'status_approval']);
            $table->index(['tanggal_mulai', 'tanggal_selesai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_adjustment_requests');
    }
};
