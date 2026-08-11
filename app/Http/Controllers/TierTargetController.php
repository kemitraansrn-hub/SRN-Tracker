<?php

namespace App\Http\Controllers;

use App\Models\TargetBulanan;
use App\Services\StabilitasService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TierTargetController extends Controller
{
    public function index(Request $request): View
    {
        $bulan = (int) $request->input('bulan', now()->month);
        $tahun = (int) $request->input('tahun', now()->year);

        $rows = TargetBulanan::with('mitra')
            ->where('bulan', $bulan)->where('tahun', $tahun)
            ->whereHas('mitra')
            ->get()
            ->sortBy(fn ($r) => $r->mitra->nama ?? '')
            ->values();

        $referenceDate = \Carbon\Carbon::create($tahun, $bulan, 1);
        $stabilitasByMitra = StabilitasService::bulkForPreviousQuarter($referenceDate);
        $quarterRange = StabilitasService::previousQuarterRange($referenceDate);

        return view('pengaturan.tier-target', [
            'rows' => $rows,
            'bulan' => $bulan,
            'tahun' => $tahun,
            'periodeLabel' => \Carbon\Carbon::create($tahun, $bulan)->translatedFormat('F Y'),
            'stabilitasByMitra' => $stabilitasByMitra,
            'quarterLabel' => 'Q'.$quarterRange['kuartal'].' '.$quarterRange['tahun'],
        ]);
    }

    public function update(Request $request, TargetBulanan $targetBulanan): RedirectResponse
    {
        $data = $request->validate([
            'tier_dipakai' => ['required', 'in:'.implode(',', TargetBulanan::TIERS)],
        ]);

        $targetBulanan->update($data);

        return back()->with('status', 'Tier Dipakai untuk '.($targetBulanan->mitra->nama ?? 'mitra').' berhasil diubah.');
    }
}
