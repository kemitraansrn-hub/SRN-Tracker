<?php

namespace App\Http\Controllers;

use App\Models\LmsStep;
use App\Models\Mitra;
use App\Models\TargetBulanan;
use App\Models\User;
use App\Services\AchievementStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Komit Tracker (Growth Specialist > Special Reg & Reg, di bawah Set Up
 * LMS) — roster mitra yang sudah Lengkap LMS-nya di minimal satu platform
 * DAN datanya sudah pernah masuk ke Tracking Performance. Data Komit/
 * Pencapaian/% Ach/Status Belanja mengikuti bulan-tahun yang dipilih (roster
 * sendiri gak berubah per bulan, cuma angkanya).
 */
class KomitTrackerController extends Controller
{
    public function index(Request $request): View
    {
        $bulan = (int) $request->input('bulan', now()->month);
        $tahun = (int) $request->input('tahun', now()->year);

        $rows = $this->buildRows($bulan, $tahun, $request);

        return view('komit-tracker.index', [
            'bulan' => $bulan,
            'tahun' => $tahun,
            'periodeLabel' => \Carbon\Carbon::create($tahun, $bulan)->translatedFormat('F Y'),
            'bulanNama' => \Carbon\Carbon::create($tahun, $bulan)->translatedFormat('F'),
            'rows' => $rows,
            'statusBelanjaOptions' => array_map(fn ($s) => AchievementStatus::label($s), ['belum-belanja', 'kurang', 'mendekati', 'tercapai', 'over-ro']),
        ]);
    }

    public function download(Request $request): StreamedResponse
    {
        $bulan = (int) $request->input('bulan', now()->month);
        $tahun = (int) $request->input('tahun', now()->year);
        $bulanNama = \Carbon\Carbon::create($tahun, $bulan)->translatedFormat('F');
        $rows = $this->buildRows($bulan, $tahun, $request);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Komit Tracker');

        $headers = ['Nama Mitra', 'KAE RO', 'KAE Development', 'Status LMS', 'Komit '.$bulanNama, $bulanNama, '% Ach', 'Status Belanja'];
        $sheet->fromArray($headers, null, 'A1', true);
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);

