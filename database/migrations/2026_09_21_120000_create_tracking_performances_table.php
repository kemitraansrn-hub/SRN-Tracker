<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_performances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mitra_id')->constrained('mitra')->cascadeOnDelete();
            $table->string('week', 30)->nullable();
            $table->string('kuartal', 30)->nullable();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->decimal('gmv', 15, 2)->default(0);
            $table->unsignedInteger('total_pesanan')->default(0);
            $table->unsignedInteger('produk_diklik')->default(0);
            $table->unsignedInteger('total_pengunjung')->default(0);
            $table->decimal('ads_spend', 15, 2)->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['mitra_id', 'tanggal_mulai', 'tanggal_selesai'], 'tracking_perf_mitra_periode_unique');
            $table->index('tanggal_selesai');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_performances');
    }
};
