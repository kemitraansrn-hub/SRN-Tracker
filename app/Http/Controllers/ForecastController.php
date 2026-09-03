<?php

namespace App\Http\Controllers;

use App\Models\ForecastRo;
use App\Models\Mitra;
use App\Models\RunRateTarget;
use App\Models\TargetBulanan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ForecastController extends Controller
{
    private const SEGMEN_LIST = ['PARETO', 'RTP (ROAD TO PARETO)', 'REGULER', 'SPECIAL REGULER'];

    /** Semua segmen sekarang breakdown Plan RO-nya per mitra (bukan cuma agregat per segmen). */
    private const MITRA_LEVEL_SEGMEN = ['PARETO', 'RTP (ROAD TO PARETO)', 'REGULER', 'SPECIAL REGULER'];

    public function index(Request $request): View
    {
        $bulan = (int) $request->input('bulan', now()->month);
        $tahun = (int) $request->input('tahun', now()->year);
        $targetSql = TargetBulanan::effectiveTargetSql();

        // Target + Ach saat ini, per segmen — mitra_id dibawa serta supaya
        // dropdown "Mitra" di form PARETO bisa langsung diambil dari sini.
        $mitraRows = DB::table('target_bulanan')
            ->join('mitra', 'mitra.id', '=', 'target_bulanan.mitra_id')
            ->leftJoin('orders', function ($join) use ($bulan, $tahun) {
                $join->on('orders.mitra_id', '=', 'mitra.id')
                    ->whereYear('orders.tanggal_order', $tahun)
                    ->whereMonth('orders.tanggal_order', $bulan);
            })
            ->where('target_bulanan.bulan', $bulan)
            ->where('target_bulanan.tahun', $tahun)
            ->whereIn('target_bulanan.segmen', self::SEGMEN_LIST)
            ->groupBy('mitra.id', 'mitra.nama', 'mitra.kae_code', 'target_bulanan.segmen', 'target_bulanan.komit', 'target_bulanan.target', 'target_bulanan.stretch', 'target_bulanan.tier_dipakai')
            ->selectRaw("mitra.id as mitra_id, mitra.nama, mitra.kae_code, target_bulanan.segmen, $targetSql as target, COALESCE(SUM(orders.total_transaksi), 0) as omset")
            ->get();

        $entries = ForecastRo::where('bulan', $bulan)->where('tahun', $tahun)
            ->with('mitra:id,nama,kode_mitra')
            ->orderBy('segmen')
            ->orderBy('tanggal_plan_ro')
            ->get();

        // Realisasi dicocokkan per entry ke order asli mitra itu dari
        // tanggal_plan_ro sampai +1 hari sesudahnya (toleransi telat
        // sehari, bukan lebih awal) — bukan total omset sebulan, karena 1
        // mitra bisa punya beberapa Plan RO dengan tanggal beda-beda.
        // Kalau mitra itu punya Plan RO lain sesudahnya, jendela
        // toleransinya dipotong sebelum tanggal plan berikutnya biar 1
        // order asli tidak ke-klaim 2 plan sekaligus.
        $mitraIds = $entries->pluck('mitra_id')->filter()->unique()->values();

        $ordersByMitraTanggal = $mitraIds->isEmpty() ? collect() : DB::table('orders')
            ->whereIn('mitra_id', $mitraIds)
            ->select('mitra_id', DB::raw('DATE(tanggal_order) as tgl'), DB::raw('SUM(total_transaksi) as total'))
            ->groupBy('mitra_id', 'tgl')
            ->get()
            ->groupBy('mitra_id');

        $entriesByMitra = $entries->whereNotNull('mitra_id')->groupBy('mitra_id');

        $entries = $entries->map(function ($e) use ($ordersByMitraTanggal, $entriesByMitra) {
            if (! $e->mitra_id) {
                $e->realisasi = null;
                $e->tanggal_realisasi = collect();
                $e->tercapai = null;

                return $e;
            }

            $siblingDates = $entriesByMitra->get($e->mitra_id)
                ->pluck('tanggal_plan_ro')
                ->reject(fn ($d) => $d->equalTo($e->tanggal_plan_ro))
                ->sort();
            $nextDate = $siblingDates->first(fn ($d) => $d->greaterThan($e->tanggal_plan_ro));

            // Realisasi = order BARU yang masuk setelah Plan RO ini
            // diajukan (created_at), bukan omset sebulan penuh — supaya
            // order lama yang sudah terjadi sebelum plan-nya dibuat tidak
            // dianggap "sudah tercapai" secara retroaktif. Kalau ada plan
            // berikutnya untuk mitra yang sama, jendela ditutup di H-1
            // tanggal plan itu; kalau tidak, toleransi telat 1 hari dari
            // tanggal plan sekarang.
            $windowStart = $e->created_at->copy()->startOfDay();
            $windowEnd = $nextDate ? $nextDate->copy()->subDay() : $e->tanggal_plan_ro->copy()->addDay();

            $matchedOrders = $ordersByMitraTanggal->get($e->mitra_id, collect())
                ->filter(fn ($r) => $r->tgl >= $windowStart->toDateString() && $r->tgl <= $windowEnd->toDateString());

            $e->realisasi = (float) $matchedOrders->sum('total');
            $e->tanggal_realisasi = $matchedOrders->pluck('tgl')->sort()->values();
            $e->tercapai = $e->realisasi >= $e->plan_ro;

            return $e;
        });

        $totalPlanRoBySegmen = $entries->groupBy('segmen')->map->sum('plan_ro');

        // Best Estimate should count a Plan RO only once it's *not yet*
        // realized on its own planned date — a fully-realized entry is
        // dropped (that revenue is already inside "Ach"); a partial one
        // only contributes the remaining gap. Segmen-level entries (no
        // mitra) can't be date-matched, so they still count in full.
        $unrealizedPlanRoBySegmen = $entries->groupBy('segmen')->map(
            fn ($segEntries) => $segEntries->sum(fn ($e) => $e->realisasi !== null
                ? max($e->plan_ro - $e->realisasi, 0)
                : $e->plan_ro)
        );

        $summary = collect(self::SEGMEN_LIST)->map(function ($segmen) use ($mitraRows, $totalPlanRoBySegmen, $unrealizedPlanRoBySegmen) {
            $rows = $mitraRows->where('segmen', $segmen);
            $target = (float) $rows->sum('target');
            $ach = (float) $rows->sum('omset');
            $totalPlanRo = (float) ($totalPlanRoBySegmen->get($segmen) ?? 0);
            $unrealizedPlanRo = (float) ($unrealizedPlanRoBySegmen->get($segmen) ?? 0);
            $bestEstimate = $ach + $unrealizedPlanRo;

            return [
                'segmen' => $segmen,
                'target' => $target,
                'ach' => $ach,
                'total_plan_ro' => $totalPlanRo,
                'best_estimate' => $bestEstimate,
                'best_estimate_pct' => $target > 0 ? round($bestEstimate / $target * 100, 1) : null,
            ];
        });

        // Orders from mitra with no Target Bulanan row this month fall
        // outside every segmen bucket above (Ach there is joined off
        // target_bulanan), so their real sales would otherwise vanish from
        // this page's total instead of just being untracked-against-target
        // — shown as its own row so the grand total still matches the
        // dashboard's unconditional order sum.
        $untargetedAch = (float) DB::table('orders')
            ->whereYear('tanggal_order', $tahun)
            ->whereMonth('tanggal_order', $bulan)
            ->whereNotIn('mitra_id', $mitraRows->pluck('mitra_id')->unique())
            ->sum('total_transaksi');

        if ($untargetedAch > 0) {
            $summary->push([
                'segmen' => 'BELUM DITARGET',
                'target' => 0,
                'ach' => $untargetedAch,
                'total_plan_ro' => 0,
                'best_estimate' => $untargetedAch,
                'best_estimate_pct' => null,
            ]);
        }

        $totalRow = [
            'segmen' => 'Total Semua Segmen',
            'target' => $summary->sum('target'),
            'ach' => $summary->sum('ach'),
            'total_plan_ro' => $summary->sum('total_plan_ro'),
            'best_estimate' => $summary->sum('best_estimate'),
            'best_estimate_pct' => $summary->sum('target') > 0 ? round($summary->sum('best_estimate') / $summary->sum('target') * 100, 1) : null,
        ];

        $user = $request->user();
        $mitraOptionsBySegmen = collect(self::MITRA_LEVEL_SEGMEN)->mapWithKeys(fn ($seg) => [
            $seg => $mitraRows->where('segmen', $seg)
                ->when(! $user->isAdmin(), fn ($rows) => $rows->where('kae_code', $user->kae_code))
                ->sortBy('nama')
                ->map(fn ($r) => ['id' => $r->mitra_id, 'nama' => $r->nama])
                ->values(),
        ]);

        $companyTarget = RunRateTarget::companyTarget($bulan, $tahun);
        $bestEstimateVsCompany = $companyTarget ? [
            'target' => $companyTarget,
            'best_estimate' => $totalRow['best_estimate'],
            'pct' => round($totalRow['best_estimate'] / $companyTarget * 100, 1),
        ] : null;

        return view('forecast.index', [
            'bulan' => $bulan,
            'tahun' => $tahun,
            'periodeLabel' => \Carbon\Carbon::create($tahun, $bulan)->translatedFormat('F Y'),
            'summary' => $summary,
            'totalRow' => $totalRow,
            'entries' => $entries,
            'mitraOptionsBySegmen' => $mitraOptionsBySegmen,
            'segmenList' => self::SEGMEN_LIST,
            'bestEstimateVsCompany' => $bestEstimateVsCompany,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'bulan' => ['required', 'integer', 'between:1,12'],
            'tahun' => ['required', 'integer', 'between:2020,2100'],
            'segmen' => ['required', 'in:'.implode(',', self::SEGMEN_LIST)],
            'mitra_id' => [
                'nullable', 'required_if:segmen,'.implode(',', self::MITRA_LEVEL_SEGMEN), 'exists:mitra,id',
                function ($attribute, $value, $fail) use ($user) {
                    if ($value && ! $user->isAdmin() && Mitra::where('id', $value)->where('kae_code', $user->kae_code)->doesntExist()) {
                        $fail('Mitra ini bukan mitra kamu.');
                    }
                },
            ],
            'tanggal_plan_ro' => ['required', 'date'],
            'plan_ro' => ['required', 'integer', 'min:0'],
        ]);

        if (! in_array($data['segmen'], self::MITRA_LEVEL_SEGMEN, true)) {
            $data['mitra_id'] = null;
        }

        $data['created_by'] = $user->id;

        ForecastRo::create($data);

        return redirect()->route('forecast.index', ['bulan' => $data['bulan'], 'tahun' => $data['tahun']])
            ->with('status', 'Plan RO berhasil ditambahkan.');
    }

    public function destroy(Request $request, ForecastRo $forecastRo): RedirectResponse
    {
        $user = $request->user();

        if (! $user->isAdmin() && $forecastRo->created_by !== $user->id) {
            throw new HttpException(403, 'Kamu tidak bisa menghapus Plan RO yang dibuat orang lain.');
        }

        $bulan = $forecastRo->bulan;
        $tahun = $forecastRo->tahun;
        $forecastRo->delete();

        return redirect()->route('forecast.index', ['bulan' => $bulan, 'tahun' => $tahun])
            ->with('status', 'Plan RO berhasil dihapus.');
    }
}
