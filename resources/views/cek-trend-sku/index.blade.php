@extends('layouts.app')

@section('content')
    <h1 class="display" style="font-size:24px;">Cek Trend SKU</h1>
    <div style="color:var(--ink-muted); font-size:13px; margin-top:4px; margin-bottom:20px;">
        Tren penjualan harian (Omset &amp; Qty) untuk SKU yang ditandai di menu Input NPD &rarr; tab Input Trend SKU, lengkap dengan titik mulai campaign di B2C.
    </div>

    @if ($trendSkuOptions->isEmpty())
        <section class="card" style="color:var(--ink-muted); font-size:13px;">
            Belum ada produk yang ditandai sebagai Trend SKU. Tandai dulu di menu
            <a href="{{ route('npd.index', ['tab' => 'trend-sku']) }}">Input NPD &rarr; tab Input Trend SKU</a>.
        </section>
    @else
        <form method="GET" action="{{ route('cek-trend-sku.index') }}" class="field-row" style="align-items:flex-end; margin-bottom:20px;">
            <div class="field" style="margin-bottom:0; min-width:260px;">
                <label>SKU</label>
                <select name="trend_sku_id" class="select-pill" onchange="this.form.submit()">
                    @foreach ($trendSkuOptions as $opt)
                        <option value="{{ $opt->id }}" {{ $trendSku && $trendSku->id === $opt->id ? 'selected' : '' }}>
                            {{ $opt->produk->brand }} &middot; {{ $opt->produk->nama }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="field" style="margin-bottom:0;">
                <label>Dari Tanggal</label>
                <input type="date" name="dari_tanggal" value="{{ $dariTanggal }}">
            </div>
            <div class="field" style="margin-bottom:0;">
                <label>Sampai Tanggal</label>
                <input type="date" name="sampai_tanggal" value="{{ $sampaiTanggal }}">
            </div>
            <div class="field" style="margin-bottom:0;">
                <label>Tahun</label>
                <select name="tahun" class="select-pill">
                    <option value="">—</option>
                    @for ($y = now()->year; $y >= now()->year - 4; $y--)
                        <option value="{{ $y }}" {{ (int) $tahun === $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
            @if ($trendSku && $impactOptions->isNotEmpty())
                <div class="field" style="margin-bottom:0; min-width:240px;">
                    <label>SKU Terimpact (bandingkan)</label>
                    <select name="impact_produk_id" class="select-pill">
                        <option value="">— Gak dibandingkan —</option>
                        @foreach ($impactOptions as $opt)
                            <option value="{{ $opt->id }}" {{ $impactProduk && $impactProduk->id === $opt->id ? 'selected' : '' }}>{{ $opt->nama }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <button type="submit" class="btn" style="width:auto;">Terapkan</button>
        </form>

        @if (! $trendSku)
            <section class="card" style="color:var(--ink-muted); font-size:13px;">Pilih SKU dulu.</section>
        @else
            <section class="card">
                <div class="card-head">
                    <div>
                        <div class="card-title">
                            {{ $trendSku->produk->brand }} &middot; {{ $trendSku->produk->nama }}
                            @if ($impactProduk)
                                <span style="color:var(--ink-muted); font-weight:500;"> vs {{ $impactProduk->nama }}</span>
                            @endif
                        </div>
                        <div class="card-hint">
                            Mulai campaign B2C: {{ $trendSku->tanggal_mulai_campaign->format('d/m/Y') }}
                            @unless ($campaignDalamRentang)
                                <span style="color:var(--ink-faint);">(di luar rentang filter saat ini)</span>
                            @endunless
                        </div>
                    </div>
                    <div style="display:flex; gap:6px;">
                        <button type="button" id="btn-metric-omset" class="btn btn-primary" style="width:auto; font-size:12px; padding:6px 14px;" onclick="setTrendSkuMetric('omset')">Omset</button>
                        <button type="button" id="btn-metric-qty" class="btn" style="width:auto; font-size:12px; padding:6px 14px;" onclick="setTrendSkuMetric('qty')">Qty</button>
                    </div>
                </div>

                @if ($chart && $chart['mode'] === 'compare')
                    <div style="display:flex; gap:16px; font-size:11.5px; color:var(--ink-muted); margin-bottom:10px;">
                        <span><span style="display:inline-block; width:14px; height:2.5px; background:var(--accent); vertical-align:middle; margin-right:5px;"></span>{{ $trendSku->produk->nama }}</span>
                        <span><span style="display:inline-block; width:14px; height:2.5px; background:var(--good); vertical-align:middle; margin-right:5px; border-top:2.5px dashed var(--good);"></span>{{ $impactProduk->nama }}</span>
                        <span style="color:var(--ink-faint);">(dinormalisasi ke % dari puncak masing-masing, buat bandingkan bentuk tren)</span>
                    </div>

                    <svg viewBox="0 0 {{ $chart['width'] }} {{ $chart['height'] }}" style="width:100%; height:auto;">
                        @foreach ($chart['omset']['gridlines'] as $gl)
                            <line x1="{{ $chart['padLeft'] }}" y1="{{ $gl['y'] }}" x2="{{ $chart['width'] - 16 }}" y2="{{ $gl['y'] }}" stroke="var(--line)" stroke-width="1" stroke-dasharray="3,3"/>
                            <text x="{{ $chart['padLeft'] - 8 }}" y="{{ $gl['y'] + 3 }}" text-anchor="end" font-size="10" fill="var(--ink-muted)">{{ $gl['label'] }}</text>
                        @endforeach

                        @if (! empty($chart['campaignX']))
                            <line x1="{{ $chart['campaignX'] }}" y1="{{ $chart['padTop'] }}" x2="{{ $chart['campaignX'] }}" y2="{{ $chart['padTop'] + $chart['plotHeight'] }}" stroke="var(--highlight)" stroke-width="1.5" stroke-dasharray="4,3"/>
                            <circle cx="{{ $chart['campaignX'] }}" cy="{{ $chart['padTop'] }}" r="3.5" fill="var(--highlight)"/>
                            <text x="{{ $chart['campaignX'] }}" y="{{ $chart['padTop'] - 5 }}" text-anchor="middle" font-size="10" font-weight="600" fill="var(--highlight)">Mulai Campaign</text>
                        @endif

                        @foreach (['omset', 'qty'] as $metric)
                            <polyline id="trend-sku-lineA-{{ $metric }}" points="{{ $chart[$metric]['pointsA'] }}" fill="none" stroke="var(--accent)" stroke-width="2" style="{{ $metric === 'omset' ? '' : 'display:none;' }}"/>
                            <polyline id="trend-sku-lineB-{{ $metric }}" points="{{ $chart[$metric]['pointsB'] }}" fill="none" stroke="var(--good)" stroke-width="2" stroke-dasharray="5,4" style="{{ $metric === 'omset' ? '' : 'display:none;' }}"/>
                        @endforeach

                        @foreach ($chart['xLabels'] as $xl)
                            <text x="{{ $xl['x'] }}" y="{{ $chart['padTop'] + $chart['plotHeight'] + 20 }}" text-anchor="middle" font-size="10" fill="var(--ink-muted)">{{ $xl['label'] }}</text>
                        @endforeach
                    </svg>

                    <script>
                        function setTrendSkuMetric(metric) {
                            ['omset', 'qty'].forEach(function (m) {
                                document.getElementById('trend-sku-lineA-' + m).style.display = (m === metric) ? '' : 'none';
                                document.getElementById('trend-sku-lineB-' + m).style.display = (m === metric) ? '' : 'none';
                                document.getElementById('btn-metric-' + m).classList.toggle('btn-primary', m === metric);
                            });
                        }
                    </script>
                @elseif ($chart)
                    <svg viewBox="0 0 {{ $chart['width'] }} {{ $chart['height'] }}" style="width:100%; height:auto;">
                        @foreach (['omset', 'qty'] as $metric)
                            <g id="trend-sku-gridlines-{{ $metric }}" style="{{ $metric === 'omset' ? '' : 'display:none;' }}">
                                @foreach ($chart[$metric]['gridlines'] as $gl)
                                    <line x1="{{ $chart['padLeft'] }}" y1="{{ $gl['y'] }}" x2="{{ $chart['width'] - 16 }}" y2="{{ $gl['y'] }}" stroke="var(--line)" stroke-width="1" stroke-dasharray="3,3"/>
                                    <text x="{{ $chart['padLeft'] - 8 }}" y="{{ $gl['y'] + 3 }}" text-anchor="end" font-size="10" fill="var(--ink-muted)">{{ $gl['label'] }}</text>
                                @endforeach
                            </g>
                        @endforeach

                        @if (! empty($chart['campaignX']))
                            <line x1="{{ $chart['campaignX'] }}" y1="{{ $chart['padTop'] }}" x2="{{ $chart['campaignX'] }}" y2="{{ $chart['padTop'] + $chart['plotHeight'] }}" stroke="var(--highlight)" stroke-width="1.5" stroke-dasharray="4,3"/>
                            <circle cx="{{ $chart['campaignX'] }}" cy="{{ $chart['padTop'] }}" r="3.5" fill="var(--highlight)"/>
                            <text x="{{ $chart['campaignX'] }}" y="{{ $chart['padTop'] - 5 }}" text-anchor="middle" font-size="10" font-weight="600" fill="var(--highlight)">Mulai Campaign</text>
                        @endif

                        @foreach (['omset', 'qty'] as $metric)
                            <polyline id="trend-sku-line-{{ $metric }}" points="{{ $chart[$metric]['points'] }}" fill="none" stroke="var(--accent)" stroke-width="2" style="{{ $metric === 'omset' ? '' : 'display:none;' }}"/>
                        @endforeach

                        @foreach ($chart['xLabels'] as $xl)
                            <text x="{{ $xl['x'] }}" y="{{ $chart['padTop'] + $chart['plotHeight'] + 20 }}" text-anchor="middle" font-size="10" fill="var(--ink-muted)">{{ $xl['label'] }}</text>
                        @endforeach
                    </svg>

                    <script>
                        function setTrendSkuMetric(metric) {
                            ['omset', 'qty'].forEach(function (m) {
                                document.getElementById('trend-sku-line-' + m).style.display = (m === metric) ? '' : 'none';
                                document.getElementById('trend-sku-gridlines-' + m).style.display = (m === metric) ? '' : 'none';
                                document.getElementById('btn-metric-' + m).classList.toggle('btn-primary', m === metric);
                            });
                        }
                    </script>
                @else
                    <div style="color:var(--ink-muted); font-size:13px;">Belum ada data penjualan di rentang tanggal ini.</div>
                @endif
            </section>
        @endif
    @endif
@endsection
