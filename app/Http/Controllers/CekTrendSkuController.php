<?php

namespace App\Http\Controllers;

use App\Models\Produk;
use App\Models\TrendSku;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Sales > Cek Trend SKU -- line chart tren penjualan harian (Omset & Qty)
 * buat satu SKU yang sudah ditandai lewat tab "Input Trend SKU" di menu
 * Input NPD, plus titik penanda di tanggal mulai dicampaignkan di B2C.
 *
 * Bisa juga overlay 1 "SKU Terimpact" (produk lain brand yang sama) buat
 * membuktikan efek halo -- campaign 1 SKU ikut mengerek tren SKU lain.
 */
class CekTrendSkuController extends Controller
{
    public function index(Request $request): View
    {
        $trendSkuOptions = TrendSku::with('produk')->get()
            ->filter(fn ($t) => $t->produk !== null)
            ->sortBy(fn ($t) => $t->produk->nama)
            ->values();

        $selectedTrendSkuId = $request->filled('trend_sku_id')
            ? (int) $request->input('trend_sku_id')
            : $trendSkuOptions->first()?->id;

        $trendSku = $trendSkuOptions->firstWhere('id', $selectedTrendSkuId);

        $tahun = $request->filled('tahun') ? (int) $request->input('tahun') : null;
        if ($tahun) {
            $dariTanggal = Carbon::create($tahun, 1, 1)->toDateString();
            $sampaiTanggal = Carbon::create($tahun, 12, 31)->toDateString();
        } else {
            $dariTanggal = $request->input('dari_tanggal', now()->subDays(89)->toDateString());
            $sampaiTanggal = $request->input('sampai_tanggal', now()->toDateString());
        }

        // Jaga-jaga urutan kebalik / rentang kebesaran (biar gak accidentally
        // generate ratusan ribu titik chart kalau user salah input tanggal).
        $dari = Carbon::parse($dariTanggal)->startOfDay();
        $sampai = Carbon::parse($sampaiTanggal)->endOfDay();
        if ($sampai->lt($dari)) {
            [$dari, $sampai] = [$sampai->copy()->startOfDay(), $dari->copy()->endOfDay()];
        }
        if ($dari->diffInDays($sampai) > 730) {
            $sampai = $dari->copy()->addDays(730)->endOfDay();
        }

        $series = $trendSku ? $this->buildDailySeries($trendSku->produk_id, $dari, $sampai) : [];

        // "SKU Terimpact" -- produk lain dari brand yang sama dengan SKU yang
        // ditandai, buat di-overlay di chart yang sama (lihat apakah tren-nya
        // ikut naik di sekitar tanggal campaign SKU utama).
        $impactOptions = $trendSku
            ? Produk::where('brand', $trendSku->produk->brand)
                ->where('id', '!=', $trendSku->produk_id)
                ->orderBy('nama')
                ->get()
            : collect();

        $impactProdukId = $request->filled('impact_produk_id') ? (int) $request->input('impact_produk_id') : null;
        $impactProduk = $impactProdukId ? $impactOptions->firstWhere('id', $impactProdukId) : null;

        $impactSeries = $impactProduk ? $this->buildDailySeries($impactProduk->id, $dari, $sampai) : [];

        $campaignDalamRentang = $trendSku
            && $trendSku->tanggal_mulai_campaign->gte($dari->startOfDay())
            && $trendSku->tanggal_mulai_campaign->lte($sampai->startOfDay());

        $campaignTanggal = $campaignDalamRentang ? $trendSku->tanggal_mulai_campaign->toDateString() : null;

        $chart = null;
        if ($series && $impactProduk) {
            $chart = $this->buildComparisonChartGeometry($series, $impactSeries, $campaignTanggal);
        } elseif ($series) {
            $chart = $this->buildChartGeometry($series, $campaignTanggal);
        }

        return view('cek-trend-sku.index', [
            'trendSkuOptions' => $trendSkuOptions,
            'trendSku' => $trendSku,
            'series' => $series,
            'dariTanggal' => $dari->toDateString(),
            'sampaiTanggal' => $sampai->toDateString(),
            'tahun' => $tahun,
            'campaignDalamRentang' => $campaignDalamRentang,
            'impactOptions' => $impactOptions,
            'impactProduk' => $impactProduk,
            'chart' => $chart,
        ]);
    }

