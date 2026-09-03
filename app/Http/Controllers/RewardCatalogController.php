<?php

namespace App\Http\Controllers;

use App\Models\RewardCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RewardCatalogController extends Controller
{
    public function index(Request $request): View
    {
        $tahun = (int) $request->input('tahun', now()->year);

        return view('reward.index', [
            'tahun' => $tahun,
            'rewardList' => RewardCatalog::where('tahun', $tahun)->orderBy('poin_dibutuhkan')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['created_by'] = $request->user()->id;

        RewardCatalog::create($data);

        return redirect()->route('reward.index', ['tahun' => $data['tahun']])->with('status', 'Reward berhasil ditambahkan.');
    }

    public function update(Request $request, RewardCatalog $reward): RedirectResponse
    {
        $data = $this->validated($request);
        $reward->update($data);

        return redirect()->route('reward.index', ['tahun' => $data['tahun']])->with('status', 'Reward berhasil diperbarui.');
    }

    public function destroy(Request $request, RewardCatalog $reward): RedirectResponse
    {
        $tahun = $reward->tahun;
        $reward->delete();

        return redirect()->route('reward.index', ['tahun' => $tahun])->with('status', 'Reward berhasil dihapus.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'tahun' => ['required', 'integer', 'between:2020,2100'],
            'poin_dibutuhkan' => ['required', 'integer', 'min:1'],
            'harga_reward' => ['required', 'numeric', 'min:0'],
            'budget_reward' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:aktif,nonaktif'],
        ]);
    }
}
