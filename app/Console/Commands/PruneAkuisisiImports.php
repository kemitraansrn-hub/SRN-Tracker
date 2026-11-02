<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Hapus file upload import Akuisisi Database Lead yang masih "pending" (belum
 * dikonfirmasi/dibatalkan) dan sudah lebih dari 24 jam -- biasanya CS yang
 * upload lalu gak lanjut konfirmasi/batal. Diporting dari konsep
 * `imports:prune` di PartnerAcquire CRM. TIDAK didaftarkan ke Laravel
 * Scheduler (proyek ini gak pakai Schedule:: sama sekali, lihat app:backup --
 * cron di-setup langsung manggil artisan command ini, bukan lewat
 * `php artisan schedule:run`) -- minta IT tambah cron harian:
 *   php artisan akuisisi:prune-imports
 */
class PruneAkuisisiImports extends Command
{
    protected $signature = 'akuisisi:prune-imports';

    protected $description = 'Hapus file pending import Akuisisi Database Lead yang lebih dari 24 jam';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        $dir = 'akuisisi-imports/pending';
        $cutoff = now()->subDay()->timestamp;
        $deleted = 0;

        foreach ($disk->files($dir) as $file) {
            if ($disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
                $deleted++;
            }
        }

        $this->info("{$deleted} file pending import dihapus.");

        return self::SUCCESS;
    }
}
