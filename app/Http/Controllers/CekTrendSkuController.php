<?php

namespace App\Http\Controllers;

use App\Models\TrendSku;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Sales > Cek Trend SKU -- line chart tren penjualan harian (Omset & Qty)
 * buat satu SKU yang sudah ditandai lewat tab "Input Trend SKU" di menu
 * Input NPD, plus titik penanda di tanggal mulai dicampaignkan di B2C.
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

        $series = [];
        if ($trendSku) {
            $rows = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('order_items.produk_id', $trendSku->produk_id)
                ->whereBetween('orders.tanggal_order', [$dari, $sampai])
                ->selectRaw('DATE(orders.tanggal_order) as tanggal, SUM(order_items.subtotal) as omset, SUM(order_items.qty) as qty')
                ->groupBy('tanggal')
                ->get()
                ->keyBy(fn ($r) => (string) $r->tanggal);

            // Isi tiap hari dalam rentang (termasuk yang gak ada transaksi =
            // 0) biar garisnya kontinu, bukan loncat-loncat ninggalin celah.
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
        }

        $campaignDalamRentang = $trendSku
            && $trendSku->tanggal_mulai_campaign->gte($dari->startOfDay())
            && $trendSku->tanggal_mulai_campaign->lte($sampai->startOfDay());

        $campaignTanggal = $campaignDalamRentang ? $trendSku->tanggal_mulai_campaign->toDateString() : null;

        return view('cek-trend-sku.index', [
            'trendSkuOptions' => $trendSkuOptions,
            'trendSku' => $trendSku,
            'series' => $series,
            'dariTanggal' => $dari->toDateString(),
            'sampaiTanggal' => $sampai->toDateString(),
            'tahun' => $tahun,
            'campaignDalamRentang' => $campaignDalamRentang,
            'chart' => $series ? $this->buildChartGeometry($series, $campaignTanggal) : null,
        ]);
    }

    /**
     * Hitung koordinat SVG (polyline + gridline + label sumbu-X) buat Omset
     * dan Qty sekali di sini, biar blade-nya cuma tinggal echo angka siap
     * pakai -- gak ada perhitungan skala di view.
     */
    private function buildChartGeometry(array $series, ?string $campaignTanggal): array
    {
        $width = 920;
        $height = 320;
        $padLeft = 64;
        $padRight = 16;
        $padTop = 16;
        $padBottom = 36;
        $plotWidth = $width - $padLeft - $padRight;
        $plotHeight = $height - $padTop - $padBottom;

        $n = count($series);
        $xFor = fn (int $i) => $n > 1 ? $padLeft + ($i / ($n - 1)) * $plotWidth : $padLeft + $plotWidth / 2;

        $buildSeries = function (string $field, string $format) use ($series, $n, $xFor, $padTop, $plotHeight) {
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

        $xLabels = [];
        $jumlahLabel = min(7, $n);
        if ($jumlahLabel > 1) {
            for ($l = 0; $l < $jumlahLabel; $l++) {
                $i = (int) round($l * ($n - 1) / ($jumlahLabel - 1));
                $xLabels[] = ['x' => round($xFor($i), 2), 'label' => \Carbon\Carbon::parse($series[$i]['tanggal'])->format('d/m')];
            }
        } elseif ($n === 1) {
            $xLabels[] = ['x' => round($xFor(0), 2), 'label' => \Carbon\Carbon::parse($series[0]['tanggal'])->format('d/m')];
        }

        $campaignX = null;
        if ($campaignTanggal) {
            foreach ($series as $i => $row) {
                if ($row['tanggal'] === $campaignTanggal) {
                    $campaignX = round($xFor($i), 2);
                    break;
                }
            }
        }

        return [
            'width' => $width,
            'height' => $height,
            'padLeft' => $padLeft,
            'padTop' => $padTop,
            'padBottom' => $padBottom,
            'plotHeight' => $plotHeight,
            'omset' => $buildSeries('omset', 'rupiah'),
            'qty' => $buildSeries('qty', 'angka'),
            'xLabels' => $xLabels,
            'campaignX' => $campaignX,
        ];
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
