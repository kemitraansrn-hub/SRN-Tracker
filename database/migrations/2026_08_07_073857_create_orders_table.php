<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->nullable()->constrained('import_batches')->nullOnDelete();
            $table->string('no_order')->unique(); // ID TRANSAKSI (core)
            $table->string('no_order_perpack')->nullable(); // ID TRANSAKSI (Perpack)
            $table->date('tanggal_order');
            $table->date('tanggal_konfirmasi')->nullable();
            $table->foreignId('mitra_id')->constrained('mitra');
            $table->decimal('total_transaksi', 15, 2)->default(0);
            $table->decimal('diskon', 15, 2)->default(0);
            $table->decimal('diskon_claim', 15, 2)->nullable();
            $table->decimal('diskon_return', 15, 2)->nullable();
            $table->decimal('biaya_pendaftaran', 15, 2)->default(0);
            $table->decimal('ongkir', 15, 2)->default(0);
            $table->decimal('biaya_penanganan', 15, 2)->nullable();
            $table->decimal('total_transfer', 15, 2)->nullable();
            $table->string('status_pembayaran')->nullable(); // Lunas | Tempo
            $table->string('status')->nullable(); // Konfirmasi | ...
            $table->string('kae_code', 5)->nullable(); // snapshot ID SALESMAN saat order masuk
            $table->string('id_channel')->nullable();
            $table->boolean('is_edited')->default(false);
            $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();

            $table->index('tanggal_order');
            $table->index('mitra_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
