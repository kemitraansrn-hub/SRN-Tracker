<?php

namespace App\Http\Controllers;

use App\Models\Mitra;
use App\Models\Order;
use App\Models\TargetBulanan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = Auth::user();
        $now = now();

        $ordersThisMonth = Order::query()
            ->whereYear('tanggal_order', $now->year)
            ->whereMonth('tanggal_order', $now->month)
            ->when(! $user->isAdmin(), fn ($q) => $q->where('orders.kae_code', $user->kae_code));

        $totalOmsetBulanIni = (clone $ordersThisMonth)->sum('total_transaksi');
        $jumlahOrderBulanIni = (clone $ordersThisMonth)->count();
        $mitraAktifBulanIni = (clone $ordersThisMonth)->distinct('mitra_id')->count('mitra_id');

        $totalMitra = Mitra::query()
            ->when(! $user->isAdmin(), fn ($q) => $q->where('kae_code', $user->kae_code))
            ->count();

        $adaTargetBulanIni = TargetBulanan::where('bulan', $now->month)->where('tahun', $now->year)->exists();

        $totalTargetBulanIni = 0;
        $mitraPerluPerhatian = collect();

        if ($adaTargetBulanIni) {
            $targetVsOmset = DB::table('target_bulanan')
                ->join('mitra', 'mitra.id', '=', 'target_bulanan.mitra_id')
                ->leftJoin('orders', function ($join) use ($now) {
                    $join->on('orders.mitra_id', '=', 'mitra.id')
                        ->whereYear('orders.tanggal_order', $now->year)
                        ->whereMonth('orders.tanggal_order', $now->month);
                })
                ->where('target_bulanan.bulan', $now->month)
                ->where('target_bulanan.tahun', $now->year)
                ->when(! $user->isAdmin(), fn ($q) => $q->where('mitra.kae_code', $user->kae_code))
                ->groupBy('mitra.id', 'mitra.nama', 'mitra.kode_mitra', 'target_bulanan.segmen', 'target_bulanan.target')
                ->selectRaw('mitra.id, mitra.nama, mitra.kode_mitra, target_bulanan.segmen, target_bulanan.target, COALESCE(SUM(orders.total_transaksi), 0) as omset')
                ->get()
                ->map(function ($r) {
                    $r->pct = $r->target > 0 ? round($r->omset / $r->target * 100, 1) : 0;

                    return $r;
                });

            $totalTargetBulanIni = $targetVsOmset->sum('target');
            $mitraPerluPerhatian = $targetVsOmset->filter(fn ($r) => $r->pct < 80)->sortBy('pct')->take(10)->values();
        }

        $achievementPct = $totalTargetBulanIni > 0 ? round($totalOmsetBulanIni / $totalTargetBulanIni * 100, 1) : null;

        $omsetPerBrand = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereYear('orders.tanggal_order', $now->year)
            ->whereMonth('orders.tanggal_order', $now->month)
            ->when(! $user->isAdmin(), fn ($q) => $q->where('orders.kae_code', $user->kae_code))
            ->selectRaw('COALESCE(order_items.brand, \'Lainnya\') as brand, SUM(order_items.subtotal) as total')
            ->groupBy('brand')
            ->orderByDesc('total')
            ->get();

        $totalItemOmset = $omsetPerBrand->sum('total');

        $trenMingguan = (clone $ordersThisMonth)
            ->selectRaw('CEIL(DAY(tanggal_order) / 7) as minggu_ke, SUM(total_transaksi) as total')
            ->groupBy('minggu_ke')
            ->orderBy('minggu_ke')
            ->get()
            ->keyBy('minggu_ke');

        $topMitra = (clone $ordersThisMonth)
            ->join('mitra', 'mitra.id', '=', 'orders.mitra_id')
            ->selectRaw('mitra.id, mitra.nama, mitra.kode_mitra, SUM(orders.total_transaksi) as total')
            ->groupBy('mitra.id', 'mitra.nama', 'mitra.kode_mitra')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $orderTerbaru = (clone $ordersThisMonth)->with('mitra')->latest('tanggal_order')->limit(8)->get();

        return view('dashboard', [
            'totalOmsetBulanIni' => $totalOmsetBulanIni,
            'jumlahOrderBulanIni' => $jumlahOrderBulanIni,
            'mitraAktifBulanIni' => $mitraAktifBulanIni,
            'totalMitra' => $totalMitra,
            'adaTargetBulanIni' => $adaTargetBulanIni,
            'totalTargetBulanIni' => $totalTargetBulanIni,
            'achievementPct' => $achievementPct,
            'mitraPerluPerhatian' => $mitraPerluPerhatian,
            'omsetPerBrand' => $omsetPerBrand,
            'totalItemOmset' => $totalItemOmset,
            'trenMingguan' => $trenMingguan,
            'topMitra' => $topMitra,
            'orderTerbaru' => $orderTerbaru,
            'periodeLabel' => $now->translatedFormat('F Y'),
        ]);
    }
}
