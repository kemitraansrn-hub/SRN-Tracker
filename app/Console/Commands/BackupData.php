<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Backup harian: dump database MySQL (gzip) + zip folder file upload
 * (storage/app/public, storage/app/private) ke storage/app/backups, dengan
 * retensi otomatis (hapus backup yang lebih tua dari N hari). Kalau
 * BACKUP_MIRROR_PATH diisi di .env (misal folder yang di-sync OneDrive/
 * Google Drive), kedua file backup ikut dicopy ke sana juga — biar ada
 * salinan di luar mesin ini, bukan cuma di disk yang sama.
 */
class BackupData extends Command
{
    protected $signature = 'app:backup';

    protected $description = 'Backup database (mysqldump) dan file upload ke storage/app/backups, dengan retensi otomatis';

    public function handle(): int
    {
        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $stamp = now()->format('Y-m-d_His');
        $ok = true;

        $ok = $this->backupDatabase($dir, $stamp) && $ok;
        $ok = $this->backupUploads($dir, $stamp) && $ok;

        $this->pruneOld($dir, 'srn-db-*.sql.gz', (int) env('BACKUP_RETENTION_DAYS', 30));
        $this->pruneOld($dir, 'srn-uploads-*.zip', (int) env('BACKUP_RETENTION_DAYS', 30));

        $mirror = env('BACKUP_MIRROR_PATH');
        if ($mirror && is_dir($mirror) && is_writable($mirror)) {
            foreach (glob($dir.DIRECTORY_SEPARATOR.'*'.$stamp.'*') as $f) {
                copy($f, rtrim($mirror, '\\/').DIRECTORY_SEPARATOR.basename($f));
            }
            $this->info('Disalin juga ke mirror: '.$mirror);
        } elseif ($mirror) {
            $this->warn("BACKUP_MIRROR_PATH diset ('{$mirror}') tapi foldernya gak ada / gak bisa ditulis — backup mirror dilewati.");
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function backupDatabase(string $dir, string $stamp): bool
    {
        $conn = config('database.connections.'.config('database.default'));
        $mysqldump = env('MYSQLDUMP_PATH', 'mysqldump');
        $outFile = $dir.DIRECTORY_SEPARATOR."srn-db-{$stamp}.sql.gz";

        $process = new Process([
            $mysqldump,
            '--host='.$conn['host'],
            '--port='.$conn['port'],
            '--user='.$conn['username'],
            '--single-transaction',
            '--routines',
            '--triggers',
            $conn['database'],
        ], null, ['MYSQL_PWD' => $conn['password']]);
        $process->setTimeout(600);

        $gz = gzopen($outFile, 'wb9');
        if (! $gz) {
            $this->error('Gagal membuka file output gzip: '.$outFile);

            return false;
        }

        try {
            $process->mustRun(function ($type, $buffer) use ($gz) {
                if ($type === Process::OUT) {
                    gzwrite($gz, $buffer);
                }
            });
        } catch (\Throwable $e) {
            gzclose($gz);
            @unlink($outFile);
            $this->error('Backup database gagal: '.$e->getMessage());

            return false;
        }

        gzclose($gz);
        $this->info('Database backup: '.$outFile.' ('.$this->humanSize(filesize($outFile)).')');

        return true;
    }

    /**
     * Backup SELURUH isi storage/app (bukan cuma public/private) — folder
     * ini juga dipakai buat nyimpen aset lain di luar dua folder standar
     * itu, misal storage/app/mou-assets (logo buat generate dokumen MOU).
     * Yang di-skip cuma folder backups itu sendiri (biar gak nge-zip diri
     * sendiri berulang-ulang).
     */
    private function backupUploads(string $dir, string $stamp): bool
    {
        $root = storage_path('app');
        $sources = array_filter(
            glob($root.DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR),
            fn ($p) => basename($p) !== 'backups' && count(scandir($p)) > 2
        );

        if (empty($sources)) {
            $this->info('Tidak ada file upload untuk di-backup (folder kosong).');

            return true;
        }

        $outFile = $dir.DIRECTORY_SEPARATOR."srn-uploads-{$stamp}.zip";
        $zip = new ZipArchive;
        if ($zip->open($outFile, ZipArchive::CREATE) !== true) {
            $this->error('Gagal membuat file zip: '.$outFile);

            return false;
        }

        foreach ($sources as $source) {
            $base = basename($source);
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($iterator as $file) {
                $localName = $base.'/'.substr($file->getPathname(), strlen($source) + 1);
                $zip->addFile($file->getPathname(), str_replace('\\', '/', $localName));
            }
        }

        $zip->close();
        $this->info('Uploads backup: '.$outFile.' ('.$this->humanSize(filesize($outFile)).')');

        return true;
    }

    private function pruneOld(string $dir, string $pattern, int $days): void
    {
        if ($days <= 0) {
            return;
        }

        $cutoff = now()->subDays($days)->timestamp;
        foreach (glob($dir.DIRECTORY_SEPARATOR.$pattern) as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
                $this->line('Hapus backup lama: '.basename($file));
            }
        }
    }

    private function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $v = $bytes;
        while ($v >= 1024 && $i < count($units) - 1) {
            $v /= 1024;
            $i++;
        }

        return round($v, 1).' '.$units[$i];
    }
}
