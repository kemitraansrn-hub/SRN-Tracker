@extends('layouts.app')

@php
    $rp = fn ($v) => $v === null ? '—' : 'Rp'.number_format((float) $v, 0, ',', '.');
@endphp

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:22px; flex-wrap:wrap;">
        <div>
            <h1 class="display" style="font-size:24px;">Omset Bulanan</h1>
            <div style="color:var(--ink-muted); font-size:13px; margin-top:4px;">
                Total omset tiap bulan, pertumbuhan bulan sebelumnya (MoM), dan tahun lalu (YoY).
            </div>
        </div>
        <form method="GET" action="{{ route('omset-bulanan.index') }}" class="field-row" style="margin-bottom:0; align-items:flex-end;">
            @if ($kaeOptions->isNotEmpty())
                <div class="field" style="margin-bottom:0;">
                    <label>KAE</label>
                    <select name="kae_code" class="select-pill" onchange="this.form.submit()">
                        <option value="">Semua KAE</option>
                        @foreach ($kaeOptions as $k)
                            <option value="{{ $k->kae_code }}" {{ $kaeCode === $k->kae_code ? 'selected' : '' }}>{{ $k->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="field" style="margin-bottom:0;">
                <label>Tahun</label>
                <select name="tahun" class="select-pill" onchange="this.form.submit()">
                    @for ($y = now()->year; $y >= 2023; $y--)
                        <option value="{{ $y }}" {{ $tahun == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
        </form>
    </div>

    @if ($sumberIni === 'historis' && $kaeCode)
        <div class="alert-error" style="margin-bottom:16px;">Data {{ $tahun }} untuk KAE ini tidak tersedia &mdash; tahun ini belum ada data order asli, dan rekap historis tidak punya breakdown per KAE.</div>
    @endif

    <section style="display:flex; gap:16px; margin-bottom:20px; flex-wrap:wrap;">
        <div class="card" style="display:inline-block;">
            <div class="info-label" style="margin-bottom:10px;">
                Total Omset {{ $tahun }}
                @if ($sumberIni === 'historis')
                    <span class="chip chip-neutral" style="margin-left:6px;">Data Historis</span>
                @endif
            </div>
            <div style="font-size:25px; font-weight:700;" class="tnum">{{ $rp($totalOmset) }}</div>
            <div style="margin-top:10px;">
                @if ($growthYtd === null)
                    <span style="font-size:12px; color:var(--ink-faint);">Tidak ada data pembanding</span>
                @else
                    @php $cYtd = $growthYtd > 0 ? 'good' : ($growthYtd < 0 ? 'critical' : 'neutral'); @endphp
                    <span class="chip chip-{{ $cYtd }}">Growth YTD {{ $growthYtd > 0 ? '+' : '' }}{{ $growthYtd }}%</span>
                @endif
            </div>
        </div>

        <div class="card" style="display:inline-block;">
            <div class="info-label" style="margin-bottom:10px;">
                Omset {{ $tahun - 1 }} (YTD{{ $bulanTerakhirLabel ? ' s.d. '.$bulanTerakhirLabel : '' }})
                @if ($sumberLalu === 'historis')
                    <span class="chip chip-neutral" style="margin-left:6px;">Data Historis</span>
                @endif
            </div>
            <div style="font-size:25px; font-weight:700;" class="tnum">{{ $rp($omsetTahunLaluYtd) }}</div>
        </div>
    </section>

    <section class="card" style="margin-bottom:20px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
            <div class="card-title" style="margin:0;">Tren Omset per Bulan</div>
            <div style="display:flex; gap:14px; font-size:12px; color:var(--ink-muted); flex-wrap:wrap;">
                @foreach (array_reverse($chart['lines']) as $l)
                    <span style="display:inline-flex; align-items:center; gap:6px;"><span style="width:14px; height:3px; background:{{ $l['color'] }}; display:inline-block; border-radius:2px;"></span>{{ $l['year'] }}</span>
                @endforeach
            </div>
        </div>
        <div style="overflow-x:auto;">
            <svg viewBox="0 0 {{ $chart['width'] }} {{ $chart['height'] }}" style="width:100%; min-width:600px; height:auto;">
                @foreach ($chart['gridLines'] as $g)
                    <line x1="{{ $chart['left'] }}" y1="{{ $g['y'] }}" x2="{{ $chart['width'] - 20 }}" y2="{{ $g['y'] }}" stroke="var(--line)" stroke-width="1"/>
                    <text x="{{ $chart['left'] - 8 }}" y="{{ $g['y'] + 4 }}" text-anchor="end" font-size="10" fill="var(--ink-faint)">{{ $g['label'] }}</text>
                @endforeach

                @foreach ($chart['xLabels'] as $xl)
                    <text x="{{ $xl['x'] }}" y="{{ $chart['bottom'] + 18 }}" text-anchor="middle" font-size="10" fill="var(--ink-faint)">{{ $xl['label'] }}</text>
                @endforeach

                @foreach ($chart['lines'] as $l)
                    @foreach ($l['segments'] as $seg)
                        <polyline points="{{ $seg }}" fill="none" stroke="{{ $l['color'] }}" stroke-width="{{ $l['strokeWidth'] }}" stroke-opacity="{{ $l['opacity'] }}"/>
                    @endforeach
                    @foreach ($l['dots'] as $d)
                        <circle cx="{{ $d['x'] }}" cy="{{ $d['y'] }}" r="{{ $l['dotR'] }}" fill="{{ $l['color'] }}" fill-opacity="{{ $l['opacity'] }}"><title>{{ $d['label'] }} {{ $l['year'] }}: {{ $rp($d['v']) }}</title></circle>
                    @endforeach
                @endforeach
            </svg>
        </div>
    </section>

    <section class="card table-card" style="padding:0;">
        <div class="card-head" style="padding:18px 20px 0; margin-bottom:12px;">
            <div class="card-title">Rincian per Bulan</div>
            <div class="card-hint">{{ $tahun }} vs {{ $tahun - 1 }}</div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr><th>Bulan</th><th>Omset {{ $tahun }}</th><th style="min-width:160px;"></th><th>Growth MoM</th><th>Omset {{ $tahun - 1 }}</th><th>Growth YoY</th></tr>
                </thead>
                <tbody>
                    @foreach ($rows as $r)
                        <tr>
                            <td style="font-weight:600;">{{ $r['label'] }}</td>
                            <td class="tnum" style="{{ $r['omset'] ? '' : 'color:var(--ink-faint);' }}">{{ $rp($r['omset']) }}</td>
                            <td>
                                @if ($maxOmset > 0 && $r['omset'])
                                    @php $isBulanTertinggi = $r['omset'] == $maxOmset; @endphp
                                    <div style="height:8px; border-radius:4px; background:var(--line); overflow:hidden;">
                                        <div style="height:100%; border-radius:4px; background:{{ $isBulanTertinggi ? 'var(--good)' : 'var(--accent)' }}; width:{{ round($r['omset'] / $maxOmset * 100, 1) }}%;"></div>
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if ($r['mom'] === null)
                                    <span style="color:var(--ink-faint); font-size:12px;">—</span>
                                @else
                                    @php $c = $r['mom'] > 0 ? 'good' : ($r['mom'] < 0 ? 'critical' : 'neutral'); @endphp
                                    <span class="chip chip-{{ $c }}">{{ $r['mom'] > 0 ? '+' : '' }}{{ $r['mom'] }}%</span>
                                @endif
                            </td>
                            <td class="tnum" style="color:var(--ink-muted); font-size:12.5px;">{{ $rp($r['omset_tahun_lalu']) }}</td>
                            <td>
                                @if ($r['yoy'] === null)
                                    <span style="color:var(--ink-faint); font-size:12px;">—</span>
                                @else
                                    @php $c = $r['yoy'] > 0 ? 'good' : ($r['yoy'] < 0 ? 'critical' : 'neutral'); @endphp
                                    <span class="chip chip-{{ $c }}">{{ $r['yoy'] > 0 ? '+' : '' }}{{ $r['yoy'] }}%</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td style="font-weight:700;">Total</td>
                        <td class="tnum" style="font-weight:700;">{{ $rp($totalOmset) }}</td>
                        <td></td><td></td><td></td><td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>
@endsection
