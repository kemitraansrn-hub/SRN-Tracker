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
        $bulan = $request->filled('bulan') ? (int) $request->input('bulan') : null;

        if ($bulan) {
            // Bulan butuh tahun jadi konteks -- pakai Tahun yang dipilih,
            // kalau belum pilih tahun ini berjalan jadi default. Rentangnya
            // dari awal bulan itu s/d HARI INI (bukan cuma 1 bulan itu saja)
            // -- kalau tahunnya tahun lampau (sudah lewat), "hari ini" gak
            // relevan lagi, jadi dibatasi s/d akhir tahun itu.
            $tahunPakai = $tahun ?: now()->year;
            $dari = Carbon::create($tahunPakai, $bulan, 1)->startOfMonth();
            $sampai = $tahunPakai === now()->year ? now()->endOfDay() : Carbon::create($tahunPakai, 12, 31)->endOfDay();
        } elseif ($tahun) {
            $dari = Carbon::create($tahun, 1, 1)->startOfDay();
            $sampai = Carbon::create($tahun, 12, 31)->endOfDay();
        } elseif ($request->filled('dari_tanggal') || $request->filled('sampai_tanggal')) {
            $dari = Carbon::parse($request->input('dari_tanggal', now()->startOfYear()->toDateString()))->startOfDay();
            $sampai = Carbon::parse($request->input('sampai_tanggal', now()->toDateString()))->endOfDay();
        } else {
            // Default: Year To Date (1 Jan tahun berjalan s/d hari ini).
            $dari = now()->startOfYear()->startOfDay();
            $sampai = now()->endOfDay();
        }

        // Jaga-jaga urutan kebalik / rentang kebesaran (biar gak accidentally
        // generate ratusan ribu titik chart kalau user salah input tanggal).
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

        // Biar jumlah hari "sebelum" vs "sesudah" campaign adil (sama
        // panjang), kalau jatah hari SEBELUM campaign di dalam rentang
        // filter gak cukup buat nyamain panjang "sesudah", tarik data
        // tambahan dari SEBELUM awal filter (di luar yang kelihatan di
        // chart, khusus buat hitungan %, bukan buat gambar garisnya).
        $extraSeriesA = [];
        $extraSeriesB = [];
        if ($campaignDalamRentang) {
            // abs() di sini SENGAJA -- Carbon::diffInDays() balikin nilai
            // signed (bisa negatif tergantung urutan tanggalnya), bukan
            // selalu absolut, jadi tanpa abs() $kurang bisa salah hitung
            // jadi 0 padahal harusnya positif.
            $campaignDay = $trendSku->tanggal_mulai_campaign->copy()->startOfDay();
            $afterLen = abs($sampai->copy()->startOfDay()->diffInDays($campaignDay)) + 1;
            $beforeAvailable = abs($campaignDay->diffInDays($dari->copy()->startOfDay()));
            $kurang = max(0, $afterLen - $beforeAvailable);

            if ($kurang > 0) {
                $extraStart = $dari->copy()->subDays($kurang)->startOfDay();
                $extraEnd = $dari->copy()->subDay()->endOfDay();
                $extraSeriesA = $trendSku ? $this->buildDailySeries($trendSku->produk_id, $extraStart, $extraEnd) : [];
                $extraSeriesB = $impactProduk ? $this->buildDailySeries($impactProduk->id, $extraStart, $extraEnd) : [];
            }
        }

        $chart = null;
        if ($series && $impactProduk) {
            $chart = $this->buildComparisonChartGeometry($series, $impactSeries, $campaignTanggal, $extraSeriesA, $extraSeriesB);
        } elseif ($series) {
            $chart = $this->buildChartGeometry($series, $campaignTanggal, $extraSeriesA);
        }

        $viewData = [
            'trendSkuOptions' => $trendSkuOptions,
            'trendSku' => $trendSku,
            'series' => $series,
            'dariTanggal' => $dari->toDateString(),
            'sampaiTanggal' => $sampai->toDateString(),
            'tahun' => $tahun,
            'bulan' => $bulan,
            'campaignDalamRentang' => $campaignDalamRentang,
            'impactOptions' => $impactOptions,
            'impactProduk' => $impactProduk,
            'chart' => $chart,
        ];

        // Filter (SKU/tanggal/bulan/tahun/SKU Terimpact) dikirim via fetch()
        // tanpa tombol Terapkan -- cukup balas fragment form+chart-nya saja,
        // bukan halaman penuh dengan layout/sidebar.
        if ($request->ajax()) {
            return view('cek-trend-sku._content', $viewData);
        }

        return view('cek-trend-sku.index', $viewData);
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
    private function buildChartGeometry(array $series, ?string $campaignTanggal, array $extraBeforeSeries = []): array
    {
        [$width, $height, $padLeft, $padTop, $padBottom, $plotWidth, $plotHeight] = $this->chartDims();

        $n = count($series);
        $xFor = fn (int $i) => $n > 1 ? $padLeft + ($i / ($n - 1)) * $plotWidth : $padLeft + $plotWidth / 2;

        $campaignIdx = $this->campaignIndex($series, $campaignTanggal);

        $buildSeries = function (string $field, string $format) use ($series, $xFor, $padTop, $plotHeight, $campaignIdx, $extraBeforeSeries) {
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

            return [
                'points' => implode(' ', $points),
                'gridlines' => $gridlines,
                'max' => $max,
                'trend' => $this->linearTrend($values, $max, $xFor, $padTop, $plotHeight, $campaignIdx, array_column($extraBeforeSeries, $field)),
            ];
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
    private function buildComparisonChartGeometry(array $seriesA, array $seriesB, ?string $campaignTanggal, array $extraSeriesA = [], array $extraSeriesB = []): array
    {
        [$width, $height, $padLeft, $padTop, $padBottom, $plotWidth, $plotHeight] = $this->chartDims();

        $n = count($seriesA);
        $xFor = fn (int $i) => $n > 1 ? $padLeft + ($i / ($n - 1)) * $plotWidth : $padLeft + $plotWidth / 2;
        $campaignIdx = $this->campaignIndex($seriesA, $campaignTanggal);

        $buildPair = function (string $field) use ($seriesA, $seriesB, $xFor, $padTop, $plotHeight, $campaignIdx, $extraSeriesA, $extraSeriesB) {
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
                // SKU Terimpact gak punya tanggal campaign sendiri -- sengaja
                // dibelah pakai tanggal campaign SKU UTAMA yang sama (itulah
                // tanggal yang mau dibuktikan efek halo-nya ke SKU ini juga).
                'trendA' => $this->linearTrend($valuesA, $maxA, $xFor, $padTop, $plotHeight, $campaignIdx, array_column($extraSeriesA, $field)),
                'trendB' => $this->linearTrend($valuesB, $maxB, $xFor, $padTop, $plotHeight, $campaignIdx, array_column($extraSeriesB, $field)),
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
        $idx = $this->campaignIndex($series, $campaignTanggal);

        return $idx !== null ? round($xFor($idx), 2) : null;
    }

    /** Index di $series yang tanggalnya persis sama dengan tanggal campaign, atau null kalau gak ketemu (campaign di luar rentang filter). */
    private function campaignIndex(array $series, ?string $campaignTanggal): ?int
    {
        if (! $campaignTanggal) {
            return null;
        }

        foreach ($series as $i => $row) {
            if ($row['tanggal'] === $campaignTanggal) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Garis tren (regresi linear sederhana) atas data harian -- buat
     * menandakan arah tren naik/turun secara visual, bukan cuma dari
     * fluktuasi harian yang naik-turun kecil. Dihitung dari NILAI ASLI
     * (bukan titik piksel), lalu baru dikonversi ke koordinat SVG pakai
     * skala `$max` yang sama dengan garis datanya sendiri.
     *
     * $campaignIdx (index di $values yang tanggalnya = tanggal mulai
     * campaign) kalau ada, dipakai sebagai titik belah buat arah/persentase
     * -- "sebelum campaign" vs "sesudah campaign" dalam rentang filter yang
     * aktif, biar bener-bener nunjukin dampak campaign-nya, bukan cuma
     * "separuh pertama vs kedua" yang kebetulan. Kalau null (campaign di
     * luar rentang filter, atau di paling ujung sehingga salah satu sisi
     * kosong), balik ke belah tengah biasa.
     *
     * $extraBeforeValues (opsional) = data harian TAMBAHAN dari SEBELUM
     * awal rentang filter, dipakai buat nyamain jumlah hari "sebelum"
     * dengan "sesudah" campaign kalau jatah hari sebelum di dalam filter
     * gak cukup -- supaya perbandingannya adil (apple-to-apple jumlah
     * hari), bukan asal ambil semua yang kebetulan ada di filter.
     */
    private function linearTrend(array $values, float $max, callable $xFor, int $padTop, int $plotHeight, ?int $campaignIdx = null, array $extraBeforeValues = []): array
    {
        $n = count($values);
        if ($n < 2) {
            return ['points' => null, 'arah' => 'stabil', 'pct' => null];
        }

        $sumX = $sumY = $sumXY = $sumX2 = 0.0;
        foreach ($values as $i => $v) {
            $sumX += $i;
            $sumY += $v;
            $sumXY += $i * $v;
            $sumX2 += $i * $i;
        }

        $denom = ($n * $sumX2 - $sumX * $sumX);
        if ($denom == 0.0) {
            return ['points' => null, 'arah' => 'stabil', 'pct' => null];
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / $denom;
        $intercept = ($sumY - $slope * $sumX) / $n;

        $startVal = $intercept;
        $endVal = $intercept + $slope * ($n - 1);

        $yStart = $padTop + $plotHeight - ($startVal / $max) * $plotHeight;
        $yEnd = $padTop + $plotHeight - ($endVal / $max) * $plotHeight;

        // Arah & persentase SENGAJA dihitung dari rata-rata data ASLI
        // (sebelum vs sesudah tanggal campaign, atau separuh-separuh kalau
        // campaign-nya gak ada di rentang filter), BUKAN dari titik regresi
        // di atas. Titik regresi (start/endVal) cuma buat gambar garis
        // putus-putusnya -- kalau ada 1 lonjakan ekstrem di akhir periode
        // (kasus nyata: SKU datar ~0 selama 2,5 bulan lalu 1 spike besar),
        // garis least-squares bisa "nembus" ke bawah 0 di titik awal
        // walau data aslinya gak pernah negatif, jadi basis % jadi rusak
        // (selalu null). Rata-rata sebelum/sesudah jauh lebih tahan banting.
        if ($campaignIdx !== null && $campaignIdx > 0 && $campaignIdx < $n) {
            $beforeWithinFilter = array_slice($values, 0, $campaignIdx);
            $afterValues = array_slice($values, $campaignIdx);
            $targetLen = count($afterValues);

            if (count($beforeWithinFilter) >= $targetLen) {
                // Lebih dari cukup -- potong, ambil yang paling DEKAT ke
                // tanggal campaign (paling relevan), biar sama panjang persis.
                $beforeValues = array_slice($beforeWithinFilter, -$targetLen);
            } else {
                // Kurang -- tempelin data tambahan dari sebelum awal filter
                // (kalau ada/cukup) di depan, biar totalnya nyamain $targetLen.
                $kurang = $targetLen - count($beforeWithinFilter);
                $extraPart = $extraBeforeValues ? array_slice($extraBeforeValues, -$kurang) : [];
                $beforeValues = array_merge($extraPart, $beforeWithinFilter);
            }
        } else {
            $half = (int) max(1, floor($n / 2));
            $beforeValues = array_slice($values, 0, $half);
            $afterValues = array_slice($values, -$half);
        }
        $firstAvg = array_sum($beforeValues) / max(1, count($beforeValues));
        $lastAvg = array_sum($afterValues) / max(1, count($afterValues));

        // Threshold 1% dari puncak, biar perubahan kecil yang keliatan
        // flat di mata tetap dilabeli "Stabil", bukan naik/turun palsu.
        $threshold = $max * 0.01;
        if ($lastAvg - $firstAvg > $threshold) {
            $arah = 'naik';
        } elseif ($firstAvg - $lastAvg > $threshold) {
            $arah = 'turun';
        } else {
            $arah = 'stabil';
        }

        if ($firstAvg > 0) {
            $pct = round((($lastAvg - $firstAvg) / $firstAvg) * 100, 1);
        } elseif ($lastAvg > 0) {
            // Separuh awal nol/nyaris nol -- tetap kasih angka (bukan null)
            // pakai basis kecil (0,1% dari puncak) biar gak terbagi 0 tapi
            // juga gak mengada-ada, bukan ekstrapolasi titik regresi.
            $epsilon = max($max * 0.001, 0.0001);
            $pct = round((($lastAvg - $epsilon) / $epsilon) * 100, 1);
        } else {
            // Dua-duanya nol -- memang gak ada apa-apa buat dibandingkan.
            $pct = null;
        }

        return [
            'points' => round($xFor(0), 2).','.round($yStart, 2).' '.round($xFor($n - 1), 2).','.round($yEnd, 2),
            'arah' => $arah,
            'pct' => $pct,
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
