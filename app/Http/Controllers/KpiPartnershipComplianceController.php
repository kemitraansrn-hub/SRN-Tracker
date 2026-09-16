<?php

namespace App\Http\Controllers;

use App\Models\CpCase;
use Illuminate\Http\Request;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        [$kpis, $totalKasus] = $this->computeKpis($bulan, $tahun);

        return view('kpi-partnership-compliance.index', [
            'kpis' => $kpis,
            'totalKasus' => $totalKasus,
            'bulan' => $bulan,
            'tahun' => $tahun,
            'isBulanIni' => $bulan === now()->month && $tahun === now()->year,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $bulan = max(1, min(12, (int) $request->input('bulan', now()->month)));
        $tahun = max(2000, min(2100, (int) $request->input('tahun', now()->year)));
        $bulanNama = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'][$bulan];

        [$kpis, $totalKasus] = $this->computeKpis($bulan, $tahun);

        $headers = ['No', 'KPI', 'Bobot', 'Target', 'Realisasi', 'Tercapai', 'Numerator', 'Denominator', 'Keterangan'];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('KPI Compliance');
        $sheet->setCellValue('A1', 'KPI Partnership Compliance — '.$bulanNama.' '.$tahun.' ('.$totalKasus.' kasus tercatat)');
        $sheet->mergeCells('A1:I1');
        $sheet->getStyle('A1')->getFont()->setBold(true);

        $sheet->fromArray($headers, null, 'A3');
        $sheet->getStyle('A3:I3')->getFont()->setBold(true);
        $sheet->getStyle('A3:I3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F2F5F9');
        foreach ([5, 26, 8, 10, 10, 10, 14, 24, 60] as $col => $width) {
            $sheet->getColumnDimensionByColumn($col + 1)->setWidth($width);
        }

        $row = 4;
        foreach ($kpis as $kpi) {
            $sheet->fromArray([
                $kpi['no'],
                $kpi['nama'],
                $kpi['bobot'].'%',
                $kpi['target_label'],
                $kpi['realisasi'] !== null ? $kpi['realisasi'].'%' : '—',
                $kpi['tercapai'] ? 'Ya' : 'Tidak',
                $kpi['numerator'].' '.$kpi['numerator_label'],
                $kpi['denominator'].' '.$kpi['denominator_label'],
                $kpi['keterangan'],
            ], null, 'A'.$row);
            $row++;
        }

        $filename = 'kpi-partnership-compliance_'.$tahun.'-'.str_pad((string) $bulan, 2, '0', STR_PAD_LEFT).'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: int}
     */
    private function computeKpis(int $bulan, int $tahun): array
    {
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
                'rumus' => 'Jumlah kasus dengan Mitra teridentifikasi ÷ Total kasus bulan ini',
                'numerator' => $mitraTeridentifikasi,
                'denominator' => $totalKasus,
                'numerator_label' => 'kasus mitra teridentifikasi',
                'denominator_label' => 'total kasus',
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
                'rumus' => 'Jumlah kasus Case Closed ÷ (Case Closed + Take Down) — kasus yang masih Progres/berjalan gak ikut dihitung',
                'numerator' => $caseClosedCount,
                'denominator' => $caseClosedCount + $takenDownCount,
                'numerator_label' => 'kasus Case Closed',
                'denominator_label' => 'kasus Case Closed + Take Down',
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
                'rumus' => 'Jumlah kasus dengan Follow Up 1 dilakukan maks H+1 dari Tanggal Temuan ÷ Total kasus bulan ini',
                'numerator' => $slaOnTimeCount,
                'denominator' => $totalKasus,
                'numerator_label' => 'kasus FU 1 on-time (≤ H+1)',
                'denominator_label' => 'total kasus',
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
                'rumus' => 'Jumlah kasus dengan Bukti Temuan (wajib selalu) & Bukti Case Close (wajib kalau udah Case Closed) lengkap ÷ Total kasus bulan ini',
                'numerator' => $dokumentasiLengkapCount,
                'denominator' => $totalKasus,
                'numerator_label' => 'kasus dokumentasi lengkap',
                'denominator_label' => 'total kasus',
            ],
        ];

        foreach ($kpis as &$kpi) {
            $kpi['tercapai'] = $kpi['realisasi'] !== null
                && ($kpi['target_op'] === '>' ? $kpi['realisasi'] > $kpi['target'] : $kpi['realisasi'] >= $kpi['target']);
        }
        unset($kpi);

        return [$kpis, $totalKasus];
    }
}
