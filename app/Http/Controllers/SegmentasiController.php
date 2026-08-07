<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SegmentasiController extends Controller
{
    public function show(Request $request, string $segmen): View
    {
        $user = $request->user();
        $now = now();

        $rows = DB::table('target_bulanan')
            ->join('mitra', 'mitra.id', '=', 'target_bulanan.mitra_id')
            ->leftJoin('orders', function ($join) use ($now) {
                $join->on('orders.mitra_id', '=', 'mitra.id')
                    ->whereYear('orders.tanggal_order', $now->year)
                    ->whereMonth('orders.tanggal_order', $now->month);
            })
            ->where('target_bulanan.bulan', $now->month)
            ->where('target_bulanan.tahun', $now->year)
            ->where('target_bulanan.segmen', $segmen)
            ->when(! $user->isAdmin(), fn ($q) => $q->where('mitra.kae_code', $user->kae_code))
            ->groupBy('mitra.id', 'mitra.nama', 'mitra.kode_mitra', 'mitra.kae_code', 'target_bulanan.target')
            ->selectRaw('mitra.id, mitra.nama, mitra.kode_mitra, mitra.kae_code, target_bulanan.target, COALESCE(SUM(orders.total_transaksi), 0) as omset')
            ->orderByDesc('omset')
            ->get()
            ->map(function ($r) {
                $r->pct = $r->target > 0 ? round($r->omset / $r->target * 100, 1) : 0;

                return $r;
            });

        return view('segmentasi.show', [
            'segmen' => $segmen,
            'mitraList' => $rows,
            'totalOmset' => $rows->sum('omset'),
            'totalTarget' => $rows->sum('target'),
            'periodeLabel' => $now->translatedFormat('F Y'),
        ]);
    }
}
