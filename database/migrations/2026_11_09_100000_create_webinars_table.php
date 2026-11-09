<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webinars', function (Blueprint $table) {
            $table->id();
            $table->string('nama_webinar', 50);
            $table->date('tanggal_webinar');
            $table->unsignedInteger('jumlah_peserta');
            $table->string('bukti_zoom_url', 500);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('tanggal_webinar');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webinars');
    }
};
