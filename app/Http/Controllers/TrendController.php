<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrendController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $today = now();

        $iniMulai = $request->input('ini_mulai', $today->copy()->startOfMonth()->toDateString());
        $iniSelesai = $request->input('ini_selesai', $today->toDateString());
        $laluMulai = $request->input('lalu_mulai', $today->copy()->subMonthNoOverflow()->startOfMonth()->toDateString());
        $laluSelesai = $request->input('lalu_selesai', $today->copy()->subMonthNoOverflow()->toDateString());

        $error = null;

        if ($iniSelesai < $iniMulai || $laluSelesai < $laluMulai) {
            $error = 'Tanggal selesai tidak boleh lebih awal dari tanggal mulai.';
        }

        $result = null;

        if (! $error) {
            $omsetIni = $this->omsetPeriode($user, $iniMulai, $iniSelesai);
            $omsetLalu = $this->omsetPeriode($user, $laluMulai, $laluSelesai);

            $growth = $omsetLalu > 0
                ? round((($omsetIni - $omsetLalu) / $omsetLalu) * 100, 2)
                : null;

            $result = [
                'omset_ini' => $omsetIni,
                'omset_lalu' => $omsetLalu,
                'growth' => $growth,
            ];
        }

        return view('trend.index', [
            'iniMulai' => $iniMulai,
            'iniSelesai' => $iniSelesai,
            'laluMulai' => $laluMulai,
            'laluSelesai' => $laluSelesai,
            'result' => $result,
            'error' => $error,
        ]);
    }

    private function omsetPeriode($user, string $mulai, string $selesai): float
    {
        return (float) Order::query()
            ->whereBetween('tanggal_order', [
                Carbon::parse($mulai)->startOfDay(),
                Carbon::parse($selesai)->endOfDay(),
            ])
            ->when(! $user->isAdmin(), fn ($q) => $q->where('kae_code', $user->kae_code))
            ->sum('total_transaksi');
    }
}
