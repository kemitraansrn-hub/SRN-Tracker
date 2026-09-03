<?php

namespace App\Http\Controllers;

use App\Models\RunRateTarget;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RunRateTargetController extends Controller
{
    public function edit(Request $request): View
    {
        $bulan = (int) $request->input('bulan', now()->month);
        $tahun = (int) $request->input('tahun', now()->year);

        $kaeUsers = User::where('role', 'kae')->whereNotNull('kae_code')->orderBy('name')->get(['name', 'kae_code']);
        $kaeTargets = RunRateTarget::kaeTargetsMap($bulan, $tahun);

        return view('pengaturan.run-rate-target', [
            'bulan' => $bulan,
            'tahun' => $tahun,
            'periodeLabel' => \Carbon\Carbon::create($tahun, $bulan)->translatedFormat('F Y'),
            'companyTarget' => RunRateTarget::companyTarget($bulan, $tahun),
            'kaeUsers' => $kaeUsers,
            'kaeTargets' => $kaeTargets,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $bulan = (int) $request->input('bulan');
        $tahun = (int) $request->input('tahun');

        $data = $request->validate([
            'bulan' => ['required', 'integer', 'between:1,12'],
            'tahun' => ['required', 'integer', 'between:2020,2100'],
            'company_target' => ['nullable', 'integer', 'min:0'],
            'kae_target' => ['nullable', 'array'],
            'kae_target.*' => ['nullable', 'integer', 'min:0'],
        ]);

        if (filled($data['company_target'] ?? null)) {
            RunRateTarget::updateOrCreate(
                ['bulan' => $bulan, 'tahun' => $tahun, 'kae_code' => ''],
                ['target' => $data['company_target']]
            );
        } else {
            RunRateTarget::where('bulan', $bulan)->where('tahun', $tahun)->where('kae_code', '')->delete();
        }

        foreach ($data['kae_target'] ?? [] as $kaeCode => $target) {
            if (filled($target)) {
                RunRateTarget::updateOrCreate(
                    ['bulan' => $bulan, 'tahun' => $tahun, 'kae_code' => $kaeCode],
                    ['target' => $target]
                );
            } else {
                RunRateTarget::where('bulan', $bulan)->where('tahun', $tahun)->where('kae_code', $kaeCode)->delete();
            }
        }

        return redirect()->route('run-rate-target.edit', ['bulan' => $bulan, 'tahun' => $tahun])
            ->with('status', 'Target berhasil disimpan untuk '.\Carbon\Carbon::create($tahun, $bulan)->translatedFormat('F Y').'.');
    }
}
