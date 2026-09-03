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
        Schema::create('reward_catalogs', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->unsignedInteger('poin_dibutuhkan');
            $table->decimal('harga_reward', 15, 2);
            $table->decimal('budget_reward', 15, 2)->nullable(); // informasi/tracking saja, tidak membatasi penukaran
            $table->string('status')->default('aktif');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reward_catalogs');
    }
};
