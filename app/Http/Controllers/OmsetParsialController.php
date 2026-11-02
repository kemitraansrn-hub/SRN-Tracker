<?php

namespace App\Http\Controllers;

use App\Models\OmsetParsial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OmsetParsialController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'jumlah' => ['required', 'numeric', 'min:0'],
            'keterangan' => ['nullable', 'string', 'max:255'],
            'bulan' => ['required', 'integer', 'min:1', 'max:12'],
            'tahun' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $current = OmsetParsial::forPeriode((int) $data['bulan'], (int) $data['tahun']);
        if ($current) {
            $current->update([
                'jumlah' => $data['jumlah'],
                'keterangan' => $data['keterangan'] ?? null,
                'updated_by' => $request->user()->id,
            ]);
        } else {
            OmsetParsial::create([
                'bulan' => $data['bulan'],
                'tahun' => $data['tahun'],
                'jumlah' => $data['jumlah'],
                'keterangan' => $data['keterangan'] ?? null,
                'updated_by' => $request->user()->id,
            ]);
        }

        return redirect()->route('dashboard', $request->only(['bulan', 'tahun']))->with('status', 'Omset Parsial berhasil disimpan.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bulan' => ['required', 'integer', 'min:1', 'max:12'],
            'tahun' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        OmsetParsial::query()->where('bulan', $data['bulan'])->where('tahun', $data['tahun'])->delete();

        return redirect()->route('dashboard', $request->only(['bulan', 'tahun']))->with('status', 'Omset Parsial berhasil dihapus.');
    }
}
