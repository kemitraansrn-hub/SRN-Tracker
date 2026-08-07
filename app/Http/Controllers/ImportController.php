<?php

namespace App\Http\Controllers;

use App\Models\ImportBatch;
use App\Models\Order;
use App\Services\ImportTemplateService;
use App\Services\OrderImportService;
use App\Services\TargetImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    public function __construct(
        private readonly OrderImportService $orderImporter,
        private readonly TargetImportService $targetImporter,
        private readonly ImportTemplateService $templates,
    ) {}

    public function downloadTemplate(string $jenis): StreamedResponse
    {
        abort_unless(in_array($jenis, ['order_harian', 'target_bulanan'], true), 404);

        $spreadsheet = $jenis === 'target_bulanan'
            ? $this->templates->targetBulanan()
            : $this->templates->orderHarian();

        $filename = $jenis === 'target_bulanan' ? 'template_target_bulanan.xlsx' : 'template_order_harian.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function index(): View
    {
        return view('import.index', [
            'history' => ImportBatch::with('uploader')->latest()->limit(15)->get(),
            'pending' => null,
        ]);
    }

    public function store(Request $request): View|RedirectResponse
    {
        if ($request->filled('confirm_token')) {
            return $this->handleConfirmedReplace($request);
        }

        $jenis = $request->input('jenis', 'order_harian');

        return $jenis === 'target_bulanan'
            ? $this->storeTargetBulanan($request)
            : $this->storeOrderHarian($request);
    }

    private function storeOrderHarian(Request $request): View|RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ], [], ['file' => 'File']);

        $file = $request->file('file');
        $parsed = $this->orderImporter->parse($file);

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

        $batch = $this->orderImporter->commit($parsed, $request->user(), $file->getClientOriginalName(), replace: false);

        return redirect()->route('import.index')
            ->with('status', 'Import berhasil: '.$batch->jumlah_baris.' baris untuk tanggal '.$batch->tanggal_data->format('d/m/Y').'.');
    }

    private function storeTargetBulanan(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
            'bulan' => ['required', 'integer', 'min:1', 'max:12'],
            'tahun' => ['required', 'integer', 'min:2020', 'max:2100'],
        ], [], ['file' => 'File']);

        $file = $request->file('file');
        $result = $this->targetImporter->import(
            $file,
            (int) $request->input('bulan'),
            (int) $request->input('tahun'),
            $request->user(),
            $file->getClientOriginalName(),
        );

        if (! $result['ok']) {
            return back()->withErrors(['file' => implode(' ', $result['errors'])]);
        }

        $periode = \Carbon\Carbon::create((int) $request->input('tahun'), (int) $request->input('bulan'))->translatedFormat('F Y');

        return redirect()->route('import.index')
            ->with('status', 'Import target bulanan berhasil: '.$result['jumlah_baris'].' mitra untuk periode '.$periode.'.');
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

        $batch = $this->orderImporter->commit($cached['parsed'], $request->user(), $cached['filename'], replace: true);
        Cache::forget($cacheKey);

        return redirect()->route('import.index')
            ->with('status', 'Data tanggal '.$batch->tanggal_data->format('d/m/Y').' berhasil ditimpa dengan file baru ('.$batch->jumlah_baris.' baris).');
    }
}
