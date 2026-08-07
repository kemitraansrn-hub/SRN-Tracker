<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('jenis'); // order_harian | target_bulanan | histori
            $table->date('tanggal_data')->nullable(); // dipakai untuk replace-by-date pada order_harian
            $table->unsignedTinyInteger('bulan')->nullable(); // dipakai untuk target_bulanan
            $table->unsignedSmallInteger('tahun')->nullable();
            $table->string('nama_file');
            $table->foreignId('uploaded_by')->constrained('users');
            $table->unsignedInteger('jumlah_baris')->default(0);
            $table->string('status'); // berhasil | ditimpa | gagal
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index(['jenis', 'tanggal_data']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_batches');
    }
};
