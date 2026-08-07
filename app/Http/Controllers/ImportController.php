<?php

namespace App\Http\Controllers;

use App\Models\ImportBatch;
use App\Models\Order;
use App\Services\OrderImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class ImportController extends Controller
{
    public function __construct(private readonly OrderImportService $importer) {}

    public function index(): View
    {
        return view('import.index', [
            'history' => ImportBatch::with('uploader')->latest()->limit(15)->get(),
            'pending' => null,
        ]);
    }

    public function store(Request $request): View|RedirectResponse
    {
        // Step 2: user already saw the "data already exists" warning and confirmed.
        if ($request->filled('confirm_token')) {
            return $this->handleConfirmedReplace($request);
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ], [], ['file' => 'File']);

        $file = $request->file('file');
        $parsed = $this->importer->parse($file);

        if (! $parsed['ok']) {
            return back()->withErrors(['file' => implode(' ', $parsed['errors'])]);
        }

        $existing = Order::whereDate('tanggal_order', $parsed['tanggal_data'])->exists();

        if ($existing) {
            $token = (string) \Illuminate\Support\Str::uuid();
            Cache::put('import_pending_'.$token, [
                'parsed' => $parsed,
                'filename' => $file->getClientOriginalName(),
            ], now()->addMinutes(15));

            $lastBatch = ImportBatch::whereDate('tanggal_data', $parsed['tanggal_data'])
                ->where('jenis', 'order_harian')
                ->latest()
                ->with('uploader')
                ->first();

            return view('import.index', [
                'history' => ImportBatch::with('uploader')->latest()->limit(15)->get(),
                'pending' => [
                    'token' => $token,
                    'tanggal_data' => $parsed['tanggal_data'],
                    'jumlah_baris_baru' => $parsed['jumlah_baris'],
                    'last_batch' => $lastBatch,
                ],
            ]);
        }

        $batch = $this->importer->commit($parsed, $request->user(), $file->getClientOriginalName(), replace: false);

        return redirect()->route('import.index')
            ->with('status', 'Import berhasil: '.$batch->jumlah_baris.' baris untuk tanggal '.$batch->tanggal_data->format('d/m/Y').'.');
    }

    private function handleConfirmedReplace(Request $request): RedirectResponse
    {
        $request->validate(['confirm_token' => ['required', 'string']]);

        $cacheKey = 'import_pending_'.$request->input('confirm_token');
        $cached = Cache::get($cacheKey);

        if (! $cached) {
            return redirect()->route('import.index')
                ->withErrors(['file' => 'Sesi konfirmasi import sudah kedaluwarsa. Silakan upload ulang file-nya.']);
        }

        $batch = $this->importer->commit($cached['parsed'], $request->user(), $cached['filename'], replace: true);
        Cache::forget($cacheKey);

        return redirect()->route('import.index')
            ->with('status', 'Data tanggal '.$batch->tanggal_data->format('d/m/Y').' berhasil ditimpa dengan file baru ('.$batch->jumlah_baris.' baris).');
    }
}
