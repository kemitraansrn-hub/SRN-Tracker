<?php

namespace App\Http\Controllers;

use App\Models\NpdProduct;
use App\Models\Produk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NpdProductController extends Controller
{
    public function index(Request $request): View
    {
        $produkList = Produk::query()
            ->when($request->filled('q'), fn ($q) => $q->where('nama', 'like', '%'.$request->input('q').'%'))
            ->when($request->filled('brand'), fn ($q) => $q->where('brand', $request->input('brand')))
            ->orderBy('brand')->orderBy('nama')
            ->paginate(30)->withQueryString();

        $npdByProdukId = NpdProduct::whereIn('produk_id', $produkList->pluck('id'))->get()->keyBy('produk_id');

        return view('npd.index', [
            'produkList' => $produkList,
            'npdByProdukId' => $npdByProdukId,
            'brandOptions' => Produk::select('brand')->distinct()->orderBy('brand')->pluck('brand'),
        ]);
    }

    public function toggle(Request $request, Produk $produk): RedirectResponse
    {
        $npd = NpdProduct::where('produk_id', $produk->id)->first();

        if ($npd) {
            $npd->delete();
        } else {
            NpdProduct::create([
                'produk_id' => $produk->id,
                'tanggal_ditandai' => now(),
                'ditandai_oleh' => $request->user()->id,
            ]);
        }

        return redirect()->route('npd.index', $request->only(['q', 'brand', 'page']))
            ->with('status', $npd ? 'Status NPD dicabut.' : 'Produk ditandai sebagai NPD.');
    }
}