    /** Series harian (Omset & Qty), diisi 0 buat hari tanpa transaksi biar garisnya kontinu. */
    private function buildDailySeries(int $produkId, Carbon $dari, Carbon $sampai): array
    {
        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.produk_id', $produkId)
            ->whereBetween('orders.tanggal_order', [$dari, $sampai])
            ->selectRaw('DATE(orders.tanggal_order) as tanggal, SUM(order_items.subtotal) as omset, SUM(order_items.qty) as qty')
            ->groupBy('tanggal')
            ->get()
            ->keyBy(fn ($r) => (string) $r->tanggal);

        $series = [];
        $cursor = $dari->copy()->startOfDay();
        $akhir = $sampai->copy()->startOfDay();
        while ($cursor->lte($akhir)) {
            $key = $cursor->toDateString();
            $row = $rows->get($key);
            $series[] = [
                'tanggal' => $cursor->toDateString(),
                'omset' => $row ? (float) $row->omset : 0.0,
                'qty' => $row ? (int) $row->qty : 0,
            ];
            $cursor->addDay();
        }

        return $series;
    }

    /**
     * Hitung koordinat SVG (polyline + gridline + label sumbu-X) buat Omset
     * dan Qty sekali di sini, biar blade-nya cuma tinggal echo angka siap
     * pakai -- gak ada perhitungan skala di view.
     */
    private function buildChartGeometry(array $series, ?string $campaignTanggal): array
    {
        [$width, $height, $padLeft, $padTop, $padBottom, $plotWidth, $plotHeight] = $this->chartDims();

        $n = count($series);
        $xFor = fn (int $i) => $n > 1 ? $padLeft + ($i / ($n - 1)) * $plotWidth : $padLeft + $plotWidth / 2;

        $buildSeries = function (string $field, string $format) use ($series, $xFor, $padTop, $plotHeight) {
            $values = array_column($series, $field);
            $max = max($values) ?: 1;

            $points = [];
            foreach ($values as $i => $v) {
                $y = $padTop + $plotHeight - ($v / $max) * $plotHeight;
                $points[] = round($xFor($i), 2).','.round($y, 2);
            }

            $gridlines = [];
            for ($g = 0; $g <= 4; $g++) {
                $val = $max * $g / 4;
                $gridlines[] = [
                    'y' => round($padTop + $plotHeight - ($g / 4) * $plotHeight, 2),
                    'label' => $format === 'rupiah' ? $this->formatRupiahSingkat($val) : number_format($val, 0, ',', '.'),
                ];
            }

            return ['points' => implode(' ', $points), 'gridlines' => $gridlines, 'max' => $max];
        };

        return [
            'mode' => 'single',
            'width' => $width,
            'height' => $height,
            'padLeft' => $padLeft,
            'padTop' => $padTop,
            'padBottom' => $padBottom,
            'plotHeight' => $plotHeight,
            'omset' => $buildSeries('omset', 'rupiah'),
            'qty' => $buildSeries('qty', 'angka'),
            'xLabels' => $this->buildXLabels($series, $xFor),
            'campaignX' => $this->campaignX($series, $campaignTanggal, $xFor),
        ];
    }

