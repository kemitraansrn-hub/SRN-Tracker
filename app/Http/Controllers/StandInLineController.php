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
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Stand in Line" (Tracking Performance > tab kedua) — roster mitra yang
 * LMS-nya sudah Lengkap di minimal satu platform (kriteria sama seperti
 * Komit Tracker, TANPA syarat "sudah ada di Tracking Performance" — justru
 * tujuannya nangkep mitra yang BELUM upload laporan mingguan). Ceklis W1-Wn
 * di layar itu KUMULATIF (pernah upload minggu itu kapan pun, gak terikat
 * periode) — Bulan/Tahun di filter dipakai buat Download Excel, tapi
 * Download men-snapshot SATU KUARTAL PENUH (3 bulan) yang memuat bulan
 * terpilih, bukan cuma bulan itu sendiri — W1, W2, dst reset tiap kuartal
 * (lihat StandInLineController::quarterBoundsFor()). Jumlah kolom minggu (W1, W2, ... Wn) MENGIKUTI
 * data yang beneran di-upload — kalau ada mitra yang udah sampai W12, tabel
 * otomatis nampilin kolom sampai W12, bukan dihardcode W1-W5.
 */
class StandInLineController extends Controller
{
    private const MIN_WEEK_COLUMNS = 5;

    public function index(Request $request): View
    {
        $user = $request->user();
        $bulan = (int) $request->input('bulan', now()->month);
        $tahun = (int) $request->input('tahun', now()->year);
        $q = trim((string) $request->input('q'));

        $mitraIds = $this->rosterMitraIds();
        $weeks = $this->weekColumns($mitraIds);
        $rows = $this->buildRows($request, $user, $q, $mitraIds, cumulatif: true, bulan: $bulan, tahun: $tahun);

        return view('stand-in-line.index', [
            'bulan' => $bulan,
            'tahun' => $tahun,
            'q' => $q,
            'weeks' => $weeks,
            'rows' => $rows,
        ]);
    }

    public function download(Request $request): StreamedResponse
    {
        $user = $request->user();
        $bulan = (int) $request->input('bulan', now()->month);
        $tahun = (int) $request->input('tahun', now()->year);

        [$kuartalKe] = $this->quarterBoundsFor($bulan, $tahun);
        $kuartalLabel = 'Q'.$kuartalKe;

        $mitraIds = $this->rosterMitraIds();
        $weeks = $this->weekColumns($mitraIds);
        $rows = $this->buildRows($request, $user, trim((string) $request->input('q')), $mitraIds, cumulatif: false, bulan: $bulan, tahun: $tahun);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Stand in Line');

        $weekHeaders = array_map(fn ($w) => $w.' ('.$kuartalLabel.' '.$tahun.')', $weeks);
        $headers = array_merge(['ID Mitra', 'Nama Mitra', 'KAE', 'Segmen'], $weekHeaders, ['Catatan']);
        $sheet->fromArray($headers, null, 'A1', true);
        $lastColIndex = count($headers);
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastColIndex);
        $sheet->getStyle('A1:'.$lastCol.'1')->getFont()->setBold(true);

        $r = 2;
        foreach ($rows as $row) {
            $sheet->fromArray(array_merge(
                [$row['kode_mitra'], $row['nama'], $row['kae'], $row['segmen']],
                array_map(fn ($w) => in_array($w, $row['weeks'], true) ? 'Sudah' : 'Belum', $weeks),
                [$row['note']->catatan ?? '']
            ), null, 'A'.$r, true);
            $r++;
        }

