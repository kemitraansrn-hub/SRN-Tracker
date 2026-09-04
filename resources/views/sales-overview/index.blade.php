@extends('layouts.app')

@php
    $rp = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');
    $avatarColors = ['var(--accent)', 'var(--good)', 'var(--warn)', 'var(--critical)', 'var(--chart-5)', 'var(--chart-6)'];
@endphp

@section('content')
    <div class="topbar" style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:18px; flex-wrap:wrap;">
        <div>
            <h1 class="display" style="font-size:24px;">Sales Overview</h1>
            <div style="color:var(--ink-muted); font-size:13px; margin-top:4px;">
                Ringkasan performa {{ $isKae ? 'kamu' : 'semua mitra' }} &mdash; {{ $periodeLabel }}
            </div>
        </div>
        @include('partials.bulan-tahun-filter', ['action' => route('sales-overview.index'), 'bulan' => $bulanIni, 'tahun' => $tahunIni, 'isBulanIni' => $isBulanIni])
    </div>

    @php
        $growthBadge = function ($g) {
            if ($g === null) {
                return '<span style="font-size:10.5px; color:var(--ink-faint);">vs bulan lalu: —</span>';
            }
            $color = $g >= 0 ? 'var(--good)' : 'var(--critical)';
            $path = $g >= 0 ? 'M6 15l6-6 6 6' : 'M6 9l6 6 6-6';
            return '<div style="display:flex; align-items:center; gap:4px;">'
                .'<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="'.$color.'" stroke-width="3"><path d="'.$path.'" stroke-linecap="round" stroke-linejoin="round"/></svg>'
                .'<span class="tnum" style="font-weight:700; font-size:11px; color:'.$color.';">'.($g >= 0 ? '+' : '').$g.'%</span>'
                .'<span style="font-size:10px; color:var(--ink-faint);">vs bulan lalu</span>'
                .'</div>';
        };
    @endphp
    <section style="display:grid; grid-template-columns:repeat(5, 1fr); gap:12px; margin-bottom:14px;">
        <div class="card" style="padding:13px 15px;">
            <div class="info-label" style="margin-bottom:7px; font-size:11px;">Total Omset</div>
            <div style="font-size:18px; font-weight:700;" class="tnum">{{ $rp($totalOmset) }}</div>
            <div style="margin-top:6px;">{!! $growthBadge($totalOmsetGrowth) !!}</div>
        </div>
        <div class="card" style="padding:13px 15px;">
            <div class="info-label" style="margin-bottom:7px; font-size:11px;">Jumlah Order</div>
            <div style="font-size:18px; font-weight:700;" class="tnum">{{ $jumlahOrder }}</div>
            <div style="margin-top:6px;">{!! $growthBadge($jumlahOrderGrowth) !!}</div>
        </div>
        <div class="card" style="padding:13px 15px;">
            <div class="info-label" style="margin-bottom:7px; font-size:11px;">Mitra Aktif</div>
            <div style="font-size:18px; font-weight:700;" class="tnum">{{ $mitraAktif }}</div>
            <div style="margin-top:6px;">{!! $growthBadge($mitraAktifGrowth) !!}</div>
        </div>
        <div class="card" style="padding:13px 15px;">
            <div class="info-label" style="margin-bottom:7px; font-size:11px;">Rata-rata Order</div>
            <div style="font-size:18px; font-weight:700;" class="tnum">{{ $rp($rataRataOrder) }}</div>
            <div style="margin-top:6px;">{!! $growthBadge($rataRataOrderGrowth) !!}</div>
        </div>
        <div class="card" style="padding:13px 15px;">
            <div class="info-label" style="margin-bottom:7px; font-size:11px;">Buy Back Pending</div>
            <div style="font-size:18px; font-weight:700;" class="tnum">{{ $buybackPending }}</div>
        </div>
    </section>

    <section style="display:grid; grid-template-columns:repeat(12, 1fr); gap:14px; margin-bottom:14px; align-items:stretch;">
        <div class="card" style="grid-column:span 4; padding:14px 16px; display:flex; flex-direction:column;">
            <div class="card-title" style="font-size:13px; margin-bottom:2px;">Sales Funnel</div>
            <div class="card-hint" style="margin-bottom:12px;">Follow-up &rarr; Belanja, {{ $periodeLabel }}</div>
            <div style="flex:1; display:flex; flex-direction:column; justify-content:space-between; gap:10px;">
                @foreach ($funnel as $s)
                    <div>
                        <div style="display:flex; justify-content:space-between; font-size:11.5px; margin-bottom:4px;">
                            <span style="font-weight:600;">{{ $s['label'] }}</span>
                            <span class="tnum" style="color:var(--ink-muted);">{{ $s['value'] }}</span>
                        </div>
                        <div style="height:18px; border-radius:5px; background:var(--line); overflow:hidden;">
                            <div style="height:100%; border-radius:5px; background:var(--accent); width:{{ $s['width_pct'] }}%; margin:0 auto;"></div>
                        </div>
                        @if ($s['conv_pct'] !== null)
                            <div style="text-align:right; font-size:10.5px; color:var(--ink-faint); margin-top:2px;">{{ $s['conv_pct'] }}% dari tahap sebelumnya</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        <div class="card" style="grid-column:span 8; padding:14px 16px; display:flex; flex-direction:column;">
            <div class="card-title" style="font-size:13px; margin-bottom:2px;">Pencapaian KAE</div>
            <div class="card-hint" style="margin-bottom:12px;">Omset &amp; mitra aktif vs target, reactivation &amp; new mitra &mdash; {{ $periodeLabel }}</div>
            <div style="flex:1; display:grid; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); gap:12px;">
                @forelse ($kaeAchievements as $k)
                    @php
                        $color = $avatarColors[$loop->index % count($avatarColors)];
                        $pctChip = fn ($pct) => $pct === null ? null : ($pct >= 100 ? 'good' : ($pct >= 70 ? 'warn' : 'critical'));
                    @endphp
                    <div style="border:1px solid var(--line); border-radius:14px; padding:16px; display:flex; flex-direction:column; justify-content:space-between; gap:14px;">
                        <div style="display:flex; align-items:center; gap:12px;">
                            @if ($k['photo_url'])
                                <img src="{{ $k['photo_url'] }}" alt="{{ $k['name'] }}" style="width:52px; height:52px; border-radius:50%; object-fit:cover; flex:none;">
                            @else
                                <div style="width:52px; height:52px; border-radius:50%; background:{{ $color }}; color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:17px; flex:none;">{{ $k['initials'] }}</div>
                            @endif
                            <div style="min-width:0; flex:1;">
                                <div style="font-weight:700; font-size:14.5px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $k['name'] }}</div>
                                <div class="tnum" style="font-size:13px; color:var(--ink-muted);">{{ $rp($k['omset']) }}</div>
                            </div>
                            @if ($k['omset_pct'] !== null)
                                <span class="chip chip-{{ $pctChip($k['omset_pct']) }}" style="font-size:11px; flex:none;">{{ $k['omset_pct'] }}%</span>
                            @endif
                        </div>
                        <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:8px; text-align:center; border-top:1px solid var(--line); padding-top:12px;">
                            <div>
                                <div class="tnum" style="font-weight:700; font-size:17px;">
                                    {{ $k['mitra_aktif'] }}@if ($k['mitra_aktif_target'])<span style="font-size:11.5px; font-weight:400; color:var(--ink-faint);">/{{ $k['mitra_aktif_target'] }}</span>@endif
                                </div>
                                <div style="font-size:10.5px; color:var(--ink-muted);">Mitra Aktif</div>
                                @if ($k['mitra_aktif_pct'] !== null)
                                    <div class="tnum" style="font-size:10.5px; font-weight:700; color:var(--{{ $pctChip($k['mitra_aktif_pct']) }});">{{ $k['mitra_aktif_pct'] }}%</div>
                                @endif
                            </div>
                            <div>
                                <div class="tnum" style="font-weight:700; font-size:17px;">{{ $k['reactivation'] }}</div>
                                <div style="font-size:10.5px; color:var(--ink-muted);">Reactivation</div>
                            </div>
                            <div>
                                <div class="tnum" style="font-weight:700; font-size:17px;">{{ $k['new_mitra'] }}</div>
                                <div style="font-size:10.5px; color:var(--ink-muted);">New Mitra</div>
                            </div>
                        </div>
                    </div>
                @empty
                    <span style="color:var(--ink-faint); font-size:12.5px;">Belum ada data.</span>
                @endforelse
            </div>
        </div>
    </section>

    <section style="display:grid; grid-template-columns:repeat(12, 1fr); gap:14px; margin-bottom:14px; align-items:stretch;">
        <div class="card" style="grid-column:span 4; padding:14px 16px;">
            <div class="card-title" style="font-size:13px; margin-bottom:10px;">Omset per Brand</div>
            @include('partials.doughnut-chart', ['segments' => $brandSegments, 'size' => 120, 'strokeWidth' => 16])
        </div>
        <div class="card" style="grid-column:span 4; padding:14px 16px;">
            <div class="card-title" style="font-size:13px; margin-bottom:10px;">Mitra per Segmentasi</div>
            @include('partials.doughnut-chart', ['segments' => $segmenSegments, 'size' => 120, 'strokeWidth' => 16])
        </div>
        <div class="card" style="grid-column:span 4; padding:14px 16px;">
            <div class="card-title" style="font-size:13px; margin-bottom:2px;">Mitra per Bracket Omset</div>
            <div class="card-hint" style="margin-bottom:10px;">{{ $periodeLabel }}</div>
            <div style="display:flex; align-items:flex-end; gap:10px; height:120px; padding:0 4px;">
                @foreach ($bracketOmset as $b)
                    <div style="flex:1; display:flex; flex-direction:column; align-items:center; justify-content:flex-end; height:100%;">
                        <div class="tnum" style="font-size:11px; font-weight:700; margin-bottom:5px;">{{ $b['jumlah'] }}</div>
                        <div style="width:100%; max-width:34px; border-radius:5px 5px 0 0; background:var(--accent); height:{{ max($b['pct'], $b['jumlah'] > 0 ? 4 : 0) }}%;"></div>
                        <div style="font-size:9.5px; color:var(--ink-muted); margin-top:6px; text-align:center;">{{ $b['label'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section style="display:grid; grid-template-columns:repeat(12, 1fr); gap:14px; align-items:stretch;">
        <div class="card" style="grid-column:span {{ $kaeContribSegments ? 6 : 12 }}; padding:14px 16px;">
            <div class="card-title" style="font-size:13px; margin-bottom:2px;">Top 10 Mitra</div>
            <div class="card-hint" style="margin-bottom:12px;">By omset {{ $periodeLabel }}</div>
            <div style="display:flex; flex-direction:column; gap:8px;">
                @forelse ($top10Mitra as $m)
                    <div>
                        <div style="display:flex; justify-content:space-between; font-size:11.5px; margin-bottom:3px;">
                            <span style="font-weight:600;">{{ $m['nama'] }}</span>
                            <span class="tnum" style="color:var(--ink-muted);">{{ $rp($m['total']) }}</span>
                        </div>
                        <div style="height:7px; border-radius:4px; background:var(--line); overflow:hidden;">
                            <div style="height:100%; border-radius:4px; background:var(--accent); width:{{ $m['pct'] }}%;"></div>
                        </div>
                    </div>
                @empty
                    <span style="color:var(--ink-faint); font-size:12.5px;">Belum ada data.</span>
                @endforelse
            </div>
        </div>

        @if ($kaeContribSegments)
            <div style="grid-column:span 6; display:flex; flex-direction:column; gap:14px;">
                <div class="card" style="padding:14px 16px; flex:1; display:flex; flex-direction:column;">
                    <div class="card-title" style="font-size:13px; margin-bottom:2px;">Contribute KAE</div>
                    <div class="card-hint" style="margin-bottom:8px;">Kontribusi omset per KAE, {{ $periodeLabel }}</div>
                    <div style="flex:1; display:flex; align-items:center;">
                        @include('partials.doughnut-chart', ['segments' => $kaeContribSegments, 'size' => 100, 'strokeWidth' => 14])
                    </div>
                </div>
                <div class="card" style="padding:14px 16px; flex:1; display:flex; flex-direction:column;">
                    <div class="card-title" style="font-size:13px; margin-bottom:2px;">Contribute per Segmen Mitra</div>
                    <div class="card-hint" style="margin-bottom:8px;">Kontribusi omset per segmen, {{ $periodeLabel }}</div>
                    <div style="flex:1; display:flex; align-items:center;">
                        @include('partials.doughnut-chart', ['segments' => $segmenContribSegments, 'size' => 100, 'strokeWidth' => 14])
                    </div>
                </div>
            </div>
        @endif
    </section>

    @if ($mitraOmsetScatter !== null)
        <section style="margin-top:14px;">
            <div class="card" style="padding:14px 16px;">
                <div class="card-title" style="font-size:13px; margin-bottom:2px;">Korelasi Mitra Aktif vs Omset per KAE</div>
                <div class="card-hint" style="margin-bottom:10px;">
                    Tiap titik = 1 KAE, {{ $periodeLabel }}. Garis putus-putus = tren umum (regresi linear).
                </div>
                @if (count($mitraOmsetScatter) >= 2)
                    @include('partials.scatter-chart', [
                        'points' => $mitraOmsetScatter,
                        'xLabel' => 'Jumlah Mitra Aktif Berbelanja',
                        'yLabel' => 'Omset (Rp)',
                        'formatY' => fn ($v) => $v >= 1_000_000 ? number_format($v / 1_000_000, 1, ',', '.').'jt' : number_format($v, 0, ',', '.'),
                    ])
                @else
                    <span style="color:var(--ink-faint); font-size:12.5px;">Butuh minimal 2 KAE dengan data bulan ini buat tampilin korelasinya.</span>
                @endif
            </div>
        </section>
    @endif
@endsection
