<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('akuisisi_channel_performances', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('channel_id');
            $table->unsignedTinyInteger('bulan');
            $table->unsignedSmallInteger('tahun');
            $table->unsignedInteger('lead')->default(0);
            $table->unsignedInteger('prospect')->default(0);
            $table->unsignedInteger('closing')->default(0);
            $table->decimal('biaya', 15, 2)->default(0);
            $table->decimal('investasi_mitra', 15, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['channel_id', 'bulan', 'tahun']);
            $table->foreign('channel_id')->references('id')->on('akuisisi_channels')->restrictOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('akuisisi_channel_performances');
    }
};
