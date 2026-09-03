<?php

namespace App\Http\Controllers;

use App\Models\Mitra;
use App\Services\MitraPoinService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PoinController extends Controller
{
    private const RUPIAH_PER_POIN = 1650;

    public function index(Request $request): View
    {
        $user = $request->user();
        $tahun = (int) $request->input('tahun', now()->year);

        $mitraList = Mitra::query()
            ->when($user->role === 'kae', fn ($q) => $q->where('kae_code', $user->kae_code))
            ->orderBy('nama')
            ->get(['id', 'nama', 'kode_mitra']);

        $poinByMitra = MitraPoinService::bulkForYear($mitraList->pluck('id')->all(), $tahun);

        $rows = $mitraList->map(fn ($m) => (object) [
            'mitra' => $m,
            'monthly' => $poinByMitra->get($m->id)['monthly'] ?? array_fill(1, 12, 0),
            'total' => $poinByMitra->get($m->id)['total'] ?? 0,
        ])->sortByDesc('total')->values();

        $grandTotal = $rows->sum('total');

        return view('poin.index', [
            'tahun' => $tahun,
            'rows' => $rows,
            'monthTotals' => collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => $rows->sum(fn ($r) => $r->monthly[$m])]),
            'grandTotal' => $grandTotal,
            'rupiahPerPoin' => self::RUPIAH_PER_POIN,
            'budgetTotal' => $grandTotal * self::RUPIAH_PER_POIN,
        ]);
    }
}
