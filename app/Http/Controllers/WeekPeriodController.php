<?php

namespace App\Http\Controllers;

use App\Models\WeekPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WeekPeriodController extends Controller
{
    public function edit(Request $request): View
    {
        $bulan = (int) $request->input('bulan', now()->month);
        $tahun = (int) $request->input('tahun', now()->year);

        $existing = WeekPeriod::forMonth($bulan, $tahun);
        $rows = $existing->isNotEmpty() ? $existing : WeekPeriod::defaultsFor($bulan, $tahun);

        return view('pengaturan.minggu', [
            'bulan' => $bulan,
            'tahun' => $tahun,
            'rows' => $rows,
            'sudahDiset' => $existing->isNotEmpty(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bulan' => ['required', 'integer', 'min:1', 'max:12'],
            'tahun' => ['required', 'integer', 'min:2020', 'max:2100'],
            'minggu' => ['required', 'array'],
            'minggu.*.mulai' => ['required', 'date'],
            'minggu.*.selesai' => ['required', 'date', 'after_or_equal:minggu.*.mulai'],
        ]);

        foreach ($data['minggu'] as $label => $range) {
            if ($range['selesai'] < $range['mulai']) {
                return back()->withErrors(['minggu' => "Tanggal selesai $label tidak boleh sebelum tanggal mulai."])->withInput();
            }

            WeekPeriod::updateOrCreate(
                ['bulan' => $data['bulan'], 'tahun' => $data['tahun'], 'minggu' => $label],
                ['tanggal_mulai' => $range['mulai'], 'tanggal_selesai' => $range['selesai']]
            );
        }

        return redirect()->route('pengaturan.minggu', ['bulan' => $data['bulan'], 'tahun' => $data['tahun']])
            ->with('status', 'Periode mingguan berhasil disimpan.');
    }
}
