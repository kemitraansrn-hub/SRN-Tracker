<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('set_up_lms_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mitra_id')->constrained('mitra')->cascadeOnDelete();
            $table->string('platform', 20);
            $table->string('detail', 50);
            $table->text('isu_kendala');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['mitra_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('set_up_lms_issues');
    }
};
