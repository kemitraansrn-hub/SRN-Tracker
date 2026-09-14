<?php

namespace App\Http\Controllers;

use App\Models\Mitra;
use App\Models\MitraProfilGrowth;
use App\Models\TargetBulanan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Profiling Mitra — modul Growth Specialist (dari sheet "Kartu Profil
 * Mitra" > Master Database). Alurnya form input per satu mitra: pilih
 * mitra dulu (index), baru muncul form isi semua section buat mitra itu
 * (edit/update). Tabel rekap semua mitra sekaligus ala sheet menyusul
 * belakangan sebagai fitur terpisah.
 */
class GrowthSpecialistController extends Controller
{
    private const PLATFORM_OPTIONS = ['Shopee', 'Tokopedia', 'Tiktok', 'Lazada', 'Meta Ads', 'WA', 'Reseller', 'Offline Toko'];

    public function index(): View
    {
        $mitraOptions = Mitra::orderBy('nama')->get(['id', 'nama', 'kode_mitra']);

        $sudahDiisi = MitraProfilGrowth::with('mitra:id,nama,kode_mitra')
            ->latest('updated_at')
            ->get();

        return view('growth-specialist.profiling-mitra-index', [
            'mitraOptions' => $mitraOptions,
            'sudahDiisi' => $sudahDiisi,
        ]);
    }

    public function edit(Mitra $mitra): View
    {
        $now = now();

        $channel = TargetBulanan::where('mitra_id', $mitra->id)
            ->where('bulan', $now->month)
            ->where('tahun', $now->year)
            ->value('segmen');

        $profil = $mitra->profilGrowth ?? new MitraProfilGrowth(['mitra_id' => $mitra->id]);

        return view('growth-specialist.profiling-mitra-form', [
            'mitra' => $mitra,
            'profil' => $profil,
            'kaeNama' => $mitra->kae_code ? (User::kaeNameMap()[$mitra->kae_code] ?? $mitra->kae_code) : null,
            'channel' => $channel,
            'platformOptions' => self::PLATFORM_OPTIONS,
        ]);
    }

    public function update(Request $request, Mitra $mitra): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['nullable', 'in:Existing,New Distri'],
            'tanggal_onboarding' => ['nullable', 'date'],
            'modal_bisnis' => ['nullable', 'numeric', 'min:0'],
            'modal_srn' => ['nullable', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'tim_sendiri' => ['nullable', 'string', 'max:255'],
            'platform_jualan' => ['nullable', 'array'],
            'platform_jualan.*' => ['string', 'in:'.implode(',', self::PLATFORM_OPTIONS)],
            'jam_aktif' => ['nullable', 'string', 'max:255'],
            'tipe_channel' => ['nullable', 'in:Online,Offline'],
            'channel_fokus_1' => ['nullable', 'array'],
            'channel_fokus_1.*' => ['string', 'in:'.implode(',', self::PLATFORM_OPTIONS)],
            'channel_fokus_2' => ['nullable', 'array'],
            'channel_fokus_2.*' => ['string', 'in:'.implode(',', self::PLATFORM_OPTIONS)],
            'motivasi' => ['nullable', 'integer', 'min:1', 'max:5'],
            'kemampuan' => ['nullable', 'integer', 'min:1', 'max:5'],
            'keaktifan' => ['nullable', 'integer', 'min:1', 'max:5'],
            'deadline_setup_channel' => ['nullable', 'date'],
            'target_traffic' => ['nullable', 'integer', 'min:0'],
            'target_leads' => ['nullable', 'integer', 'min:0'],
            'lms_status' => ['nullable', 'in:Done,On Progress'],
            'catatan' => ['nullable', 'string'],
        ]);

        MitraProfilGrowth::updateOrCreate(['mitra_id' => $mitra->id], $data);

        return redirect()->route('growth-specialist.profiling-mitra.edit', $mitra)
            ->with('status', 'Profil '.$mitra->nama.' berhasil disimpan.');
    }
}
