<?php

namespace App\Http\Controllers;

use App\Models\CpCase;
use App\Models\Mitra;
use App\Models\NewMitraFlag;
use App\Models\Order;
use App\Models\RunRateTarget;
use App\Models\TargetBulanan;
use App\Models\TrendSetting;
use App\Services\RunRateService;
use App\Services\SpecialDealPerformanceService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = Auth::user();

        $bulan = max(1, min(12, (int) $request->input('bulan', now()->month)));
        $tahun = max(2000, min(2100, (int) $request->input('tahun', now()->year)));
        $now = Carbon::create($tahun, $bulan, 1)->startOfMonth();
        $isBulanIni = $now->isSameMonth(today());

        $canViewAll = $user->canViewAll();
        $kaeCode = $canViewAll ? null : $user->kae_code;

        $ordersThisMonth = Order::query()
            ->whereYear('tanggal_order', $now->year)
            ->whereMonth('tanggal_order', $now->month)
            ->when(! $canViewAll, fn ($q) => $q->where('orders.kae_code', $user->kae_code));

        $totalOmsetBulanIni = (clone $ordersThisMonth)->sum('total_transaksi');
        $jumlahOrderBulanIni = (clone $ordersThisMonth)->count();

        $companyTarget = $canViewAll ? RunRateTarget::companyTarget($now->month, $now->year) : null;
        $companyAchPct = $companyTarget ? round($totalOmsetBulanIni / $companyTarget * 100, 1) : null;

        $prevMonthRef = $now->copy()->subMonthNoOverflow();
        // Bulan berjalan: bandingkan sampai hari ini (MTD asli). Bulan lain
        // (histori/masa depan): bandingkan sebulan penuh vs sebulan penuh.
        $dayCap = $isBulanIni ? min(today()->day, $prevMonthRef->daysInMonth) : $prevMonthRef->daysInMonth;
        $mtdLalu = Order::query()
            ->whereBetween('tanggal_order', [
                $prevMonthRef->copy()->startOfMonth(),
                $prevMonthRef->copy()->startOfMonth()->addDays($dayCap - 1)->endOfDay(),
            ])
            ->when(! $canViewAll, fn ($q) => $q->where('kae_code', $user->kae_code))
            ->sum('total_transaksi');
        $mtdGrowthPct = $mtdLalu > 0 ? round((($totalOmsetBulanIni - $mtdLalu) / $mtdLalu) * 100, 1) : null;

        $adaTargetBulanIni = TargetBulanan::where('bulan', $now->month)->where('tahun', $now->year)->exists();

        $tigaTarget = null;
        $specialDealPerformance = collect();
        $reactivationCandidates = collect();

        if ($adaTargetBulanIni) {
            $tigaTarget = DB::table('target_bulanan')
                ->join('mitra', 'mitra.id', '=', 'target_bulanan.mitra_id')
                ->where('target_bulanan.bulan', $now->month)
                ->where('target_bulanan.tahun', $now->year)
                ->when(! $canViewAll, fn ($q) => $q->where('mitra.kae_code', $user->kae_code))
                ->selectRaw('SUM(target_bulanan.komit) as komit, SUM(target_bulanan.target) as target, SUM(target_bulanan.stretch) as stretch')
                ->first();

            $specialDealPerformance = SpecialDealPerformanceService::summary($now->month, $now->year, $kaeCode);
            $reactivationCandidates = SpecialDealPerformanceService::reactivationCandidates($now->month, $now->year, $kaeCode);
        }

        $pencapaianTigaTier = null;
        if ($tigaTarget) {
            $pctFor = fn ($v) => $v > 0 ? round($totalOmsetBulanIni / $v * 100, 1) : null;
            $pencapaianTigaTier = [
                'komit' => ['target' => (float) $tigaTarget->komit, 'pct' => $pctFor((float) $tigaTarget->komit)],
                'target' => ['target' => (float) $tigaTarget->target, 'pct' => $pctFor((float) $tigaTarget->target)],
                'stretch' => ['target' => (float) $tigaTarget->stretch, 'pct' => $pctFor((float) $tigaTarget->stretch)],
            ];
        }

        $runRateTargetBulanan = $canViewAll
            ? $companyTarget
            : RunRateTarget::kaeTarget($now->month, $now->year, $user->kae_code);

        // "Minggu berjalan" cuma relevan buat bulan yang sedang berlangsung —
        // bulan histori/masa depan gak punya highlight minggu aktif.
        $currentWeekLabel = $isBulanIni ? \App\Models\WeekPeriod::resolveWeek(today()) : null;

        // End date of each W1-W4, from the admin-configured Periode
        // Mingguan for this month; falls back to a plain 7-day chunk
        // (day 7/14/21/end-of-month) for any week not configured yet.
        $configuredWeeks = \App\Models\WeekPeriod::forMonth($now->month, $now->year)->keyBy('minggu');
        $daysInMonth = $now->daysInMonth;
        $weekEndDate = [];
        foreach ([1, 2, 3, 4] as $w) {
            $configured = $configuredWeeks->get('W'.$w);
            $weekEndDate[$w] = $configured
                ? $configured->tanggal_selesai->copy()
                : $now->copy()->startOfMonth()->addDays(min($w * 7, $daysInMonth) - 1);
        }

        $runRateWeekly = null;
        if ($runRateTargetBulanan) {
            $weekTarget = $runRateTargetBulanan / 4;
            $runRateWeekly = [];
            foreach ([1, 2, 3, 4] as $w) {
                $cumTarget = round($weekTarget * $w);
                $cumActual = (clone $ordersThisMonth)
                    ->whereDate('tanggal_order', '<=', $weekEndDate[$w]->toDateString())
                    ->sum('total_transaksi');
                $runRateWeekly[$w] = [
                    'target' => $cumTarget,
                    'run_rate' => $cumActual,
                    'growth' => $cumTarget > 0 ? round((($cumActual - $cumTarget) / $cumTarget) * 100, 2) : null,
                ];
            }
        }

        $topMitra = (clone $ordersThisMonth)
            ->join('mitra', 'mitra.id', '=', 'orders.mitra_id')
            ->selectRaw('mitra.id, mitra.nama, mitra.kode_mitra, SUM(orders.total_transaksi) as total')
            ->groupBy('mitra.id', 'mitra.nama', 'mitra.kode_mitra')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $orderTerbaru = (clone $ordersThisMonth)->with('mitra')->latest('tanggal_order')->limit(8)->get();

        $runRate = $canViewAll ? RunRateService::mitraActiveTable($now->year, $now->month) : null;

        $trendCard = null;
        $activeTrend = TrendSetting::active();

        if ($activeTrend) {
            $omsetTrendIni = Order::query()
                ->whereBetween('tanggal_order', [$activeTrend->ini_mulai->copy()->startOfDay(), $activeTrend->ini_selesai->copy()->endOfDay()])
                ->when(! $canViewAll, fn ($q) => $q->where('kae_code', $user->kae_code))
                ->sum('total_transaksi');

            $omsetTrendLalu = Order::query()
                ->whereBetween('tanggal_order', [$activeTrend->lalu_mulai->copy()->startOfDay(), $activeTrend->lalu_selesai->copy()->endOfDay()])
                ->when(! $canViewAll, fn ($q) => $q->where('kae_code', $user->kae_code))
                ->sum('total_transaksi');

            $trendCard = [
                'label' => $activeTrend->label ?: 'Perbandingan Periode',
                'omset_ini' => $omsetTrendIni,
                'growth' => $omsetTrendLalu > 0 ? round((($omsetTrendIni - $omsetTrendLalu) / $omsetTrendLalu) * 100, 2) : null,
            ];
        }

        // Dashboard Development, tim Partnership Compliance — ringkasan kasus
        // Cutting Price bulan yang sama dengan filter Bulan/Tahun di atas.
        // Toko Besar/Kecil ngikutin ambang batas yang sama kayak
        // CpCase::statusToko() (terlaris >= 1000), kasus yang terlaris-nya
        // belum keisi gak masuk ke Besar maupun Kecil.
        $cpBaseQuery = fn () => CpCase::whereYear('tanggal_temuan', $now->year)->whereMonth('tanggal_temuan', $now->month);

        $totalKasusCp = $cpBaseQuery()->count();
        $kasusTokoBesarCount = $cpBaseQuery()->where('terlaris', '>=', 1000)->count();
        $kasusTokoKecilCount = $cpBaseQuery()->whereNotNull('terlaris')->where('terlaris', '<', 1000)->count();
        $kasusTokoBelumDiketahuiCount = $totalKasusCp - $kasusTokoBesarCount - $kasusTokoKecilCount;

        $pctTokoBesar = $totalKasusCp > 0 ? round($kasusTokoBesarCount / $totalKasusCp * 100, 2) : null;
        $pctTokoKecil = $totalKasusCp > 0 ? round($kasusTokoKecilCount / $totalKasusCp * 100, 2) : null;

        $avgPctCpTokoBesar = $kasusTokoBesarCount > 0
            ? round((float) $cpBaseQuery()->where('terlaris', '>=', 1000)->selectRaw('AVG((harga_sop - harga_pelanggaran) / harga_sop * 100) as v')->value('v'), 2)
            : null;
        $avgPctCpTokoKecil = $kasusTokoKecilCount > 0
            ? round((float) $cpBaseQuery()->whereNotNull('terlaris')->where('terlaris', '<', 1000)->selectRaw('AVG((harga_sop - harga_pelanggaran) / harga_sop * 100) as v')->value('v'), 2)
            : null;

        $avgHargaPelanggaranTokoBesar = $kasusTokoBesarCount > 0
            ? round($cpBaseQuery()->where('terlaris', '>=', 1000)->avg('harga_pelanggaran'))
            : null;
        $avgHargaPelanggaranTokoKecil = $kasusTokoKecilCount > 0
            ? round($cpBaseQuery()->whereNotNull('terlaris')->where('terlaris', '<', 1000)->avg('harga_pelanggaran'))
            : null;

        $platformBreakdownCp = $cpBaseQuery()
            ->select('platform', DB::raw('COUNT(*) as jumlah'))
            ->groupBy('platform')
            ->orderByDesc('jumlah')
            ->get();
        $topPlatformCp = $platformBreakdownCp->first()?->platform;

        $topSkuCp = $cpBaseQuery()
            ->whereNotNull('produk_id')
            ->join('produk', 'produk.id', '=', 'cp_cases.produk_id')
            ->select('produk.nama as nama_produk', DB::raw('COUNT(*) as jumlah'))
            ->groupBy('produk.id', 'produk.nama')
            ->orderByDesc('jumlah')
            ->limit(5)
            ->get();

        return view('dashboard', [
            'trendCard' => $trendCard,
            'companyTarget' => $companyTarget,
            'companyAchPct' => $companyAchPct,
            'mtdIni' => $totalOmsetBulanIni,
            'mtdLalu' => $mtdLalu,
            'mtdGrowthPct' => $mtdGrowthPct,
            'dayCap' => $dayCap,
            'totalOmsetBulanIni' => $totalOmsetBulanIni,
            'jumlahOrderBulanIni' => $jumlahOrderBulanIni,
            'adaTargetBulanIni' => $adaTargetBulanIni,
            'pencapaianTigaTier' => $pencapaianTigaTier,
            'specialDealPerformance' => $specialDealPerformance,
            'reactivationCandidates' => $reactivationCandidates,
            'bulanIni' => $now->month,
            'tahunIni' => $now->year,
            'isBulanIni' => $isBulanIni,
            'runRateWeekly' => $runRateWeekly,
            'runRateWeekEndDate' => $weekEndDate,
            'currentWeekLabel' => $currentWeekLabel,
            'topMitra' => $topMitra,
            'orderTerbaru' => $orderTerbaru,
            'runRate' => $runRate,
            'periodeLabel' => $now->translatedFormat('F Y'),
            'totalKasusCp' => $totalKasusCp,
            'kasusTokoBesarCount' => $kasusTokoBesarCount,
            'kasusTokoKecilCount' => $kasusTokoKecilCount,
            'kasusTokoBelumDiketahuiCount' => $kasusTokoBelumDiketahuiCount,
            'pctTokoBesar' => $pctTokoBesar,
            'pctTokoKecil' => $pctTokoKecil,
            'avgPctCpTokoBesar' => $avgPctCpTokoBesar,
            'avgPctCpTokoKecil' => $avgPctCpTokoKecil,
            'avgHargaPelanggaranTokoBesar' => $avgHargaPelanggaranTokoBesar,
            'avgHargaPelanggaranTokoKecil' => $avgHargaPelanggaranTokoKecil,
            'platformBreakdownCp' => $platformBreakdownCp,
            'topPlatformCp' => $topPlatformCp,
            'topSkuCp' => $topSkuCp,
        ]);
    }

    public function toggleNewMitra(Request $request, Mitra $mitra): RedirectResponse
    {
        $bulan = (int) $request->input('bulan');
        $tahun = (int) $request->input('tahun');

        $flag = NewMitraFlag::where('mitra_id', $mitra->id)->where('bulan', $bulan)->where('tahun', $tahun)->first();

        if ($flag) {
            $flag->delete();
        } else {
            NewMitraFlag::create([
                'mitra_id' => $mitra->id,
                'bulan' => $bulan,
                'tahun' => $tahun,
                'flagged_by' => $request->user()->id,
            ]);
        }

        return redirect()->route('dashboard');
    }
}
