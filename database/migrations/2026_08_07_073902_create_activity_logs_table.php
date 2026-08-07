<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('aksi'); // created | updated | import | replace
            $table->string('tabel_terkait');
            $table->unsignedBigInteger('record_id')->nullable();
            $table->json('data_lama')->nullable();
            $table->json('data_baru')->nullable();
            $table->timestamps();

            $table->index(['tabel_terkait', 'record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
