<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cp_cases', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique(); // PC-0001, dst — auto-generate
            $table->date('tanggal_temuan');

            // Mitra resmi (lookup ke database) ATAU manual kalau gak ketemu match.
            $table->foreignId('mitra_id')->nullable()->constrained('mitra')->nullOnDelete();
            $table->string('nama_mitra_manual')->nullable();

            $table->string('nama_toko');
            $table->string('platform'); // Shopee | Tokopedia | TikTok Shop | Lazada | dst (Master Drop Down)
            $table->foreignId('kota_kabupaten_id')->nullable()->constrained('kota_kabupatens')->nullOnDelete();
            $table->string('link_etalase')->nullable();

            $table->string('kode_barcode')->nullable();
            $table->string('produk');
            $table->decimal('harga_sop', 15, 2);
            $table->decimal('harga_pelanggaran', 15, 2);
            // selisih & persentase selisih dihitung on-the-fly (accessor), gak disimpan

            $table->string('status_kasus')->default('Baru Ditemukan'); // Master Drop Down: STATUS KASUS

            $table->date('follow_up_1_tanggal')->nullable();
            $table->boolean('follow_up_1_status')->nullable();
            $table->date('follow_up_2_tanggal')->nullable();
            $table->boolean('follow_up_2_status')->nullable();
            $table->date('follow_up_3_tanggal')->nullable();
            $table->boolean('follow_up_3_status')->nullable();

            $table->string('bukti_temuan')->nullable(); // link bukti (drive, dsb)
            $table->string('bukti_case_close')->nullable();
            $table->date('tanggal_case_close')->nullable();

            $table->boolean('approval_takedown')->default(false);
            $table->string('status_takedown')->nullable();
            $table->boolean('banding')->default(false);

            $table->foreignId('created_by')->constrained('users'); // tim Compliance yang input
            $table->timestamps();

            $table->index('tanggal_temuan');
            $table->index('status_kasus');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cp_cases');
    }
};
