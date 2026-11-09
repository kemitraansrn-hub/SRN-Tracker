<?php

namespace App\Http\Controllers;

use App\Models\AkuisisiChannel;
use App\Models\AkuisisiChannelPerformance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Akuisisi > Performa Channel -- input manual Lead/Prospect/Closing/Biaya/
 * Investasi Mitra per channel per bulan, dipakai Dashboard > Akuisisi buat
 * hitung konversi closing (by lead & by prospect) dan CAC. Satu form, semua
 * channel aktif langsung ditampilkan & disimpan sekaligus (bukan modal per
 * channel) karena jumlah channel-nya kecil & tetap (lihat AkuisisiChannel).
 */
class AkuisisiChannelPerformanceController extends Controller
{
    public function index(Request $request): View
    {
        $bulan = max(1, min(12, (int) $request->input('bulan', now()->month)));
        $tahun = max(2000, min(2100, (int) $request->input('tahun', now()->year)));

        $channels = AkuisisiChannel::where('is_active', true)->orderBy('sort_order')->get();

        $existing = AkuisisiChannelPerformance::where('bulan', $bulan)->where('tahun', $tahun)
            ->whereIn('channel_id', $channels->pluck('id'))
            ->get()
            ->keyBy('channel_id');

        $rows = $channels->map(fn (AkuisisiChannel $channel) => [
            'channel' => $channel,
            'data' => $existing->get($channel->id),
        ]);

        return view('akuisisi.channel-performance.index', [
            'rows' => $rows,
            'bulan' => $bulan,
            'tahun' => $tahun,
            'isBulanIni' => $bulan === now()->month && $tahun === now()->year,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $bulan = max(1, min(12, (int) $request->input('bulan')));
        $tahun = max(2000, min(2100, (int) $request->input('tahun')));

        $validChannelIds = AkuisisiChannel::where('is_active', true)->pluck('id');

        $data = $request->validate([
            'data' => ['required', 'array'],
            'data.*.lead' => ['nullable', 'integer', 'min:0'],
            'data.*.prospect' => ['nullable', 'integer', 'min:0'],
            'data.*.closing' => ['nullable', 'integer', 'min:0'],
            'data.*.biaya' => ['nullable', 'numeric', 'min:0'],
            'data.*.investasi_mitra' => ['nullable', 'numeric', 'min:0'],
        ])['data'];

        $userId = $request->user()->id;

        $existing = AkuisisiChannelPerformance::where('bulan', $bulan)->where('tahun', $tahun)
            ->whereIn('channel_id', array_keys($data))
            ->get()->keyBy('channel_id');

        foreach ($data as $channelId => $values) {
            if (! $validChannelIds->contains((int) $channelId)) {
                continue;
            }

            AkuisisiChannelPerformance::updateOrCreate(
                ['channel_id' => $channelId, 'bulan' => $bulan, 'tahun' => $tahun],
                [
                    'lead' => $values['lead'] ?? 0,
                    'prospect' => $values['prospect'] ?? 0,
                    'closing' => $values['closing'] ?? 0,
                    'biaya' => $values['biaya'] ?? 0,
                    'investasi_mitra' => $values['investasi_mitra'] ?? 0,
                    'updated_by' => $userId,
                    'created_by' => $existing->get($channelId)?->created_by ?? $userId,
                ]
            );
        }

        return redirect()->route('akuisisi-channel-performance.index', ['bulan' => $bulan, 'tahun' => $tahun])
            ->with('status', 'Performa channel berhasil disimpan.');
    }
}