        $r = 2;
        foreach ($rows as $row) {
            $sheet->fromArray([
                $row['nama'],
                $row['kae_ro'],
                $row['kae_development'],
                $row['status_lms'],
                (float) $row['komit'],
                (float) $row['pencapaian'],
                $row['pct_ach'] !== null ? $row['pct_ach'].'%' : '—',
                $row['status_belanja_label'],
            ], null, 'A'.$r, true);
            $r++;
        }

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 'Komit Tracker '.$bulanNama.' '.$tahun.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** @return Collection<int, array> */
    private function buildRows(int $bulan, int $tahun, Request $request): Collection
    {
        $mitraIds = $this->rosterMitraIds();

        if ($mitraIds->isEmpty()) {
            return collect();
        }

        $q = trim((string) $request->input('q'));
        $statusBelanjaFilter = trim((string) $request->input('status_belanja'));

        $mitraRows = Mitra::whereIn('id', $mitraIds)
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('nama', 'like', "%{$q}%")->orWhere('kode_mitra', 'like', "%{$q}%")))
            ->orderBy('nama')
            ->get(['id', 'nama', 'kode_mitra', 'kae_code']);

        if ($mitraRows->isEmpty()) {
            return collect();
        }

        $kaeMap = User::kaeNameMap();
        // Belum ada assignment "growth specialist per mitra" di data (role
        // growth_specialist juga belum dipakai user manapun — semua masih
        // admin), jadi KAE Development dipakai dari siapa yang lagi buka
        // halaman ini.
        $kaeDevelopment = $request->user()->name ?? '—';

        $targetByMitra = TargetBulanan::where('bulan', $bulan)->where('tahun', $tahun)
            ->whereIn('mitra_id', $mitraRows->pluck('id'))->get()->keyBy('mitra_id');

        $realisasiByMitra = DB::table('orders')
            ->whereIn('mitra_id', $mitraRows->pluck('id'))
            ->whereYear('tanggal_order', $tahun)->whereMonth('tanggal_order', $bulan)
            ->groupBy('mitra_id')
            ->selectRaw('mitra_id, SUM(total_transaksi) as total')
            ->pluck('total', 'mitra_id');

        $lmsStatusByMitra = $this->lmsStatusByMitra($mitraRows->pluck('id'));

        $rows = $mitraRows->map(function (Mitra $m) use ($kaeMap, $kaeDevelopment, $targetByMitra, $realisasiByMitra, $lmsStatusByMitra) {
            $target = $targetByMitra->get($m->id);
            $komit = (float) ($target->komit ?? 0);
            $effectiveTarget = $target ? $target->effectiveTarget() : 0.0;
            $pencapaian = (float) ($realisasiByMitra[$m->id] ?? 0);
            $pctAch = $komit > 0 ? round($pencapaian / $komit * 100, 1) : null;
            $pctVsEffective = $effectiveTarget > 0 ? round($pencapaian / $effectiveTarget * 100, 1) : 0.0;
            $statusKey = AchievementStatus::resolveWeeklyPlan($pencapaian, $effectiveTarget, $pctVsEffective);

            return [
                'mitra_id' => $m->id,
                'nama' => $m->nama,
                'kode_mitra' => $m->kode_mitra,
                'kae_ro' => $m->kae_code ? ($kaeMap[$m->kae_code] ?? $m->kae_code) : '—',
                'kae_development' => $kaeDevelopment,
                'status_lms' => $lmsStatusByMitra[$m->id] ?? '—',
                'komit' => $komit,
                'pencapaian' => $pencapaian,
                'pct_ach' => $pctAch,
                'status_belanja_key' => $statusKey,
                'status_belanja_label' => AchievementStatus::label($statusKey),
                'status_belanja_color' => AchievementStatus::color($statusKey),
            ];
        });

        if ($statusBelanjaFilter !== '') {
            $rows = $rows->filter(fn ($r) => $r['status_belanja_label'] === $statusBelanjaFilter);
        }

        return $rows->values();
    }

    /** Mitra id yang Lengkap LMS-nya di minimal satu platform DAN pernah punya data di Tracking Performance. */
    private function rosterMitraIds(): Collection
    {
        $stepCountByPlatform = LmsStep::where('aktif', true)
            ->select('platform', DB::raw('count(*) as total'))
            ->groupBy('platform')->pluck('total', 'platform');

        $lengkapMitraIds = DB::table('lms_step_completions')
            ->join('lms_steps', 'lms_steps.id', '=', 'lms_step_completions.lms_step_id')
            ->where('lms_steps.aktif', true)
            ->groupBy('lms_step_completions.mitra_id', 'lms_steps.platform')
            ->selectRaw('lms_step_completions.mitra_id, lms_steps.platform, count(*) as done')
            ->get()
            ->filter(fn ($row) => ($stepCountByPlatform[$row->platform] ?? 0) > 0 && $row->done >= $stepCountByPlatform[$row->platform])
            ->pluck('mitra_id')
            ->unique();

        $trackingMitraIds = DB::table('tracking_performances')->distinct()->pluck('mitra_id');

        return $lengkapMitraIds->intersect($trackingMitraIds)->values();
    }

    /** @return array<int, string> teks status LMS per mitra, misal "Shopee: Lengkap, Meta: Proses". */
    private function lmsStatusByMitra(Collection $mitraIds): array
    {
        $stepCountByPlatform = LmsStep::where('aktif', true)
            ->select('platform', DB::raw('count(*) as total'))
            ->groupBy('platform')->pluck('total', 'platform');

        $enrolledPlatforms = DB::table('lms_enrollments')
            ->whereIn('mitra_id', $mitraIds)
            ->get(['mitra_id', 'platform'])
            ->groupBy('mitra_id');

        $doneCounts = DB::table('lms_step_completions')
            ->join('lms_steps', 'lms_steps.id', '=', 'lms_step_completions.lms_step_id')
            ->whereIn('lms_step_completions.mitra_id', $mitraIds)
            ->where('lms_steps.aktif', true)
            ->groupBy('lms_step_completions.mitra_id', 'lms_steps.platform')
            ->selectRaw('lms_step_completions.mitra_id, lms_steps.platform, count(*) as done')
            ->get()
            ->groupBy('mitra_id');

        $result = [];
        foreach ($enrolledPlatforms as $mitraId => $platforms) {
            $doneForMitra = $doneCounts->get($mitraId, collect())->keyBy('platform');
            $parts = $platforms->map(function ($p) use ($doneForMitra, $stepCountByPlatform) {
                $total = $stepCountByPlatform[$p->platform] ?? 0;
                $done = $doneForMitra[$p->platform]->done ?? 0;
                $pct = $total > 0 ? (int) round($done / $total * 100) : 0;
                $label = $pct >= 100 ? 'Lengkap' : ($pct > 0 ? 'Proses' : 'Awal');

                return (LmsStep::PLATFORMS[$p->platform] ?? $p->platform).': '.$label;
            });
            $result[$mitraId] = $parts->implode(', ');
        }

        return $result;
    }
}
