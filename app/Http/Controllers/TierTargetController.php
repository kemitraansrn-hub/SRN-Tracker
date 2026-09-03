<?php

namespace App\Http\Controllers;

use App\Models\TargetBulanan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TierTargetController extends Controller
{
    public function update(Request $request, TargetBulanan $targetBulanan): RedirectResponse
    {
        $data = $request->validate([
            'tier_dipakai' => ['required', 'in:'.implode(',', TargetBulanan::TIERS)],
        ]);

        $targetBulanan->update($data);

        return back()->with('status', 'Tier Dipakai untuk '.($targetBulanan->mitra->nama ?? 'mitra').' berhasil diubah.');
    }
}
