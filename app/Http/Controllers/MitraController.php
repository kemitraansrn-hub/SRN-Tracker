<?php

namespace App\Http\Controllers;

use App\Models\Mitra;
use App\Models\SpecialDeal;
use App\Models\User;
use App\Services\MasterMitraImportService;
use App\Services\StabilitasService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MitraController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $now = now();
        $prevMonthRef = $now->copy()->subMonthNoOverflow();

        $stabilitasByMitra = StabilitasService::bulkForPreviousQuarter();
        $segmenByMitraId = $this->segmenByMitraId($now);

        $query = Mitra::query()
            ->when(! $user->canViewAll(), fn ($q) => $q->where('kae_code', $user->kae_code))
            ->when($request->filled('q'), fn ($q) => $q->where(function ($qq) use ($request) {
                $qq->where('nama', 'like', '%'.$request->input('q').'%')
                    ->orWhere('kode_mitra', 'like', '%'.$request->input('q').'%');
            }))
            ->when($user->canViewAll() && $request->filled('kae_code'), fn ($q) => $q->where('kae_code', $request->input('kae_code')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            // "Pasif" mitra have 0 order rows in the previous quarter, so
            // they never get a row from bulkForPreviousQuarter()'s groupBy
            // at all — absence from the collection IS the Pasif signal.
            ->when($request->input('stabilitas') === 'Pasif', fn ($q) => $q->whereNotIn('id', $stabilitasByMitra->keys()))
            ->when(in_array($request->input('stabilitas'), ['Stabil', 'Naik-turun'], true), fn ($q) => $q->whereIn(
                'id',
                $stabilitasByMitra->filter(fn ($s) => $s['stabilitas'] === $request->input('stabilitas'))->keys()
            ))
            // Segmen mitra ikut sumber yang sama dengan Special Deal
            // Performance — diambil dari menu Special Deal kuartal
            // berjalan, bukan kolom di tabel mitra.
            ->when($request->filled('segmen'), fn ($q) => $q->whereIn(
                'id',
                $segmenByMitraId->filter(fn ($s) => $s === $request->input('segmen'))->keys()
            ))
            ->withSum(['orders as omset_bulan_ini' => function ($q) use ($now) {
                $q->whereYear('tanggal_order', $now->year)->whereMonth('tanggal_order', $now->month);
            }], 'total_transaksi')
            ->withSum(['orders as omset_bulan_lalu' => function ($q) use ($prevMonthRef) {
                $q->whereYear('tanggal_order', $prevMonthRef->year)->whereMonth('tanggal_order', $prevMonthRef->month);
            }], 'total_transaksi')
            ->when($request->boolean('omset_nol'), fn ($q) => $q->havingRaw('(omset_bulan_ini IS NULL OR omset_bulan_ini = 0)'))
            ->orderBy('nama');

        // Pagination manual (bukan ->paginate() bawaan Eloquent) — pola
        // yang sama dengan SegmentasiController, biar konsisten dan gak
        // kena isu ->paginate() + havingRaw() (filter omset_nol di atas)
        // yang total count-nya bisa gak akurat kalau dicampur groupBy/having.
        $allMitra = $query->get();
        $allMitra->each(fn ($m) => $m->segmen = $segmenByMitraId[$m->id] ?? null);

        $perPage = 20;
        $page = (int) $request->input('page', 1);
        $mitraList = new LengthAwarePaginator(
            $allMitra->forPage($page, $perPage)->values(),
            $allMitra->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $quarterRange = StabilitasService::previousQuarterRange();

        return view('mitra.index', [
            'mitraList' => $mitraList,
            'kaeOptions' => $user->canViewAll() ? User::where('role', 'kae')->orderBy('name')->get() : collect(),
            'stabilitasByMitra' => $stabilitasByMitra,
            'segmenOptions' => SpecialDeal::SEGMEN_OPTIONS,
            'blnAktifLabel' => 'Bln Aktif Q'.$quarterRange['kuartal'],
            'lmLabel' => 'LM ('.$prevMonthRef->translatedFormat('M').')',
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $now = now();
        $segmenByMitraId = $this->segmenByMitraId($now);

        $mitraList = Mitra::query()
            ->when(! $user->canViewAll(), fn ($q) => $q->where('kae_code', $user->kae_code))
            ->when($request->filled('q'), fn ($q) => $q->where(function ($qq) use ($request) {
                $qq->where('nama', 'like', '%'.$request->input('q').'%')
                    ->orWhere('kode_mitra', 'like', '%'.$request->input('q').'%');
            }))
            ->when($user->canViewAll() && $request->filled('kae_code'), fn ($q) => $q->where('kae_code', $request->input('kae_code')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('segmen'), fn ($q) => $q->whereIn(
                'id',
                $segmenByMitraId->filter(fn ($s) => $s === $request->input('segmen'))->keys()
            ))
            ->orderBy('nama')
            ->get();

        $kaeNameMap = User::kaeNameMap();
        $headers = ['Kode Mitra', 'Nama', 'No HP', 'No WA', 'KAE', 'Segmen', 'Status', 'Kota', 'Provinsi'];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Data Mitra');
        $sheet->fromArray($headers, null, 'A1', true);
        $sheet->getStyle('A1:I1')->getFont()->setBold(true);
        $sheet->getStyle('A1:I1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EBE5EF');

        $r = 2;
        foreach ($mitraList as $m) {
            $sheet->fromArray([
                $m->kode_mitra,
                $m->nama,
                $m->no_hp,
                $m->no_wa,
                $kaeNameMap[$m->kae_code ?? ''] ?? ($m->kae_code ?? '—'),
                $segmenByMitraId[$m->id] ?? '—',
                $m->status,
                $m->kota,
                $m->provinsi,
            ], null, 'A'.$r, true);
            $r++;
        }

        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'data_mitra_'.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Segmen per mitra buat kuartal berjalan, sumber dari menu Special Deal
     * — konsisten dengan SpecialDealPerformanceService. Kalau ada lebih
     * dari satu Special Deal buat mitra yang sama, yang paling baru
     * diinput yang dipakai.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function segmenByMitraId(\Carbon\Carbon $reference): \Illuminate\Support\Collection
    {
        return DB::table('special_deals')
            ->where('kuartal', (int) ceil($reference->month / 3))
            ->where('tahun', $reference->year)
            ->orderBy('created_at')
            ->pluck('segmen', 'mitra_id');
    }

    public function show(Request $request, Mitra $mitra): View
    {
        $this->authorizeMitra($request, $mitra);

        $now = now();

        $omsetBulanIni = $mitra->orders()
            ->whereYear('tanggal_order', $now->year)->whereMonth('tanggal_order', $now->month)
            ->sum('total_transaksi');

        $jumlahOrderBulanIni = $mitra->orders()
            ->whereYear('tanggal_order', $now->year)->whereMonth('tanggal_order', $now->month)
            ->count();

        $historiOrder = $mitra->orders()->latest('tanggal_order')->limit(50)->get();
        $followupLogs = $mitra->followupLogs()->with('kae')->latest('tanggal_fu')->limit(10)->get();
        $specialDeals = $mitra->specialDeals()->with('kae')->latest()->limit(10)->get();
        $targetBulanIni = $mitra->targetBulanan()->where('bulan', $now->month)->where('tahun', $now->year)->first();
        $stabilitas = StabilitasService::forMitra($mitra->id);

        return view('mitra.show', [
            'mitra' => $mitra,
            'omsetBulanIni' => $omsetBulanIni,
            'jumlahOrderBulanIni' => $jumlahOrderBulanIni,
            'historiOrder' => $historiOrder,
            'followupLogs' => $followupLogs,
            'specialDeals' => $specialDeals,
            'targetBulanIni' => $targetBulanIni,
            'stabilitas' => $stabilitas,
            'periodeLabel' => $now->translatedFormat('F Y'),
        ]);
    }

    public function create(): View
    {
        return view('mitra.form', [
            'mitra' => new Mitra(),
            'kaeOptions' => User::where('role', 'kae')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $mitra = Mitra::create($data);

        return redirect()->route('mitra.show', $mitra)->with('status', 'Mitra baru berhasil ditambahkan.');
    }

    public function showMasterUpload(): View
    {
        return view('mitra.master-upload');
    }

    public function masterUpload(Request $request, MasterMitraImportService $importer): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ], [], ['file' => 'File']);

        $result = $importer->import($request->file('file'));

        if (! $result['ok']) {
            return back()->withErrors(['file' => implode(' ', $result['errors'])]);
        }

        $jumlahSkip = count($result['skipped']);
        $redirect = redirect()->route('mitra.index')
            ->with('status', 'Upload Master Mitra: '.$result['jumlah_diperbarui'].' mitra diperbarui, '.$result['jumlah_dibuat'].' mitra baru dibuat, dari '.$result['jumlah_baris'].' baris.'.($jumlahSkip > 0 ? ' '.$jumlahSkip.' baris dilewati.' : ''));

        if ($jumlahSkip > 0) {
            $redirect->with('import_skipped', $result['skipped']);
        }

        return $redirect;
    }

    public function edit(Mitra $mitra): View
    {
        return view('mitra.form', [
            'mitra' => $mitra,
            'kaeOptions' => User::where('role', 'kae')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Mitra $mitra): RedirectResponse
    {
        $mitra->update($this->validated($request, $mitra));

        return redirect()->route('mitra.show', $mitra)->with('status', 'Data mitra berhasil diperbarui.');
    }

    private function validated(Request $request, ?Mitra $mitra = null): array
    {
        return $request->validate([
            'kode_mitra' => ['required', 'string', 'max:50', 'unique:mitra,kode_mitra'.($mitra ? ','.$mitra->id : '')],
            'nama' => ['required', 'string', 'max:255'],
            'no_hp' => ['nullable', 'string', 'max:30'],
            'no_wa' => ['nullable', 'string', 'max:30'],
            'alamat' => ['nullable', 'string'],
            'provinsi' => ['nullable', 'string', 'max:100'],
            'kota' => ['nullable', 'string', 'max:100'],
            'kecamatan' => ['nullable', 'string', 'max:100'],
            'desa' => ['nullable', 'string', 'max:100'],
            'kodepos' => ['nullable', 'string', 'max:10'],
            'kae_code' => ['nullable', 'string', 'max:5'],
            'status' => ['required', 'in:aktif,nonaktif'],
        ]);
    }

    private function authorizeMitra(Request $request, Mitra $mitra): void
    {
        $user = $request->user();

        if (! $user->canViewAll() && $mitra->kae_code !== $user->kae_code) {
            abort(403, 'Anda tidak punya akses ke mitra ini.');
        }
    }
}
