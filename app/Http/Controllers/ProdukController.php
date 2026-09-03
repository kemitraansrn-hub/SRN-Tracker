<?php

namespace App\Http\Controllers;

use App\Models\Produk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProdukController extends Controller
{
    public function index(Request $request): View
    {
        $produkList = Produk::query()
            ->when($request->filled('q'), fn ($q) => $q->where(function ($qq) use ($request) {
                $qq->where('nama', 'like', '%'.$request->input('q').'%')
                    ->orWhere('kode_sku', 'like', '%'.$request->input('q').'%');
            }))
            ->when($request->filled('brand'), fn ($q) => $q->where('brand', $request->input('brand')))
            ->orderBy('brand')->orderBy('nama')
            ->get();

        $brands = Produk::query()->distinct()->orderBy('brand')->pluck('brand');

        return view('produk.index', ['produkList' => $produkList, 'brands' => $brands]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kode_sku' => ['nullable', 'string', 'max:255', 'unique:produk,kode_sku'],
            'nama' => ['required', 'string', 'max:255'],
            'brand' => ['required', 'string', 'max:255'],
            'kategori' => ['nullable', 'string', 'max:255'],
            'harga' => ['nullable', 'numeric', 'min:0'],
            'qty_per_poin' => ['nullable', 'numeric', 'min:0.0001'],
            'status' => ['required', 'in:aktif,nonaktif'],
        ]);

        Produk::create($data);

        return redirect()->route('produk.index')->with('status', 'Produk berhasil ditambahkan.');
    }

    public function update(Request $request, Produk $produk): RedirectResponse
    {
        $data = $request->validate([
            'kode_sku' => ['nullable', 'string', 'max:255', 'unique:produk,kode_sku,'.$produk->id],
            'nama' => ['required', 'string', 'max:255'],
            'brand' => ['required', 'string', 'max:255'],
            'kategori' => ['nullable', 'string', 'max:255'],
            'harga' => ['nullable', 'numeric', 'min:0'],
            'qty_per_poin' => ['nullable', 'numeric', 'min:0.0001'],
            'status' => ['required', 'in:aktif,nonaktif'],
        ]);

        $produk->update($data);

        return redirect()->route('produk.index')->with('status', 'Produk berhasil diperbarui.');
    }

    public function destroy(Produk $produk): RedirectResponse
    {
        $produk->delete();

        return redirect()->route('produk.index')->with('status', 'Produk berhasil dihapus.');
    }

    /**
     * "Notifikasi" produk baru: SKU/nama yang gak ketemu waktu import order
     * harian, jadi otomatis ke-create di Master Produk — perlu di-review
     * manual (cek typo/duplikat) baru ditandai sudah dibaca.
     */
    public function notifications(): View
    {
        return view('produk.notifications', [
            'pending' => Produk::pendingReview()->latest()->get(),
            'recentlyReviewed' => Produk::where('auto_created', true)->whereNotNull('reviewed_at')
                ->latest('reviewed_at')->limit(20)->get(),
        ]);
    }

    public function markReviewed(Produk $produk): RedirectResponse
    {
        $produk->update(['reviewed_at' => now()]);

        return back()->with('status', 'Produk "'.$produk->nama.'" ditandai sudah dibaca.');
    }

    public function markAllReviewed(): RedirectResponse
    {
        $count = Produk::pendingReview()->update(['reviewed_at' => now()]);

        return back()->with('status', $count.' produk ditandai sudah dibaca.');
    }
}
