<?php

namespace App\Http\Controllers;

use App\Models\Mitra;
use App\Models\User;
use App\Services\StabilitasService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MitraController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $now = now();
        $prevMonthRef = $now->copy()->subMonthNoOverflow();

        $stabilitasByMitra = StabilitasService::bulkForPreviousQuarter();

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
            ->withSum(['orders as omset_bulan_ini' => function ($q) use ($now) {
                $q->whereYear('tanggal_order', $now->year)->whereMonth('tanggal_order', $now->month);
            }], 'total_transaksi')
            ->withSum(['orders as omset_bulan_lalu' => function ($q) use ($prevMonthRef) {
                $q->whereYear('tanggal_order', $prevMonthRef->year)->whereMonth('tanggal_order', $prevMonthRef->month);
            }], 'total_transaksi')
            ->when($request->boolean('omset_nol'), fn ($q) => $q->havingRaw('(omset_bulan_ini IS NULL OR omset_bulan_ini = 0)'))
            ->orderBy('nama');

        $mitraList = $query->paginate(50)->withQueryString();

        $quarterRange = StabilitasService::previousQuarterRange();

        return view('mitra.index', [
            'mitraList' => $mitraList,
            'kaeOptions' => $user->canViewAll() ? User::where('role', 'kae')->orderBy('name')->get() : collect(),
            'stabilitasByMitra' => $stabilitasByMitra,
            'blnAktifLabel' => 'Bln Aktif Q'.$quarterRange['kuartal'],
            'lmLabel' => 'LM ('.$prevMonthRef->translatedFormat('M').')',
        ]);
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

        $historiOrder = $mitra->orders()->latest('tanggal_order')->limit(20)->get();
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
