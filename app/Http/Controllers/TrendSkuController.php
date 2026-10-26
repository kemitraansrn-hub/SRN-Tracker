<?php

namespace App\Http\Controllers;

use App\Models\Produk;
use App\Models\TrendSku;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Aksi tandai/edit/hapus Trend SKU -- dipanggil dari tab "Input Trend SKU"
 * di menu Input NPD (resources/views/npd/index.blade.php). Lihat
 * CekTrendSkuController buat halaman line chart-nya (menu Sales).
 */
class TrendSkuController extends Controller
{
    public function store(Request $request, Produk $produk): RedirectResponse
    {
        if (TrendSku::where('produk_id', $produk->id)->exists()) {
            return back()->with('status', 'Produk ini sudah ditandai sebagai Trend SKU.');
        }

        $data = $request->validate([
            'tanggal_mulai_campaign' => ['required', 'date'],
        ]);

        TrendSku::create([
            'produk_id' => $produk->id,
            'tanggal_mulai_campaign' => $data['tanggal_mulai_campaign'],
            'ditandai_oleh' => $request->user()->id,
        ]);

        return redirect()->route('npd.index', $request->only(['q', 'brand', 'page']))
            ->with('status', $produk->nama.' ditandai sebagai Trend SKU.');
    }

    public function update(Request $request, TrendSku $trendSku): RedirectResponse
    {
        $data = $request->validate([
            'tanggal_mulai_campaign' => ['required', 'date'],
        ]);

        $trendSku->update(['tanggal_mulai_campaign' => $data['tanggal_mulai_campaign']]);

        return redirect()->route('npd.index', $request->only(['q', 'brand', 'page']))
            ->with('status', 'Tanggal mulai campaign berhasil diperbarui.');
    }

    public function destroy(Request $request, TrendSku $trendSku): RedirectResponse
    {
        $nama = $trendSku->produk->nama ?? 'produk ini';
        $trendSku->delete();

        return redirect()->route('npd.index', $request->only(['q', 'brand', 'page']))
            ->with('status', 'Status Trend SKU untuk '.$nama.' dicabut.');
    }
}
