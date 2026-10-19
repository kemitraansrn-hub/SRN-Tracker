<?php

namespace App\Http\Controllers;

use App\Models\Pengakuisisi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Master data Pengakuisisi (Program Akuisisi Mitra Offline) -- dipakai di
 * form "Tambah Mitra" pada menu Akuisisi > Tracking Referral.
 */
class PengakuisisiController extends Controller
{
    public function index(): View
    {
        return view('akuisisi.pengakuisisi', [
            'pengakuisisiList' => Pengakuisisi::withCount('akuisisiReferrals')->orderBy('nama')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;

        Pengakuisisi::create($data);

        return redirect()->route('pengakuisisi.index')->with('status', 'Pengakuisisi berhasil ditambahkan.');
    }

    public function update(Request $request, Pengakuisisi $pengakuisisi): RedirectResponse
    {
        $data = $this->validated($request);
        $pengakuisisi->update($data);

        // Baris Tracking Referral yang udah pernah pakai Pengakuisisi ini
        // ikut disinkronkan namanya (nama_pengakuisisi itu salinan, lihat
        // catatan di model AkuisisiReferral), biar gak nampilin nama basi
        // kalau misal typo dibenerin belakangan.
        $pengakuisisi->akuisisiReferrals()->update(['nama_pengakuisisi' => $data['nama']]);

        return redirect()->route('pengakuisisi.index')->with('status', 'Pengakuisisi berhasil diperbarui.');
    }

    public function destroy(Pengakuisisi $pengakuisisi): RedirectResponse
    {
        if ($pengakuisisi->akuisisiReferrals()->exists()) {
            return back()->with('status', 'Pengakuisisi ini masih dipakai di Tracking Referral, gak bisa dihapus.');
        }

        $pengakuisisi->delete();

        return redirect()->route('pengakuisisi.index')->with('status', 'Pengakuisisi berhasil dihapus.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'bank' => ['nullable', 'string', 'max:255'],
            'no_rekening' => ['nullable', 'string', 'max:50'],
            'alamat' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
