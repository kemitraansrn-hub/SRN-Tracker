<?php

namespace App\Http\Controllers;

use App\Models\Mitra;
use App\Models\SpecialDeal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SpecialDealController extends Controller
{
    public const STATUSES = ['diajukan', 'berjalan', 'selesai', 'batal'];

    public function index(Request $request): View
    {
        $user = $request->user();

        $query = SpecialDeal::with(['mitra', 'kae'])
            ->when(! $user->isAdmin(), fn ($q) => $q->where('kae_user_id', $user->id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latest();

        return view('special-deal.index', [
            'deals' => $query->paginate(20)->withQueryString(),
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();

        $mitraOptions = Mitra::where('status', 'aktif')
            ->when(! $user->isAdmin(), fn ($q) => $q->where('kae_code', $user->kae_code))
            ->orderBy('nama')
            ->get();

        return view('special-deal.form', [
            'deal' => new SpecialDeal(),
            'mitraOptions' => $mitraOptions,
            'selectedMitraId' => $request->integer('mitra_id') ?: null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $this->validated($request);

        $mitra = Mitra::findOrFail($data['mitra_id']);
        $this->authorizeMitra($request, $mitra);

        SpecialDeal::create([
            ...$data,
            'kae_user_id' => $user->id,
        ]);

        return redirect()->route('mitra.show', $mitra)->with('status', 'Special deal berhasil diajukan.');
    }

    public function edit(Request $request, SpecialDeal $specialDeal): View
    {
        $this->authorizeMitra($request, $specialDeal->mitra);

        $user = $request->user();
        $mitraOptions = Mitra::where('status', 'aktif')
            ->when(! $user->isAdmin(), fn ($q) => $q->where('kae_code', $user->kae_code))
            ->orderBy('nama')
            ->get();

        return view('special-deal.form', [
            'deal' => $specialDeal,
            'mitraOptions' => $mitraOptions,
            'selectedMitraId' => $specialDeal->mitra_id,
        ]);
    }

    public function update(Request $request, SpecialDeal $specialDeal): RedirectResponse
    {
        $this->authorizeMitra($request, $specialDeal->mitra);

        $data = $this->validated($request);
        $mitra = Mitra::findOrFail($data['mitra_id']);
        $this->authorizeMitra($request, $mitra);

        $specialDeal->update($data);

        return redirect()->route('special-deal.index')->with('status', 'Special deal berhasil diperbarui.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'mitra_id' => ['required', 'exists:mitra,id'],
            'deskripsi' => ['required', 'string'],
            'nilai' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:'.implode(',', self::STATUSES)],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
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
