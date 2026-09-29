<?php

namespace App\Http\Controllers;

use App\Models\LmsStep;
use App\Models\Mitra;
use App\Models\SpecialDeal;
use App\Models\StandInLineNote;
use App\Models\TrackingPerformance;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * "Stand in Line" (Tracking Performance > tab kedua) — roster mitra yang
 * LMS-nya sudah Lengkap di minimal satu platform (kriteria sama seperti
 * Komit Tracker, TANPA syarat "sudah ada di Tracking Performance" — justru
 * tujuannya nangkep mitra yang BELUM upload laporan mingguan). Centang
 * W1-W5 kalau ada baris Tracking Performance mitra itu untuk minggu itu di
 * bulan/tahun yang dipilih.
 */
class StandInLineController extends Controller
{
    public const WEEKS = ['W1', 'W2', 'W3', 'W4', 'W5'];

    public function index(Request $request): View
    {
        $user = $request->user();
        $bulan = (int) $request->input('bulan', now()->month);
        $tahun = (int) $request->input('tahun', now()->year);
        $q = trim((string) $request->input('q'));

        $mitraIds = $this->rosterMitraIds();

        $mitraRows = Mitra::whereIn('id', $mitraIds)
            ->when($user->role === 'kae', fn ($qr) => $qr->where('kae_code', $user->kae_code))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('nama', 'like', "%{$q}%")->orWhere('kode_mitra', 'like', "%{$q}%")))
            ->orderBy('nama')
            ->get(['id', 'nama', 'kode_mitra', 'kae_code']);

        $kaeMap = User::kaeNameMap();
        $segmenMap = SpecialDeal::segmenByMitraId(\Carbon\Carbon::create($tahun, $bulan, 1));

        $weeksByMitra = TrackingPerformance::whereIn('mitra_id', $mitraRows->pluck('id'))
            ->whereMonth('tanggal_selesai', $bulan)->whereYear('tanggal_selesai', $tahun)
            ->whereNotNull('week')
            ->get(['mitra_id', 'week'])
            ->groupBy('mitra_id')
            ->map(fn ($rows) => $rows->pluck('week')->unique()->all());

        $notesByMitra = StandInLineNote::where('bulan', $bulan)->where('tahun', $tahun)
            ->whereIn('mitra_id', $mitraRows->pluck('id'))
            ->get()->keyBy('mitra_id');

        $rows = $mitraRows->map(fn (Mitra $m) => [
            'mitra_id' => $m->id,
            'kode_mitra' => $m->kode_mitra,
            'nama' => $m->nama,
            'kae' => $m->kae_code ? ($kaeMap[$m->kae_code] ?? $m->kae_code) : '—',
            'segmen' => $segmenMap[$m->id] ?? '—',
            'weeks' => $weeksByMitra->get($m->id, []),
            'note' => $notesByMitra->get($m->id),
        ]);

        return view('stand-in-line.index', [
            'bulan' => $bulan,
            'tahun' => $tahun,
            'q' => $q,
            'weeks' => self::WEEKS,
            'rows' => $rows,
        ]);
    }

    public function storeNote(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mitra_id' => ['required', 'exists:mitra,id'],
            'bulan' => ['required', 'integer', 'min:1', 'max:12'],
            'tahun' => ['required', 'integer'],
            'catatan' => ['required', 'string', 'max:2000'],
        ]);

        StandInLineNote::updateOrCreate(
            ['mitra_id' => $data['mitra_id'], 'bulan' => $data['bulan'], 'tahun' => $data['tahun']],
            ['catatan' => $data['catatan'], 'created_by' => $request->user()->id]
        );

        return redirect()->route('growth-specialist.tracking-performance.stand-in-line', ['bulan' => $data['bulan'], 'tahun' => $data['tahun']])
            ->with('status', 'Catatan tersimpan.');
    }

    public function destroyNote(StandInLineNote $standInLineNote): RedirectResponse
    {
        $bulan = $standInLineNote->bulan;
        $tahun = $standInLineNote->tahun;
        $standInLineNote->delete();

        return redirect()->route('growth-specialist.tracking-performance.stand-in-line', ['bulan' => $bulan, 'tahun' => $tahun])
            ->with('status', 'Catatan dihapus.');
    }

    /** Mitra id yang Lengkap LMS-nya di minimal satu platform (lihat KomitTrackerController::rosterMitraIds() — sama, tanpa syarat Tracking Performance). */
    private function rosterMitraIds(): Collection
    {
        $stepCountByPlatform = LmsStep::where('aktif', true)
            ->select('platform', DB::raw('count(*) as total'))
            ->groupBy('platform')->pluck('total', 'platform');

        return DB::table('lms_step_completions')
            ->join('lms_steps', 'lms_steps.id', '=', 'lms_step_completions.lms_step_id')
            ->where('lms_steps.aktif', true)
            ->groupBy('lms_step_completions.mitra_id', 'lms_steps.platform')
            ->selectRaw('lms_step_completions.mitra_id, lms_steps.platform, count(*) as done')
            ->get()
            ->filter(fn ($row) => ($stepCountByPlatform[$row->platform] ?? 0) > 0 && $row->done >= $stepCountByPlatform[$row->platform])
            ->pluck('mitra_id')
            ->unique()
            ->values();
    }
}
