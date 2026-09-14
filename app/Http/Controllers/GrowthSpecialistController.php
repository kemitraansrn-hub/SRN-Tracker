<?php

namespace App\Http\Controllers;

use App\Models\Mitra;
use App\Models\MitraProfilGrowth;
use App\Models\TargetBulanan;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Master Database — modul Growth Specialist (dari sheet "Kartu Profil
 * Mitra"). 1 baris = 1 mitra, kolom Nama/KAE RO/Channel otomatis dari
 * database (mitra + target_bulanan bulan berjalan), sisanya diisi manual
 * oleh Growth Specialist lewat edit inline per baris (lihat update()).
 */
class GrowthSpecialistController extends Controller
{
    private const PLATFORM_OPTIONS = ['Shopee', 'Tokopedia', 'Tiktok', 'Lazada', 'Meta Ads', 'WA', 'Reseller', 'Offline Toko'];

    public function index(): View
    {
        $now = now();

        $segmenMap = TargetBulanan::where('bulan', $now->month)
            ->where('tahun', $now->year)
            ->whereNotNull('segmen')
            ->pluck('segmen', 'mitra_id');

        $mitraList = Mitra::with('profilGrowth')
            ->orderBy('nama')
            ->get()
            ->map(function (Mitra $m) use ($segmenMap) {
                return [
                    'mitra' => $m,
                    'kae_nama' => $m->kae_code ? (User::kaeNameMap()[$m->kae_code] ?? $m->kae_code) : null,
                    'channel' => $segmenMap[$m->id] ?? null,
                    'profil' => $m->profilGrowth ?? new MitraProfilGrowth(['mitra_id' => $m->id]),
                ];
            });

        return view('growth-specialist.profiling-mitra', [
            'mitraList' => $mitraList,
            'platformOptions' => self::PLATFORM_OPTIONS,
        ]);
    }

    public function update(Request $request, Mitra $mitra): JsonResponse
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

        $profil = MitraProfilGrowth::updateOrCreate(['mitra_id' => $mitra->id], $data);

        return response()->json([
            'ok' => true,
            'persen_operational_cost' => $profil->persenOperationalCost(),
            'toleransi_cashflow' => $profil->toleransiCashflow(),
            'tipe_mitra' => $profil->tipeMitra(),
            'deadline_closing_pertama' => $profil->deadlineClosingPertama()?->format('Y-m-d'),
        ]);
    }
}
