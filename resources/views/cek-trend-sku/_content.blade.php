@php
    // Closure (bukan function biasa) sengaja dipakai di sini biar gak
    // bentrok "function sudah dideklarasikan" kalau fragment ini di-render
    // lebih dari sekali dalam 1 proses PHP yang sama (misal tinker test, atau
    // beberapa call AJAX yang masuk ke worker PHP yang sama).
    $trendBadge = function (array $trend, string $size = '12px') {
        $arah = $trend['arah'] ?? 'stabil';
        $pct = $trend['pct'] ?? null;
        $color = $arah === 'naik' ? 'var(--good)' : ($arah === 'turun' ? 'var(--critical)' : 'var(--ink-muted)');
        $icon = $arah === 'naik' ? '&#9650;' : ($arah === 'turun' ? '&#9660;' : '&#8594;');
        $label = $arah === 'naik' ? 'Naik' : ($arah === 'turun' ? 'Turun' : 'Stabil');
        $pctText = $pct !== null ? ' '.number_format(abs($pct), 1, ',', '.').'%' : '';

        return '<span style="color:'.$color.'; font-weight:700; font-size:'.$size.'; white-space:nowrap;">'.$icon.' '.$label.$pctText.'</span>';
    };
@endphp

<form onsubmit="return false;" class="field-row" style="align-items:flex-end; margin-bottom:20px;">
    <div class="field" style="margin-bottom:0; min-width:260px;">
        <label>SKU</label>
        <select name="trend_sku_id" class="select-pill" onchange="trendSkuOnSkuChange()">
            @foreach ($trendSkuOptions as $opt)
                <option value="{{ $opt->id }}" {{ $trendSku && $trendSku->id === $opt->id ? 'selected' : '' }}>
                    {{ $opt->produk->brand }} &middot; {{ $opt->produk->nama }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="field" style="margin-bottom:0;">
        <label>Hitung s/d Tanggal</label>
        <input type="date" name="sampai_tanggal" value="{{ $sampaiTanggal }}" onchange="trendSkuOnDateChange()">
    </div>
    <div class="field" style="margin-bottom:0;">
        <label>Tahun</label>
        <select name="tahun" class="select-pill" onchange="trendSkuOnTahunChange()">
            <option value="">—</option>
            @for ($y = now()->year; $y >= now()->year - 4; $y--)
                <option value="{{ $y }}" {{ (int) $tahun === $y ? 'selected' : '' }}>{{ $y }}</option>
            @endfor
        </select>
    </div>
    @if ($trendSku && $impactOptions->isNotEmpty())
        <div class="field" style="margin-bottom:0; min-width:240px;">
            <label>SKU Terimpact (bandingkan)</label>
            <select name="impact_produk_id" class="select-pill" onchange="trendSkuReload()">
                <option value="">— Gak dibandingkan —</option>
                @foreach ($impactOptions as $opt)
                    <option value="{{ $opt->id }}" {{ $impactProduk && $impactProduk->id === $opt->id ? 'selected' : '' }}>{{ $opt->nama }}</option>
                @endforeach
            </select>
        </div>
    @endif
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
                    &middot; Rentang chart: {{ \Carbon\Carbon::parse($dariTanggal)->format('d/m/Y') }} &ndash; {{ \Carbon\Carbon::parse($sampaiTanggal)->format('d/m/Y') }}
                    ({{ abs(\Carbon\Carbon::parse($dariTanggal)->diffInDays(\Carbon\Carbon::parse($trendSku->tanggal_mulai_campaign))) }} hari sebelum &amp; {{ abs(\Carbon\Carbon::parse($trendSku->tanggal_mulai_campaign)->diffInDays(\Carbon\Carbon::parse($sampaiTanggal))) + 1 }} hari sesudah campaign)
                </div>
            </div>
            <div style="display:flex; gap:6px;">
                <button type="button" id="btn-metric-omset" class="btn btn-primary" style="width:auto; font-size:12px; padding:6px 14px;" onclick="setTrendSkuMetric('omset')">Omset</button>
                <button type="button" id="btn-metric-qty" class="btn" style="width:auto; font-size:12px; padding:6px 14px;" onclick="setTrendSkuMetric('qty')">Qty</button>
            </div>
        </div>

        @if ($chart && $chart['mode'] === 'compare')
            <div style="display:flex; flex-wrap:wrap; gap:16px; align-items:center; font-size:11.5px; color:var(--ink-muted); margin-bottom:10px;">
                <span><span style="display:inline-block; width:14px; height:2.5px; background:var(--accent); vertical-align:middle; margin-right:5px;"></span>{{ $trendSku->produk->nama }} &mdash;
                    @foreach (['omset', 'qty'] as $metric)
                        <span id="trend-badge-A-{{ $metric }}" style="{{ $metric === 'omset' ? '' : 'display:none;' }}">{!! $trendBadge($chart[$metric]['trendA']) !!}</span>
                    @endforeach
                </span>
                <span><span style="display:inline-block; width:14px; height:2.5px; background:var(--good); vertical-align:middle; margin-right:5px; border-top:2.5px dashed var(--good);"></span>{{ $impactProduk->nama }} &mdash;
                    @foreach (['omset', 'qty'] as $metric)
                        <span id="trend-badge-B-{{ $metric }}" style="{{ $metric === 'omset' ? '' : 'display:none;' }}">{!! $trendBadge($chart[$metric]['trendB']) !!}</span>
                    @endforeach
                </span>
                <span style="color:var(--ink-faint);">
                    (garis dinormalisasi ke % dari puncak masing-masing; garis putus-putus tipis = garis tren)<br>
                    % dihitung dari rata-rata sebelum vs sesudah tanggal Mulai Campaign, jumlah hari sama persis di kedua sisi
                </span>
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
                    @php $trendA = $chart[$metric]['trendA']; $trendB = $chart[$metric]['trendB']; @endphp
                    @if ($trendA['points'])
                        <polyline id="trend-sku-trendA-{{ $metric }}" points="{{ $trendA['points'] }}" fill="none" stroke="{{ $trendA['arah'] === 'naik' ? 'var(--good)' : ($trendA['arah'] === 'turun' ? 'var(--critical)' : 'var(--ink-faint)') }}" stroke-width="1.5" stroke-dasharray="2,3" opacity="0.8" style="{{ $metric === 'omset' ? '' : 'display:none;' }}"/>
                    @endif
                    @if ($trendB['points'])
                        <polyline id="trend-sku-trendB-{{ $metric }}" points="{{ $trendB['points'] }}" fill="none" stroke="{{ $trendB['arah'] === 'naik' ? 'var(--good)' : ($trendB['arah'] === 'turun' ? 'var(--critical)' : 'var(--ink-faint)') }}" stroke-width="1.5" stroke-dasharray="2,3" opacity="0.5" style="{{ $metric === 'omset' ? '' : 'display:none;' }}"/>
                    @endif
                    <polyline id="trend-sku-lineA-{{ $metric }}" points="{{ $chart[$metric]['pointsA'] }}" fill="none" stroke="var(--accent)" stroke-width="2" style="{{ $metric === 'omset' ? '' : 'display:none;' }}"/>
                    <polyline id="trend-sku-lineB-{{ $metric }}" points="{{ $chart[$metric]['pointsB'] }}" fill="none" stroke="var(--good)" stroke-width="2" stroke-dasharray="5,4" style="{{ $metric === 'omset' ? '' : 'display:none;' }}"/>
                @endforeach

                @foreach ($chart['xLabels'] as $xl)
                    <text x="{{ $xl['x'] }}" y="{{ $chart['padTop'] + $chart['plotHeight'] + 20 }}" text-anchor="middle" font-size="10" fill="var(--ink-muted)">{{ $xl['label'] }}</text>
                @endforeach
            </svg>
        @elseif ($chart)
            <div style="display:flex; gap:10px; align-items:center; font-size:12px; margin-bottom:10px;">
                <span style="color:var(--ink-muted);">Tren:</span>
                @foreach (['omset', 'qty'] as $metric)
                    <span id="trend-badge-{{ $metric }}" style="{{ $metric === 'omset' ? '' : 'display:none;' }}">{!! $trendBadge($chart[$metric]['trend'], '13px') !!}</span>
                @endforeach
                <span style="color:var(--ink-faint); font-size:11px;">
                    (garis putus-putus tipis di chart = garis tren) &middot; % dihitung dari rata-rata sebelum vs sesudah tanggal Mulai Campaign, jumlah hari sama persis di kedua sisi
                </span>
            </div>

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
                    @php $trend = $chart[$metric]['trend']; @endphp
                    @if ($trend['points'])
                        <polyline id="trend-sku-trend-{{ $metric }}" points="{{ $trend['points'] }}" fill="none" stroke="{{ $trend['arah'] === 'naik' ? 'var(--good)' : ($trend['arah'] === 'turun' ? 'var(--critical)' : 'var(--ink-faint)') }}" stroke-width="2" stroke-dasharray="2,4" style="{{ $metric === 'omset' ? '' : 'display:none;' }}"/>
                    @endif
                    <polyline id="trend-sku-line-{{ $metric }}" points="{{ $chart[$metric]['points'] }}" fill="none" stroke="var(--accent)" stroke-width="2" style="{{ $metric === 'omset' ? '' : 'display:none;' }}"/>
                @endforeach

                @foreach ($chart['xLabels'] as $xl)
                    <text x="{{ $xl['x'] }}" y="{{ $chart['padTop'] + $chart['plotHeight'] + 20 }}" text-anchor="middle" font-size="10" fill="var(--ink-muted)">{{ $xl['label'] }}</text>
                @endforeach
            </svg>
        @else
            <div style="color:var(--ink-muted); font-size:13px;">Belum ada data penjualan di rentang tanggal ini.</div>
        @endif
    </section>
@endif
