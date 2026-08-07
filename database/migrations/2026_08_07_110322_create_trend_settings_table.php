<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trend_settings', function (Blueprint $table) {
            $table->id();
            $table->string('label')->nullable(); // e.g. "vs Minggu Lalu"
            $table->date('ini_mulai');
            $table->date('ini_selesai');
            $table->date('lalu_mulai');
            $table->date('lalu_selesai');
            $table->foreignId('updated_by')->constrained('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trend_settings');
    }
};