    /**
     * Varian 2-garis (SKU utama vs SKU Terimpact), dinormalisasi masing-masing
     * ke % dari puncaknya sendiri -- dipilih karena dua SKU biasanya beda
     * jauh skalanya (omset/qty), jadi yang mau dibandingkan adalah BENTUK
     * trennya (apakah sama-sama naik di sekitar tanggal campaign), bukan
     * nilai absolutnya.
     */
    private function buildComparisonChartGeometry(array $seriesA, array $seriesB, ?string $campaignTanggal): array
    {
        [$width, $height, $padLeft, $padTop, $padBottom, $plotWidth, $plotHeight] = $this->chartDims();

        $n = count($seriesA);
        $xFor = fn (int $i) => $n > 1 ? $padLeft + ($i / ($n - 1)) * $plotWidth : $padLeft + $plotWidth / 2;

        $buildPair = function (string $field) use ($seriesA, $seriesB, $xFor, $padTop, $plotHeight) {
            $valuesA = array_column($seriesA, $field);
            $valuesB = array_column($seriesB, $field);
            $maxA = max($valuesA) ?: 1;
            $maxB = max($valuesB) ?: 1;

            $pointsFor = function (array $values, float $max) use ($xFor, $padTop, $plotHeight) {
                $points = [];
                foreach ($values as $i => $v) {
                    $y = $padTop + $plotHeight - ($v / $max) * $plotHeight;
                    $points[] = round($xFor($i), 2).','.round($y, 2);
                }

                return implode(' ', $points);
            };

            $gridlines = [];
            for ($g = 0; $g <= 4; $g++) {
                $gridlines[] = [
                    'y' => round($padTop + $plotHeight - ($g / 4) * $plotHeight, 2),
                    'label' => ($g * 25).'%',
                ];
            }

            return [
                'pointsA' => $pointsFor($valuesA, $maxA),
                'pointsB' => $pointsFor($valuesB, $maxB),
                'gridlines' => $gridlines,
                'maxA' => $maxA,
                'maxB' => $maxB,
            ];
        };

        return [
            'mode' => 'compare',
            'width' => $width,
            'height' => $height,
            'padLeft' => $padLeft,
            'padTop' => $padTop,
            'padBottom' => $padBottom,
            'plotHeight' => $plotHeight,
            'omset' => $buildPair('omset'),
            'qty' => $buildPair('qty'),
            'xLabels' => $this->buildXLabels($seriesA, $xFor),
            'campaignX' => $this->campaignX($seriesA, $campaignTanggal, $xFor),
        ];
    }

    private function chartDims(): array
    {
        $width = 920;
        $height = 320;
        $padLeft = 64;
        $padRight = 16;
        $padTop = 16;
        $padBottom = 36;
        $plotWidth = $width - $padLeft - $padRight;
        $plotHeight = $height - $padTop - $padBottom;

        return [$width, $height, $padLeft, $padTop, $padBottom, $plotWidth, $plotHeight];
    }

    private function buildXLabels(array $series, callable $xFor): array
    {
        $n = count($series);
        $xLabels = [];
        $jumlahLabel = min(7, $n);
        if ($jumlahLabel > 1) {
            for ($l = 0; $l < $jumlahLabel; $l++) {
                $i = (int) round($l * ($n - 1) / ($jumlahLabel - 1));
                $xLabels[] = ['x' => round($xFor($i), 2), 'label' => Carbon::parse($series[$i]['tanggal'])->format('d/m')];
            }
        } elseif ($n === 1) {
            $xLabels[] = ['x' => round($xFor(0), 2), 'label' => Carbon::parse($series[0]['tanggal'])->format('d/m')];
        }

        return $xLabels;
    }

    private function campaignX(array $series, ?string $campaignTanggal, callable $xFor): ?float
    {
        if (! $campaignTanggal) {
            return null;
        }

        foreach ($series as $i => $row) {
            if ($row['tanggal'] === $campaignTanggal) {
                return round($xFor($i), 2);
            }
        }

        return null;
    }

    private function formatRupiahSingkat(float $val): string
    {
        if ($val >= 1_000_000_000) {
            return number_format($val / 1_000_000_000, 1, ',', '.').' M';
        }
        if ($val >= 1_000_000) {
            return number_format($val / 1_000_000, 1, ',', '.').' Jt';
        }
        if ($val >= 1_000) {
            return number_format($val / 1_000, 0, ',', '.').' Rb';
        }

        return number_format($val, 0, ',', '.');
    }
}
