<?php

namespace App\Http\Controllers;

use App\Models\Mitra;
use App\Models\MitraSnapshot;
use App\Models\TargetBulanan;
use App\Models\User;
use App\Services\AchievementStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Data Development": end-of-month snapshot of Pareto & RTP mitra whose
 * achievement vs target fell below 100% ("Kurang Belanja") or spiked above
 * 120% ("Warning"). Snapshots are saved manually (no background scheduler
 * on this local setup) so the numbers are frozen as of when the admin hit
 * "Simpan Snapshot", not recomputed live on every page view.
 */
class DataDevelopmentController extends Controller
{
    public function index(Request $request): View
    {
        $bulan = (int) $request->input('bulan', now()->month);
        $tahun = (int) $request->input('tahun', now()->year);

        $snapshots = MitraSnapshot::with('mitra')
            ->where('bulan', $bulan)->where('tahun', $tahun)
            ->orderBy('pct')
            ->get();

        return view('data-development.index', [
            'bulan' => $bulan,
            'tahun' => $tahun,
            'periodeLabel' => \Carbon\Carbon::create($tahun, $bulan)->translatedFormat('F Y'),
            'kurang' => $snapshots->where('status', 'kurang')->values(),
            'warning' => $snapshots->where('status', 'warning')->values(),
            'lastSnapshot' => $snapshots->max('created_at'),
        ]);
    }

    public function download(Request $request): StreamedResponse
    {
        $bulan = (int) $request->input('bulan', now()->month);
        $tahun = (int) $request->input('tahun', now()->year);
        $periodeLabel = \Carbon\Carbon::create($tahun, $bulan)->translatedFormat('F Y');

        // KAE resolved from the mitra's *current* kae_code, not the one
        // frozen in the snapshot — a mitra's KAE can get reassigned after
        // a snapshot was saved (e.g. a correction), and the report should
        // reflect who owns them now, not a stale code from save-time.
        $snapshots = MitraSnapshot::with('mitra:id,kae_code')->where('bulan', $bulan)->where('tahun', $tahun)->orderBy('pct')->get();
        $kaeNameMap = User::kaeNameMap();

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        $sheets = [
            'kurang' => ['title' => 'Kurang dari Target', 'label' => 'Kurang'],
            'warning' => ['title' => 'Warning', 'label' => 'Warning'],
        ];

        foreach ($sheets as $status => $meta) {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($meta['title']);

            $headers = ['Kode Mitra', 'Nama Mitra', 'KAE', 'Segmen', 'Target Bulan', 'Realisasi Bulan', '% vs Target', 'Status'];
            // $strictNullComparison=true is required here: fromArray()'s
            // default loose comparison treats a genuine 0 value the same
            // as "leave this cell blank" (0 == null in PHP), which was
            // silently dropping Rp0 realisasi / 0% cells from the export.
            $sheet->fromArray($headers, null, 'A1', true);
            $sheet->getStyle('A1:H1')->getFont()->setBold(true);

            $rows = $snapshots->where('status', $status)->values();
            $r = 2;
            foreach ($rows as $s) {
                $kaeCode = $s->mitra->kae_code ?? $s->kae_code;
                $label = $status === 'kurang'
                    ? AchievementStatus::label(AchievementStatus::resolveWeeklyPlan((float) $s->realisasi_bulan, (float) $s->target_bulan, (float) $s->pct))
                    : $meta['label'];
                $sheet->fromArray([
                    $s->kode_mitra,
                    $s->nama,
                    $kaeCode ? ($kaeNameMap[$kaeCode] ?? $kaeCode) : '—',
                    $s->segmen,
                    (float) $s->target_bulan,
                    (float) $s->realisasi_bulan,
                    (float) $s->pct,
                    $label,
                ], null, 'A'.$r, true);
                $r++;
            }

            foreach (range('A', 'H') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
        }

        $filename = 'Data Development '.str_replace(' ', '_', $periodeLabel).'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $bulan = (int) $request->input('bulan', now()->month);
        $tahun = (int) $request->input('tahun', now()->year);
        $targetSql = TargetBulanan::effectiveTargetSql();

        $rows = DB::table('target_bulanan')
            ->join('mitra', 'mitra.id', '=', 'target_bulanan.mitra_id')
            ->leftJoin('orders', function ($join) use ($bulan, $tahun) {
                $join->on('orders.mitra_id', '=', 'mitra.id')
                    ->whereYear('orders.tanggal_order', $tahun)
                    ->whereMonth('orders.tanggal_order', $bulan);
            })
            ->where('target_bulanan.bulan', $bulan)
            ->where('target_bulanan.tahun', $tahun)
            ->where(function ($q) {
                $q->where('target_bulanan.segmen', 'PARETO')
                    ->orWhere('target_bulanan.segmen', 'like', 'RTP%');
            })
            ->groupBy('mitra.id', 'mitra.nama', 'mitra.kode_mitra', 'mitra.kae_code', 'target_bulanan.segmen', 'target_bulanan.komit', 'target_bulanan.target', 'target_bulanan.stretch', 'target_bulanan.tier_dipakai')
            ->selectRaw("mitra.id as mitra_id, mitra.nama, mitra.kode_mitra, mitra.kae_code, target_bulanan.segmen, $targetSql as target, COALESCE(SUM(orders.total_transaksi), 0) as omset")
            ->get();

        DB::transaction(function () use ($rows, $bulan, $tahun, $request) {
            MitraSnapshot::where('bulan', $bulan)->where('tahun', $tahun)->delete();

            foreach ($rows as $r) {
                if ($r->target <= 0) {
                    continue;
                }

                $pct = round($r->omset / $r->target * 100, 1);

                if ($pct >= 100 && $pct <= 120) {
                    continue;
                }

                MitraSnapshot::create([
                    'mitra_id' => $r->mitra_id,
                    'bulan' => $bulan,
                    'tahun' => $tahun,
                    'kode_mitra' => $r->kode_mitra,
                    'nama' => $r->nama,
                    'kae_code' => $r->kae_code,
                    'segmen' => $r->segmen,
                    'target_bulan' => $r->target,
                    'realisasi_bulan' => $r->omset,
                    'pct' => $pct,
                    'status' => $pct < 100 ? 'kurang' : 'warning',
                    'created_by' => $request->user()->id,
                ]);
            }
        });

        $periode = \Carbon\Carbon::create($tahun, $bulan)->translatedFormat('F Y');

        return redirect()->route('data-development.index', ['bulan' => $bulan, 'tahun' => $tahun])
            ->with('status', 'Snapshot '.$periode.' berhasil disimpan.');
    }
}
