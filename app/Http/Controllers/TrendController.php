<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\TrendSetting;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
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
            'activeSetting' => TrendSetting::active(),
        ]);
    }

    public function saveDashboardCard(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:60'],
            'ini_mulai' => ['required', 'date'],
            'ini_selesai' => ['required', 'date', 'after_or_equal:ini_mulai'],
            'lalu_mulai' => ['required', 'date'],
            'lalu_selesai' => ['required', 'date', 'after_or_equal:lalu_mulai'],
        ]);

        TrendSetting::create([
            ...$data,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('trend.index', $request->only(['ini_mulai', 'ini_selesai', 'lalu_mulai', 'lalu_selesai']))
            ->with('status', 'Card perbandingan berhasil ditampilkan di Dashboard.');
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
