<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('target_bulanan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mitra_id')->constrained('mitra');
            $table->unsignedTinyInteger('bulan');
            $table->unsignedSmallInteger('tahun');
            $table->string('segmen'); // PARETO | RTP | REGULER | SPECIAL REGULER | ...
            $table->decimal('komit', 15, 2)->nullable();
            $table->decimal('target', 15, 2);
            $table->decimal('stretch', 15, 2)->nullable();
            $table->decimal('target_mou', 15, 2)->nullable();
            $table->foreignId('import_batch_id')->nullable()->constrained('import_batches')->nullOnDelete();
            $table->timestamps();

            $table->unique(['mitra_id', 'bulan', 'tahun']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('target_bulanan');
    }
};
