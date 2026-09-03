<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('forecast_ros', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('bulan');
            $table->unsignedSmallInteger('tahun');
            $table->string('segmen');
            $table->foreignId('mitra_id')->nullable()->constrained('mitra')->nullOnDelete();
            $table->date('tanggal_plan_ro');
            $table->unsignedBigInteger('plan_ro');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['bulan', 'tahun', 'segmen']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('forecast_ros');
    }
};
