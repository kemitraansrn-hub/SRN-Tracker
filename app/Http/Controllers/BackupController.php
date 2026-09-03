<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Halaman admin buat trigger backup manual (di luar jadwal Task Scheduler
 * harian) + lihat & download backup yang sudah ada. File backup sendiri
 * dihasilkan oleh app:backup (App\Console\Commands\BackupData).
 */
class BackupController extends Controller
{
    public function index(): View
    {
        return view('pengaturan.backup', [
            'backups' => $this->listBackups(),
        ]);
    }

    public function run(): RedirectResponse
    {
        set_time_limit(0);

        $exitCode = Artisan::call('app:backup');
        $output = Artisan::output();

        if ($exitCode === 0) {
            return redirect()->route('backup.index')->with('status', 'Backup berhasil dibuat.');
        }

        return back()->withErrors(['backup' => 'Backup gagal: '.trim($output)]);
    }

    public function download(string $filename): BinaryFileResponse
    {
        $safeName = basename($filename);

        abort_unless(
            preg_match('/^srn-(db|uploads)-\d{4}-\d{2}-\d{2}_\d{6}\.(sql\.gz|zip)$/', $safeName),
            404
        );

        $path = storage_path('app/backups/'.$safeName);
        abort_unless(is_file($path), 404);

        return response()->download($path);
    }

    private function listBackups(): array
    {
        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            return [];
        }

        $files = collect(glob($dir.DIRECTORY_SEPARATOR.'srn-*'))
            ->map(fn ($f) => [
                'name' => basename($f),
                'size' => filesize($f),
                'modified' => filemtime($f),
                'type' => str_contains(basename($f), 'srn-db-') ? 'Database' : 'File Upload',
            ])
            ->sortByDesc('modified')
            ->values();

        return $files->all();
    }
}
