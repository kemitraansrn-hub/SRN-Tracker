<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stand_in_line_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mitra_id')->constrained('mitra')->cascadeOnDelete();
            $table->unsignedTinyInteger('bulan');
            $table->unsignedSmallInteger('tahun');
            $table->text('catatan');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->unique(['mitra_id', 'bulan', 'tahun']);
        });

        Schema::create('set_up_lms_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mitra_id')->constrained('mitra')->cascadeOnDelete();
            $table->string('platform');
            $table->text('catatan');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->unique(['mitra_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('set_up_lms_notes');
        Schema::dropIfExists('stand_in_line_notes');
    }
};
