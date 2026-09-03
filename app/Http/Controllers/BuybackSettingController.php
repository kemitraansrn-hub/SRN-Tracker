<?php

namespace App\Http\Controllers;

use App\Models\BuybackSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BuybackSettingController extends Controller
{
    public function edit(): View
    {
        return view('pengaturan.buyback-setting', [
            'currentRate' => BuybackSetting::currentRate(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tingkat_penyusutan' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        BuybackSetting::create([
            'tingkat_penyusutan' => $data['tingkat_penyusutan'],
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('buyback-setting.edit')->with('status', 'Tingkat penyusutan berhasil diperbarui.');
    }
}
