<?php

namespace App\Http\Controllers;

use App\Models\Mitra;
use App\Models\TrackingPerformance;
use App\Models\User;
use App\Services\TrackingPerformanceImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Tracking Performance (Growth Specialist > Special Reg & Reg) — upload file
 * performa toko per mitra per periode, lalu tabel hasilnya. CTR/CVR/ROAS
 * dihitung dari angka mentah (lihat TrackingPerformance). Kolom Δ GMV/
 * Traffic/CTR/CVR membandingkan tiap periode dengan periode sebelumnya milik
 * mitra yang sama (ikon naik/turun); upload pertama = Baseline. Growth
 * Stagnan/Growth/Turun dan Catatan KAE masih kosong — menunggu aturan KPI.
 */
class TrackingPerformanceController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $base = TrackingPerformance::query()
            ->when($user->role === 'kae', fn ($qr) => $qr->whereHas('mitra', fn ($m) => $m->where('kae_code', $user->kae_code)));

        $weekOptions = (clone $base)->whereNotNull('week')->distinct()->orderBy('week')->pluck('week');
        $tahunOptions = (clone $base)->selectRaw('YEAR(tanggal_selesai) as t')->distinct()->orderByDesc('t')->pluck('t');

        $rows = (clone $base)->with('mitra')
            ->when($request->filled('mitra_id'), fn ($qr) => $qr->where('mitra_id', $request->integer('mitra_id')))
            ->when($request->filled('bulan'), fn ($qr) => $qr->whereMonth('tanggal_selesai', $request->integer('bulan')))
            ->when($request->filled('tahun'), fn ($qr) => $qr->whereYear('tanggal_selesai', $request->integer('tahun')))
            ->when($request->filled('week'), fn ($qr) => $qr->where('week', $request->input('week')))
            ->get()
            ->sort(fn ($a, $b) => [$b->tanggal_selesai, $a->mitra->nama ?? ''] <=> [$a->tanggal_selesai, $b->mitra->nama ?? ''])
            ->values();

        // Pembanding = periode tepat sebelumnya milik mitra yang sama. Diambil
        // dari SELURUH riwayat mitra (bukan hasil filter), jadi filter Week
        // W2 tetap dibandingkan dengan W1.
        $sebelumnyaByRowId = [];
        $terakhir = [];
        $base->clone()->whereIn('mitra_id', $rows->pluck('mitra_id')->unique())
            ->orderBy('tanggal_mulai')->orderBy('id')->get()
            ->each(function ($r) use (&$sebelumnyaByRowId, &$terakhir) {
                $sebelumnyaByRowId[$r->id] = $terakhir[$r->mitra_id] ?? null;
                $terakhir[$r->mitra_id] = $r;
            });

        $rows->each(function ($r) use ($sebelumnyaByRowId) {
            $prev = $sebelumnyaByRowId[$r->id] ?? null;
            $r->deltas = collect(TrackingPerformance::METRIK_DELTA)->mapWithKeys(fn ($m) => [$m => $r->arah($m, $prev)])->all();
            $r->status_growth = $r->statusGrowth($prev);
        });

        // Growth itu nilai turunan (dihitung dari periode sebelumnya), bukan
        // kolom di database — jadi filternya baru bisa diterapkan di sini,
        // sesudah status_growth tiap baris dihitung.
        if ($request->filled('growth')) {
            $rows = $rows->where('status_growth', $request->input('growth'))->values();
        }

        $perPage = 20;
        $page = (int) $request->input('page', 1);
        $rowsPage = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('tracking-performance.index', [
            'rowsPage' => $rowsPage,
            'mitraOptions' => Mitra::query()
                ->when($user->role === 'kae', fn ($qr) => $qr->where('kae_code', $user->kae_code))
                ->orderBy('nama')->get(['id', 'nama', 'kode_mitra'])
                ->map(fn ($m) => (object) ['id' => $m->id, 'label' => $m->nama.' ('.$m->kode_mitra.')']),
            'weekOptions' => $weekOptions,
            'tahunOptions' => $tahunOptions,
            'growthOptions' => TrackingPerformance::STATUS_GROWTH_OPTIONS,
            'kaeMap' => User::kaeNameMap(),
        ]);
    }

    public function upload(Request $request, TrackingPerformanceImportService $importer): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ], [], ['file' => 'File']);

        $result = $importer->import($request->file('file'), $request->user());

        if (! $result['ok']) {
            return back()->withErrors(['file' => implode(' ', $result['errors'])]);
        }

        $jumlahSkip = count($result['skipped']);
        $redirect = redirect()->route('growth-specialist.tracking-performance')
            ->with('status', 'Upload Tracking Performance: '.$result['jumlah_dibuat'].' baris baru, '.$result['jumlah_diperbarui'].' diperbarui, dari '.$result['jumlah_baris'].' baris.'.($jumlahSkip > 0 ? ' '.$jumlahSkip.' baris dilewati.' : ''));

        if ($jumlahSkip > 0) {
            $redirect->with('import_skipped', $result['skipped']);
        }

        return $redirect;
    }

    public function template(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(TrackingPerformanceImportService::SHEET_NAME);

        $headers = ['Kode Mitra', 'Nama Mitra', 'Week', 'Kuartal', 'Tanggal', 'Total Penjualan (IDR)', 'Total Pesanan', 'Produk Diklik', 'Total Pengunjung', 'Ads Spend (IDR)'];
        $sheet->fromArray($headers, null, 'A1', true);
        $sheet->getStyle('A1:J1')->getFont()->setBold(true);
        $sheet->getStyle('A1:J1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EBE5EF');
        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setWidth(22);
        }
        $sheet->getStyle('A2:A500')->getNumberFormat()->setFormatCode('@');
        $sheet->getStyle('E2:E500')->getNumberFormat()->setFormatCode('@');

        $petunjuk = $spreadsheet->createSheet();
        $petunjuk->setTitle('Petunjuk');
        $petunjuk->fromArray([
            ['Petunjuk pengisian sheet "'.TrackingPerformanceImportService::SHEET_NAME.'"'],
            ['- Satu baris = satu mitra untuk satu periode (biasanya satu minggu).'],
            ['- Kode Mitra: kode reseller di Data Mitra (paling akurat). Kalau kosong, dicocokkan lewat Nama Mitra yang persis sama.'],
            ['- Tanggal: 31-08-2026-06-09-2026 (mulai-selesai, hari-bulan-tahun) atau 31-08-2026 untuk satu hari.'],
            ['- Total Penjualan (IDR) = GMV. Angka boleh format Indonesia (869.250 atau 289.750,00).'],
            ['- Ads Spend (IDR): biaya iklan periode itu. Kosongkan/0 kalau tidak pakai iklan (ROAS jadi 0).'],
            ['- Upload ulang mitra + periode yang sama akan memperbarui baris lama, bukan menambah.'],
            ['- CTR = Produk Diklik / Total Pengunjung; CVR = Total Pesanan / Produk Diklik; ROAS = GMV / Ads Spend (dihitung otomatis oleh sistem).'],
        ], null, 'A1', true);
        $petunjuk->getStyle('A1')->getFont()->setBold(true);
        $petunjuk->getColumnDimension('A')->setWidth(120);
        $spreadsheet->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'template_tracking_performance.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function destroy(Request $request, TrackingPerformance $trackingPerformance): RedirectResponse
    {
        $user = $request->user();
        abort_if($user->role === 'kae' && $trackingPerformance->mitra?->kae_code !== $user->kae_code, 403, 'Anda tidak punya akses ke data ini.');

        $trackingPerformance->delete();

        return redirect()->route('growth-specialist.tracking-performance')->with('status', 'Baris performa dihapus.');
    }
}
