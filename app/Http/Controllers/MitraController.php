<?php

namespace App\Http\Controllers;

use App\Models\Mitra;
use App\Models\User;
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

        $query = Mitra::query()
            ->when(! $user->isAdmin(), fn ($q) => $q->where('kae_code', $user->kae_code))
            ->when($request->filled('q'), fn ($q) => $q->where(function ($qq) use ($request) {
                $qq->where('nama', 'like', '%'.$request->input('q').'%')
                    ->orWhere('kode_mitra', 'like', '%'.$request->input('q').'%');
            }))
            ->when($user->isAdmin() && $request->filled('kae_code'), fn ($q) => $q->where('kae_code', $request->input('kae_code')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->withCount(['orders as order_bulan_ini_count' => function ($q) use ($now) {
                $q->whereYear('tanggal_order', $now->year)->whereMonth('tanggal_order', $now->month);
            }])
            ->withSum(['orders as omset_bulan_ini' => function ($q) use ($now) {
                $q->whereYear('tanggal_order', $now->year)->whereMonth('tanggal_order', $now->month);
            }], 'total_transaksi')
            ->orderBy('nama');

        return view('mitra.index', [
            'mitraList' => $query->paginate(20)->withQueryString(),
            'kaeOptions' => $user->isAdmin() ? User::where('role', 'kae')->orderBy('name')->get() : collect(),
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

        return view('mitra.show', [
            'mitra' => $mitra,
            'omsetBulanIni' => $omsetBulanIni,
            'jumlahOrderBulanIni' => $jumlahOrderBulanIni,
            'historiOrder' => $historiOrder,
            'followupLogs' => $followupLogs,
            'specialDeals' => $specialDeals,
            'targetBulanIni' => $targetBulanIni,
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

        if (! $user->isAdmin() && $mitra->kae_code !== $user->kae_code) {
            abort(403, 'Anda tidak punya akses ke mitra ini.');
        }
    }
}