        foreach (range(1, $lastColIndex) as $colIndex) {
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex))->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'Stand in Line '.$kuartalLabel.' '.$tahun.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
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

    /**
     * @return Collection<int, array>
     *
     * $cumulatif true (layar): ceklis W1-Wn dari SELURUH riwayat Tracking
     * Performance mitra, gak peduli periode — tracker "pernah selesai kapan
     * pun" sejak LMS Lengkap. $cumulatif false (download): ceklis cuma dari
     * laporan yang tanggal_selesai-nya jatuh di SATU KUARTAL PENUH (3 bulan)
     * yang memuat bulan/tahun terpilih — snapshot laporan kuartal itu,
     * BUKAN cuma 1 bulan. Wajib per-kuartal (bukan per-bulan) karena
     * penomoran W1, W2, dst reset tiap kuartal (lihat kalender kuartal yang
     * user kasih 2026-10-07) — W1 di Juli (Q3) dan W1 di Oktober (Q4) adalah
     * minggu yang beda sama sekali walau labelnya sama, jadi filter 1 bulan
     * saja dulu salah motong data kuartal yang sama.
     */
    private function buildRows(Request $request, User $user, string $q, Collection $mitraIds, bool $cumulatif, int $bulan, int $tahun): Collection
    {
        $mitraRows = Mitra::whereIn('id', $mitraIds)
            ->when($user->role === 'kae', fn ($qr) => $qr->where('kae_code', $user->kae_code))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('nama', 'like', "%{$q}%")->orWhere('kode_mitra', 'like', "%{$q}%")))
            ->orderBy('nama')
            ->get(['id', 'nama', 'kode_mitra', 'kae_code']);

        $kaeMap = User::kaeNameMap();
        $segmenMap = SpecialDeal::segmenByMitraId(now());

        $weeksByMitra = TrackingPerformance::whereIn('mitra_id', $mitraRows->pluck('id'))
            ->when(! $cumulatif, function ($qr) use ($bulan, $tahun) {
                [, $start, $end] = $this->quarterBoundsFor($bulan, $tahun);
                $qr->whereBetween('tanggal_selesai', [$start->toDateString(), $end->toDateString()]);
            })
            ->whereNotNull('week')
            ->get(['mitra_id', 'week'])
            ->groupBy('mitra_id')
            ->map(fn ($rows) => $rows->pluck('week')->unique()->all());

        $notesByMitra = StandInLineNote::where('bulan', $bulan)->where('tahun', $tahun)
            ->whereIn('mitra_id', $mitraRows->pluck('id'))
            ->get()->keyBy('mitra_id');

        return $mitraRows->map(fn (Mitra $m) => [
            'mitra_id' => $m->id,
            'kode_mitra' => $m->kode_mitra,
            'nama' => $m->nama,
            'kae' => $m->kae_code ? ($kaeMap[$m->kae_code] ?? $m->kae_code) : '—',
            'segmen' => $segmenMap[$m->id] ?? '—',
            'weeks' => $weeksByMitra->get($m->id, []),
            'note' => $notesByMitra->get($m->id),
        ]);
    }

    /**
     * Kolom minggu (W1, W2, ... Wn) MENGIKUTI data Tracking Performance yang
     * beneran ada — bukan dihardcode. Minimal tetap tampil W1-W5 (baseline)
     * biar tabel gak kosong pas belum ada yang upload sama sekali.
     *
     * @return string[]
     */
    private function weekColumns(Collection $mitraIds): array
    {
        $maxWeek = TrackingPerformance::whereIn('mitra_id', $mitraIds)
            ->whereNotNull('week')
            ->pluck('week')
            ->map(fn ($w) => (int) preg_replace('/\D+/', '', (string) $w))
            ->filter(fn ($n) => $n > 0)
            ->max();

        $maxWeek = max((int) $maxWeek, self::MIN_WEEK_COLUMNS);

        return array_map(fn ($i) => 'W'.$i, range(1, $maxWeek));
    }

    /** Mitra id yang Lengkap LMS-nya di minimal satu platform (lihat KomitTrackerController::rosterMitraIds() — sama, tanpa syarat Tracking Performance). */
    private function rosterMitraIds(): Collection
    {
        return LmsStep::mitraIdsLengkap();
    }

    /**
     * Kuartal kalender standar (Q1 Jan-Mar, Q2 Apr-Jun, Q3 Jul-Sep, Q4
     * Okt-Des) yang memuat $bulan/$tahun, dipakai buat nge-scope Download
     * Excel Stand in Line ke satu kuartal penuh, bukan satu bulan.
     *
     * @return array{0: int, 1: \Illuminate\Support\Carbon, 2: \Illuminate\Support\Carbon}
     */
    private function quarterBoundsFor(int $bulan, int $tahun): array
    {
        $kuartalKe = (int) ceil($bulan / 3);
        $startMonth = ($kuartalKe - 1) * 3 + 1;
        $start = \Carbon\Carbon::create($tahun, $startMonth, 1)->startOfDay();
        $end = $start->copy()->addMonths(2)->endOfMonth();

        return [$kuartalKe, $start, $end];
    }
}
