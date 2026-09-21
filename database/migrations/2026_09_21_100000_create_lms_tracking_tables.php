<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lms_steps', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 20);
            $table->unsignedSmallInteger('urutan');
            $table->string('judul');
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->index(['platform', 'urutan']);
        });

        Schema::create('lms_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mitra_id')->constrained('mitra')->cascadeOnDelete();
            $table->string('platform', 20);
            $table->timestamps();

            $table->unique(['mitra_id', 'platform']);
        });

        Schema::create('lms_step_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mitra_id')->constrained('mitra')->cascadeOnDelete();
            $table->foreignId('lms_step_id')->constrained('lms_steps')->cascadeOnDelete();
            $table->string('link_gdrive', 500);
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['mitra_id', 'lms_step_id']);
        });

        $shopee = [
            'Persiapan Awal Optimasi Shopee',
            'Mindset Seller Sukses Shopee',
            'Membuat Akun Shopee',
            'Membuat Nama Toko Di Shopee',
            'Mulai Membuat Toko Shopee',
            'Tour Singkat Seller Centre',
            'Pengaturan Awal Toko',
            'Optimasi Banner Foto Produk Shopee',
            'Praktek Optimasi Banner & Foto Produk',
            'Upload Produk Pertama Shopee',
            'Optimasi Dekorasi Toko Shopee',
            'Strategi Sukses 10 Juta Pertama',
            'Penjelasan Iklan Shopee',
            'Tutorial Iklan Toko Shopee',
            'Tutorial GMV Max Roas Shopee',
        ];

        DB::table('lms_steps')->insert(array_map(fn ($judul, $i) => [
            'platform' => 'shopee',
            'urutan' => $i + 1,
            'judul' => $judul,
            'aktif' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ], $shopee, array_keys($shopee)));
    }

    public function down(): void
    {
        Schema::dropIfExists('lms_step_completions');
        Schema::dropIfExists('lms_enrollments');
        Schema::dropIfExists('lms_steps');
    }
};
