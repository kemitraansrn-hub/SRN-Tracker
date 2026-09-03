<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('special_deals', function (Blueprint $table) {
            $table->string('segmen')->nullable()->after('mitra_id');
            $table->decimal('target_kuartal', 15, 2)->nullable()->after('deskripsi');
            $table->decimal('budget_persen', 5, 2)->nullable()->after('target_kuartal');
            $table->string('subsidi')->nullable()->after('budget_persen');
        });

        DB::table('special_deals')->whereIn('status', ['diajukan', 'berjalan'])->update(['status' => 'proses']);
        DB::table('special_deals')->where('status', 'selesai')->update(['status' => 'done']);

        Schema::table('special_deals', function (Blueprint $table) {
            $table->dropColumn('nilai');
        });
    }

    public function down(): void
    {
        Schema::table('special_deals', function (Blueprint $table) {
            $table->decimal('nilai', 15, 2)->nullable();
            $table->dropColumn(['segmen', 'target_kuartal', 'budget_persen', 'subsidi']);
        });
    }
};
