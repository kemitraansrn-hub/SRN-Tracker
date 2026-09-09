<?php

namespace App\Console\Commands;

use App\Models\BuybackRequestItem;
use App\Models\NpdProduct;
use App\Models\OrderItem;
use App\Models\Produk;
use App\Models\SalesDraftItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Gabungkan 2 record produk duplikat jadi 1: semua referensi di
 * order_items, sales_draft_items, buyback_request_items, dan npd_products
 * dipindahkan dari produk duplikat ke produk yang dipertahankan, baru
 * produk duplikatnya dihapus. Dibungkus 1 DB transaction jadi aman kalau
 * ada error di tengah (rollback otomatis, gak ada data setengah pindah).
 */
class MergeProduk extends Command
{
    protected $signature = 'produk:merge {keep : ID produk yang dipertahankan} {duplicate : ID produk yang mau digabung lalu dihapus} {--dry-run : Cuma tampilkan apa yang bakal berubah, tanpa eksekusi} {--force : Skip konfirmasi interaktif}';

    protected $description = 'Gabungkan produk duplikat jadi 1 produk, pindahkan semua referensinya (order, sales draft, buyback, NPD)';

    public function handle(): int
    {
        $keepId = (int) $this->argument('keep');
        $dupId = (int) $this->argument('duplicate');

        if ($keepId === $dupId) {
            $this->error('ID produk yang dipertahankan dan yang mau digabung tidak boleh sama.');

            return self::FAILURE;
        }

        $keep = Produk::find($keepId);
        $dup = Produk::find($dupId);

        if (! $keep || ! $dup) {
            $this->error('Salah satu ID produk tidak ditemukan. keep='.$keepId.' duplicate='.$dupId);

            return self::FAILURE;
        }

        $this->info('Produk yang DIPERTAHANKAN:');
        $this->line("  #{$keep->id} | {$keep->kode_sku} | {$keep->nama} | {$keep->brand}");
        $this->info('Produk yang akan DIGABUNG lalu DIHAPUS:');
        $this->line("  #{$dup->id} | {$dup->kode_sku} | {$dup->nama} | {$dup->brand}");

        if ($keep->brand !== $dup->brand) {
            $this->warn("Perhatian: brand berbeda ({$keep->brand} vs {$dup->brand}) — pastikan ini memang produk yang sama sebelum lanjut.");
        }

        $counts = [
            'order_items' => OrderItem::where('produk_id', $dupId)->count(),
            'sales_draft_items' => SalesDraftItem::where('produk_id', $dupId)->count(),
            'buyback_request_items' => BuybackRequestItem::where('produk_id', $dupId)->count(),
            'npd_products' => NpdProduct::where('produk_id', $dupId)->count(),
        ];

        $this->newLine();
        $this->info('Referensi yang akan dipindahkan dari produk #'.$dupId.' ke #'.$keepId.':');
        foreach ($counts as $table => $n) {
            $this->line("  {$table}: {$n} baris");
        }

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->info('Dry-run — tidak ada perubahan yang dieksekusi.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Lanjutkan? Produk #{$dupId} akan dihapus permanen setelah referensinya dipindahkan.", false)) {
            $this->warn('Dibatalkan.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($keepId, $dupId) {
            OrderItem::where('produk_id', $dupId)->update(['produk_id' => $keepId]);
            SalesDraftItem::where('produk_id', $dupId)->update(['produk_id' => $keepId]);
            BuybackRequestItem::where('produk_id', $dupId)->update(['produk_id' => $keepId]);

            // npd_products punya unique constraint di produk_id, jadi gak
            // bisa asal di-repoint kalau produk keep udah punya baris NPD
            // sendiri — di kasus itu, baris NPD punya duplikat cukup
            // dihapus (statusnya "pernah ditandai NPD" udah kepegang di
            // produk yang dipertahankan).
            $dupNpd = NpdProduct::where('produk_id', $dupId)->first();
            if ($dupNpd) {
                if (NpdProduct::where('produk_id', $keepId)->exists()) {
                    $dupNpd->delete();
                } else {
                    $dupNpd->update(['produk_id' => $keepId]);
                }
            }

            Produk::destroy($dupId);
        });

        $this->newLine();
        $this->info("Selesai. Produk #{$dupId} sudah digabung ke #{$keepId} dan dihapus.");

        return self::SUCCESS;
    }
}
