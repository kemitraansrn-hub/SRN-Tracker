<?php

namespace App\Http\Controllers;

use App\Models\AkuisisiImportBatch;
use App\Services\AkuisisiLeadImport\AkuisisiImportFileException;
use App\Services\AkuisisiLeadImport\AkuisisiLeadImporter;
use App\Services\AkuisisiLeadImport\AkuisisiLeadImportValidator;
use App\Services\AkuisisiLeadImport\AkuisisiSpreadsheetReader;
use App\Services\AkuisisiLeadImport\AkuisisiWorkbookBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Import lead (baru ATAU migrasi data lama): upload -> pratinjau (validasi, tanpa menyimpan) ->
 * konfirmasi -> hasil & riwayat. File yang diupload disimpan sementara sampai dikonfirmasi atau
 * dibatalkan, dan divalidasi ulang saat konfirmasi. Diporting dari
 * App\Http\Controllers\ImportController di PartnerAcquire CRM.
 */
class AkuisisiImportController extends Controller
{
    private const PENDING_DIR = 'akuisisi-imports/pending';

    public function __construct(
        private AkuisisiSpreadsheetReader $reader,
        private AkuisisiLeadImportValidator $validator,
        private AkuisisiWorkbookBuilder $workbooks,
    ) {}

    public function index(): View
    {
        return view('akuisisi.imports.index', [
            'batches' => AkuisisiImportBatch::with('creator')->latest('id')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('akuisisi.imports.create');
    }

    public function template(): StreamedResponse
    {
        return $this->download($this->workbooks->template(), 'Template Import Lead.xlsx');
    }

    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:10240'],
        ], [
            'file.required' => 'Pilih file yang akan diimport.',
            'file.mimes' => 'Format file harus .xlsx atau .csv.',
            'file.max' => 'Ukuran file maksimal 10 MB.',
        ]);

        $file = $request->file('file');
        $token = (string) Str::uuid();
        $extension = strtolower($file->getClientOriginalExtension()) ?: 'xlsx';
        $file->storeAs(self::PENDING_DIR, "{$token}.{$extension}", 'local');

        $request->session()->put("akuisisi_lead_import.{$token}", [
            'name' => $file->getClientOriginalName(),
            'extension' => $extension,
        ]);

        return redirect()->route('akuisisi-leads.imports.preview', $token);
    }

    public function preview(Request $request, string $token): View|RedirectResponse
    {
        [$pending, $result] = $this->validatePending($request, $token);
        if (! $pending) {
            return $result;
        }

        return view('akuisisi.imports.preview', ['token' => $token, 'fileName' => $pending['name'], 'result' => $result]);
    }

    public function pendingErrors(Request $request, string $token): StreamedResponse|RedirectResponse
    {
        [$pending, $result] = $this->validatePending($request, $token);
        if (! $pending) {
            return $result;
        }

        return $this->download($this->workbooks->errorReport($result['problems']), 'Baris Bermasalah - '.pathinfo($pending['name'], PATHINFO_FILENAME).'.xlsx');
    }

    public function confirm(Request $request, string $token, AkuisisiLeadImporter $importer): RedirectResponse
    {
        set_time_limit(600);

        // Validasi ulang: data di database bisa berubah sejak pratinjau.
        [$pending, $result] = $this->validatePending($request, $token);
        if (! $pending) {
            return $result;
        }
        if (! $result['valid']) {
            return redirect()->route('akuisisi-leads.imports.preview', $token)->with('error', 'Tidak ada baris yang bisa diimport.');
        }

        $batch = $importer->import($result, $pending['name'], $request->user());
        $this->forget($request, $token, $pending);

        return redirect()->route('akuisisi-leads.imports.show', $batch)
            ->with('status', number_format($batch->success_rows, 0, ',', '.').' lead berhasil diimport.');
    }

    public function cancel(Request $request, string $token): RedirectResponse
    {
        if ($pending = $request->session()->get("akuisisi_lead_import.{$token}")) {
            $this->forget($request, $token, $pending);
        }

        return redirect()->route('akuisisi-leads.imports.create')->with('status', 'Import dibatalkan.');
    }

    public function show(AkuisisiImportBatch $batch): View
    {
        return view('akuisisi.imports.show', ['batch' => $batch->load('creator')]);
    }

    public function errors(AkuisisiImportBatch $batch): StreamedResponse
    {
        abort_unless($batch->error_report, 404);

        return $this->download($this->workbooks->errorReport($batch->error_report), "Laporan Error Import #{$batch->id}.xlsx");
    }

    /**
     * @return array{0: ?array, 1: array|RedirectResponse} [data file sementara, hasil validasi] atau [null, redirect]
     */
    private function validatePending(Request $request, string $token): array
    {
        $pending = $request->session()->get("akuisisi_lead_import.{$token}");
        $path = $pending ? self::PENDING_DIR."/{$token}.{$pending['extension']}" : null;

        if (! $pending || ! Storage::disk('local')->exists($path)) {
            return [null, redirect()->route('akuisisi-leads.imports.create')->with('error', 'File import tidak ditemukan atau sesi sudah berakhir. Silakan upload ulang.')];
        }

        try {
            $sheet = $this->reader->read(Storage::disk('local')->path($path), $pending['extension']);

            return [$pending, $this->validator->validate($sheet)];
        } catch (AkuisisiImportFileException $e) {
            $this->forget($request, $token, $pending);

            return [null, redirect()->route('akuisisi-leads.imports.create')->withErrors(['file' => $e->getMessage()])];
        }
    }

    private function forget(Request $request, string $token, array $pending): void
    {
        Storage::disk('local')->delete(self::PENDING_DIR."/{$token}.{$pending['extension']}");
        $request->session()->forget("akuisisi_lead_import.{$token}");
    }

    private function download(Spreadsheet $book, string $fileName): StreamedResponse
    {
        return response()->streamDownload(
            fn () => (new Xlsx($book))->save('php://output'),
            $fileName,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }
}
