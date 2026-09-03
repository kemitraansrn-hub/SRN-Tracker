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
        Schema::create('poin_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mitra_id')->constrained('mitra');
            $table->unsignedSmallInteger('tahun'); // tahun poin yang dipakai (poin reset tiap tahun)
            $table->foreignId('reward_catalog_id')->nullable()->constrained('reward_catalogs')->nullOnDelete();
            $table->string('nama_reward'); // snapshot, aman walau reward diubah/dihapus belakangan
            $table->unsignedInteger('poin_per_unit');
            $table->unsignedInteger('qty');
            $table->unsignedInteger('poin_terpakai');
            $table->string('status')->default('on-check');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['mitra_id', 'tahun']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('poin_redemptions');
    }
};
