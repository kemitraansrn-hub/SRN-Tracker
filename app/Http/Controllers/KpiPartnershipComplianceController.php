<?php

namespace App\Http\Controllers;

use App\Models\CpCase;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * KPI Partnership Compliance — scorecard bulanan buat tim Compliance,
 * dihitung langsung dari data Tracking CP (bukan input manual). 4 KPI,
 * masing-masing bobot 25%, formula & batas SLA sudah disepakati:
 * - Identifikasi Mitra: mitra_id keisi / total kasus bulan itu.
 * - Penyelesaian Kasus: Case Closed / (Case Closed + beneran Take Down)
 *   — kasus yang masih Progres/Pengajuan Takedown gak ikut jadi pembagi
 *   soalnya belum "selesai".
 * - SLA Follow Up: FU 1 dilakukan maks H+1 dari tanggal_temuan.
 * - Kelengkapan Dokumentasi: bukti_temuan wajib selalu, bukti_case_close
 *   cuma wajib kalau kasusnya udah Case Closed.
 */
class KpiPartnershipComplianceController extends Controller
{
    public function index(Request $request): View
    {
        $bulan = max(1, min(12, (int) $request->input('bulan', now()->month)));
        $tahun = max(2000, min(2100, (int) $request->input('tahun', now()->year)));

        $baseQuery = fn () => CpCase::whereYear('tanggal_temuan', $tahun)->whereMonth('tanggal_temuan', $bulan);

        $totalKasus = $baseQuery()->count();
        $pct = fn (int $n, int $d) => $d > 0 ? round($n / $d * 100, 1) : null;

        $mitraTeridentifikasi = $baseQuery()->whereNotNull('mitra_id')->count();

        $caseClosedCount = $baseQuery()->where('status_kasus', 'Case Closed')->count();
        $takenDownCount = $baseQuery()->whereHas('takedownBanding')->count();

        $slaOnTimeCount = $baseQuery()
            ->whereNotNull('follow_up_1_tanggal')
            ->whereRaw('DATEDIFF(follow_up_1_tanggal, tanggal_temuan) <= 1')
            ->count();

        $dokumentasiLengkapCount = $baseQuery()
            ->whereNotNull('bukti_temuan')
            ->where(fn ($q) => $q->where('status_kasus', '!=', 'Case Closed')->orWhereNotNull('bukti_case_close'))
            ->count();

        $kpis = [
            [
                'no' => 1,
                'nama' => 'Identifikasi Mitra',
                'bobot' => 25,
                'target_label' => '>90%',
                'target' => 90,
                'target_op' => '>',
                'realisasi' => $pct($mitraTeridentifikasi, $totalKasus),
                'keterangan' => 'Mengukur kemampuan tim menemukan identitas mitra dari link pelanggaran yang ditemukan.',
            ],
            [
                'no' => 2,
                'nama' => 'Penyelesaian Kasus',
                'bobot' => 25,
                'target_label' => '>75%',
                'target' => 75,
                'target_op' => '>',
                'realisasi' => $pct($caseClosedCount, $caseClosedCount + $takenDownCount),
                'keterangan' => 'Mengukur efektivitas follow up tim dalam membuat mitra menaikkan harga sesuai SOP tanpa perlu take down.',
            ],
            [
                'no' => 3,
                'nama' => 'SLA Follow Up',
                'bobot' => 25,
                'target_label' => '>95%',
                'target' => 95,
                'target_op' => '>',
                'realisasi' => $pct($slaOnTimeCount, $totalKasus),
                'keterangan' => 'Mengukur kecepatan tim dalam melakukan follow up terhadap pelanggaran yang ditemukan.',
            ],
            [
                'no' => 4,
                'nama' => 'Kelengkapan Dokumentasi',
                'bobot' => 25,
                'target_label' => '100%',
                'target' => 100,
                'target_op' => '>=',
                'realisasi' => $pct($dokumentasiLengkapCount, $totalKasus),
                'keterangan' => 'Mengukur kedisiplinan tim dalam melengkapi dokumentasi before-after setiap kasus.',
            ],
        ];

        foreach ($kpis as &$kpi) {
            $kpi['tercapai'] = $kpi['realisasi'] !== null
                && ($kpi['target_op'] === '>' ? $kpi['realisasi'] > $kpi['target'] : $kpi['realisasi'] >= $kpi['target']);
        }

        return view('kpi-partnership-compliance.index', [
            'kpis' => $kpis,
            'totalKasus' => $totalKasus,
            'bulan' => $bulan,
            'tahun' => $tahun,
            'isBulanIni' => $bulan === now()->month && $tahun === now()->year,
        ]);
    }
}
