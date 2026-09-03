<?php

namespace App\Http\Controllers;

use App\Models\HistoricalOmsetBulanan;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * "Omset Bulanan": total omset per bulan dalam 1 tahun, dengan pertumbuhan
 * month-over-month (bulan sebelumnya) dan year-over-year (bulan yang sama
 * tahun lalu). Tahun yang belum punya data order asli (mis. sebelum 2026)
 * jatuh balik ke historical_omset_bulanan — angka total perusahaan yang
 * diinput manual dari rekap lama, tidak punya breakdown per KAE.
 */
class OmsetBulananController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $tahun = (int) $request->input('tahun', now()->year);

        $kaeCode = $user->role === 'kae'
            ? $user->kae_code
            : $request->input('kae_code');

        $iniAdaDataAsli = Order::whereYear('tanggal_order', $tahun)->exists();
        $laluAdaDataAsli = Order::whereYear('tanggal_order', $tahun - 1)->exists();

        $omsetIni = $this->omsetPerBulan($tahun, $kaeCode, $iniAdaDataAsli);
        $omsetLalu = $this->omsetPerBulan($tahun - 1, $kaeCode, $laluAdaDataAsli);

        // Untuk growth Januari, butuh Desember tahun sebelumnya — sumbernya
        // ikut $omsetLalu (asli kalau tahun lalu ada data order, historis
        // kalau tidak), bukan selalu dari tabel yang sama dengan $omsetIni.
        $rows = collect();
        $prevOmset = $omsetLalu->get(12);

        for ($bulan = 1; $bulan <= 12; $bulan++) {
            $omset = $omsetIni->get($bulan);
            $omsetTahunLalu = $omsetLalu->get($bulan);

            $mom = ($omset !== null && $prevOmset !== null && $prevOmset > 0)
                ? round((($omset - $prevOmset) / $prevOmset) * 100, 1)
                : null;

            $yoy = ($omset !== null && $omsetTahunLalu !== null && $omsetTahunLalu > 0)
                ? round((($omset - $omsetTahunLalu) / $omsetTahunLalu) * 100, 1)
                : null;

            $rows->push([
                'bulan' => $bulan,
                'label' => \Carbon\Carbon::create($tahun, $bulan, 1)->translatedFormat('F'),
                'omset' => $omset,
                'omset_tahun_lalu' => $omsetTahunLalu,
                'mom' => $mom,
                'yoy' => $yoy,
            ]);

            $prevOmset = $omset;
        }

        $maxOmset = $rows->pluck('omset')->filter(fn ($v) => $v !== null)->max() ?: 0;
        $totalOmset = $rows->pluck('omset')->filter(fn ($v) => $v !== null)->sum();

        // YTD = sejauh mana $tahun sudah ada datanya (bulan terakhir yang
        // terisi) — biar perbandingan omset tahun lalu adil, bukan bandingin
        // tahun berjalan yang baru sebagian vs tahun lalu yang sudah full 12
        // bulan. Otomatis ikut kalender: tahun depan bulan terakhirnya juga
        // ikut geser tanpa perlu ubah kode.
        $bulanTerakhirData = $rows->filter(fn ($r) => $r['omset'] !== null)->max('bulan');

        $omsetTahunLaluYtd = $bulanTerakhirData
            ? $rows->filter(fn ($r) => $r['bulan'] <= $bulanTerakhirData)->pluck('omset_tahun_lalu')->filter(fn ($v) => $v !== null)->sum()
            : null;

        $growthYtd = ($omsetTahunLaluYtd !== null && $omsetTahunLaluYtd > 0)
            ? round((($totalOmset - $omsetTahunLaluYtd) / $omsetTahunLaluYtd) * 100, 1)
            : null;

        return view('omset-bulanan.index', [
            'tahun' => $tahun,
            'kaeCode' => $kaeCode,
            'rows' => $rows,
            'totalOmset' => $totalOmset,
            'maxOmset' => $maxOmset,
            'sumberIni' => $iniAdaDataAsli ? 'order' : 'historis',
            'sumberLalu' => $laluAdaDataAsli ? 'order' : 'historis',
            'bulanTerakhirLabel' => $bulanTerakhirData ? \Carbon\Carbon::create($tahun, $bulanTerakhirData, 1)->translatedFormat('F') : null,
            'omsetTahunLaluYtd' => $omsetTahunLaluYtd,
            'growthYtd' => $growthYtd,
            'kaeOptions' => $user->role === 'kae' ? collect() : User::where('role', 'kae')->orderBy('name')->get(['name', 'kae_code']),
            'chart' => $this->buildYearOverlayChart($kaeCode),
        ]);
    }

    /**
     * Pre-computed SVG coordinates untuk beberapa garis ditumpuk di satu
     * sumbu 12 bulan (Jan-Des), satu garis per tahun dengan warna beda —
     * biar pola musiman & pertumbuhan antar-tahun langsung kebanding di
     * bulan yang sama. Dependency-free (no JS charting lib), sama seperti
     * gaya SVG lain di app ini. Nilai null (data tidak tersedia, mis.
     * filter KAE di tahun historis) memutus garis jadi beberapa segmen
     * polyline terpisah, bukan digambar 0.
     */
    private function buildYearOverlayChart(?string $kaeCode): array
    {
        $startYear = HistoricalOmsetBulanan::min('tahun') ?? now()->year;
        $endYear = now()->year;

        $palette = ['var(--accent)', 'var(--good)', 'var(--warn)', 'var(--critical)', 'var(--ink-faint)'];

        $width = 720;
        $height = 220;
        $left = 55;
        $top = 10;
        $step = $width / 11;

        $omsetPerTahun = [];
        $maxVal = 0;

        for ($y = $endYear; $y >= $startYear; $y--) {
            $adaDataAsli = Order::whereYear('tanggal_order', $y)->exists();
            $omsetTahunY = $this->omsetPerBulan($y, $kaeCode, $adaDataAsli);
            $maxVal = max($maxVal, $omsetTahunY->filter(fn ($v) => $v !== null)->max() ?: 0);
            $omsetPerTahun[$y] = $omsetTahunY;
        }
        $maxVal = $maxVal ?: 1;

        $lines = [];
        foreach ($omsetPerTahun as $y => $omsetTahunY) {
            $segments = [];
            $current = [];
            $dots = [];

            for ($m = 1; $m <= 12; $m++) {
                $v = $omsetTahunY->get($m);
                if ($v === null) {
                    if (count($current) > 1) {
                        $segments[] = implode(' ', $current);
                    }
                    $current = [];

                    continue;
                }

                $x = round($left + ($m - 1) * $step, 1);
                $yy = round($top + $height * (1 - $v / $maxVal), 1);
                $current[] = "$x,$yy";
                $dots[] = ['x' => $x, 'y' => $yy, 'v' => $v, 'label' => \Carbon\Carbon::create($y, $m, 1)->translatedFormat('F')];
            }

            if (count($current) > 1) {
                $segments[] = implode(' ', $current);
            }

            $isCurrent = $y === $endYear;

            $lines[] = [
                'year' => $y,
                'color' => $palette[min($endYear - $y, count($palette) - 1)],
                'isCurrent' => $isCurrent,
                // Tahun-tahun sebelumnya dibuat pastel (opacity & garis lebih
                // tipis) biar tahun berjalan yang paling menonjol di grafik.
                'opacity' => $isCurrent ? 1 : 0.4,
                'strokeWidth' => $isCurrent ? 3 : 1.75,
                'dotR' => $isCurrent ? 3.5 : 2,
                'segments' => $segments,
                'dots' => $dots,
            ];
        }

        // Tahun terbaru digambar paling akhir (di atas) biar paling menonjol.
        $lines = array_reverse($lines);

        $gridLines = collect(range(0, 4))->map(function ($i) use ($maxVal, $height, $top) {
            $frac = $i / 4;

            return [
                'y' => round($top + $height * (1 - $frac), 1),
                'label' => $this->formatRingkas($maxVal * $frac),
            ];
        });

        $xLabels = collect(range(1, 12))->map(fn ($m) => [
            'x' => round($left + ($m - 1) * $step, 1),
            'label' => mb_substr(\Carbon\Carbon::create(2000, $m, 1)->translatedFormat('F'), 0, 3),
        ]);

        return [
            'width' => $width + $left + 20,
            'height' => $height + $top + 30,
            'left' => $left,
            'top' => $top,
            'bottom' => $top + $height,
            'lines' => $lines,
            'gridLines' => $gridLines,
            'xLabels' => $xLabels,
        ];
    }

    private function formatRingkas(float $v): string
    {
        if ($v >= 1_000_000_000) {
            return round($v / 1_000_000_000, 1).'M';
        }
        if ($v >= 1_000_000) {
            return round($v / 1_000_000, 1).'Jt';
        }
        if ($v >= 1_000) {
            return round($v / 1_000, 1).'Rb';
        }

        return (string) round($v);
    }

    /**
     * @return Collection<int, ?float> bulan (1-12) => omset, null kalau
     *   sumbernya historis tapi ada filter KAE (data historis tidak
     *   punya breakdown per KAE, jadi jujur ditampilkan tidak tersedia).
     */
    private function omsetPerBulan(int $tahun, ?string $kaeCode, bool $adaDataAsli): Collection
    {
        if ($adaDataAsli) {
            return Order::query()
                ->whereYear('tanggal_order', $tahun)
                ->when($kaeCode, fn ($q) => $q->where('kae_code', $kaeCode))
                ->selectRaw('MONTH(tanggal_order) as bulan, SUM(total_transaksi) as omset')
                ->groupBy('bulan')
                ->pluck('omset', 'bulan')
                ->map(fn ($v) => (float) $v);
        }

        if ($kaeCode) {
            return collect();
        }

        return HistoricalOmsetBulanan::where('tahun', $tahun)
            ->pluck('omset', 'bulan')
            ->map(fn ($v) => (float) $v);
    }
}
