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
        ]);

        $current = OmsetParsial::current();
        if ($current) {
            $current->update([
                'jumlah' => $data['jumlah'],
                'keterangan' => $data['keterangan'] ?? null,
                'updated_by' => $request->user()->id,
            ]);
        } else {
            OmsetParsial::create([
                'jumlah' => $data['jumlah'],
                'keterangan' => $data['keterangan'] ?? null,
                'updated_by' => $request->user()->id,
            ]);
        }

        return redirect()->route('dashboard')->with('status', 'Omset Parsial berhasil disimpan.');
    }

    public function destroy(): RedirectResponse
    {
        OmsetParsial::query()->delete();

        return redirect()->route('dashboard')->with('status', 'Omset Parsial berhasil dihapus.');
    }
}
