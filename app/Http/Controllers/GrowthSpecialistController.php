<?php

namespace App\Http\Controllers;

use App\Models\Mitra;
use App\Models\MitraProfilGrowth;
use App\Models\TargetBulanan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

/**
 * Profiling Mitra — modul Growth Specialist (dari sheet "Kartu Profil
 * Mitra" > Master Database). Alurnya: index nampilin tabel rekap SEMUA
 * mitra (persis kolom-kolomnya kayak di sheet, dipaginasi 20/halaman sama
 * kayak Segmentasi Mitra), tombol "+ Input Mitra" buka halaman pilih mitra
 * (create) baru masuk ke form isi 7 section buat mitra itu (edit/update).
 */
class GrowthSpecialistController extends Controller
{
    private const PLATFORM_OPTIONS = ['Shopee', 'Tokopedia', 'Tiktok', 'Lazada', 'Meta Ads', 'WA', 'Reseller', 'Offline Toko'];

    public function index(Request $request): View
    {
        $now = now();

        $segmenMap = TargetBulanan::where('bulan', $now->month)
            ->where('tahun', $now->year)
            ->whereNotNull('segmen')
            ->pluck('segmen', 'mitra_id');

        $kaeMap = User::kaeNameMap();

        $rows = Mitra::with('profilGrowth')
            ->whereHas('profilGrowth')
            ->orderBy('nama')
            ->get()
            ->map(function (Mitra $m) use ($segmenMap, $kaeMap) {
                $p = $m->profilGrowth;

                return [
                    'mitra' => $m,
                    'kae_nama' => $m->kae_code ? ($kaeMap[$m->kae_code] ?? $m->kae_code) : null,
                    'channel' => $segmenMap[$m->id] ?? null,
                    'profil' => $p,
                    'pct' => $p->persenOperationalCost(),
                    'toleransi_cashflow' => $p->toleransiCashflow(),
                    'tipe_mitra' => $p->tipeMitra(),
                    'deadline_closing' => $p->deadlineClosingPertama(),
                ];
            });

        if ($cari = trim((string) $request->input('cari'))) {
            $rows = $rows->filter(fn ($r) => str_contains(mb_strtolower($r['mitra']->nama), mb_strtolower($cari)));
        }

        if ($tipeMitra = $request->input('tipe_mitra')) {
            $rows = $rows->filter(fn ($r) => $tipeMitra === 'belum' ? $r['tipe_mitra'] === null : $r['tipe_mitra'] === $tipeMitra);
        }

        if ($kaeCode = $request->input('kae_code')) {
            $rows = $rows->filter(fn ($r) => $r['mitra']->kae_code === $kaeCode);
        }

        if ($lmsStatus = $request->input('lms_status')) {
            $rows = $rows->filter(fn ($r) => $lmsStatus === 'belum' ? $r['profil']->lms_status === null : $r['profil']->lms_status === $lmsStatus);
        }

        $rows = $rows->values();

        $perPage = 20;
        $page = (int) $request->input('page', 1);
        $rowsPage = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('growth-specialist.profiling-mitra-index', [
            'rowsPage' => $rowsPage,
            'totalMitra' => MitraProfilGrowth::count(),
            'kaeOptions' => $kaeMap,
            'filters' => $request->only(['cari', 'tipe_mitra', 'kae_code', 'lms_status']),
        ]);
    }

    public function create(): View
    {
        $mitraOptions = Mitra::orderBy('nama')->get(['id', 'nama', 'kode_mitra']);

        return view('growth-specialist.profiling-mitra-create', [
            'mitraOptions' => $mitraOptions,
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

        return redirect()->route('growth-specialist.profiling-mitra')
            ->with('status', 'Profil '.$mitra->nama.' berhasil disimpan.');
    }
}
