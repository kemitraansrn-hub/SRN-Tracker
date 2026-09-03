<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE produk MODIFY qty_per_poin DECIMAL(8,4) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE produk MODIFY qty_per_poin INT UNSIGNED NULL');
    }
};
