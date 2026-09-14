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
        Schema::create('notification_dismissals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Kunci kategori notifikasi, mis. 'produk_baru', 'takedown_approval'
            // — cocokin sama App\Services\NotificationCenter::CATEGORIES.
            $table->string('kategori');
            $table->timestamp('dismissed_at');
            $table->timestamps();

            $table->unique(['user_id', 'kategori']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_dismissals');
    }
};
