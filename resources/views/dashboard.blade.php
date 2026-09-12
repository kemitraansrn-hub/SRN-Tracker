@extends('layouts.app')

@php
    $rp = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');
@endphp

@section('content')
    <h1 class="display" style="font-size:24px; margin-bottom:16px;">Dashboard</h1>

    <div style="display:flex; gap:4px; margin-bottom:22px; border-bottom:1px solid var(--line);">
        <button type="button" id="dashtab-sales-btn" onclick="switchDashboardTab('sales')" style="padding:10px 4px; margin-right:22px; background:none; border:none; border-bottom:2px solid var(--accent); font-family:inherit; font-size:14px; font-weight:700; color:var(--ink); cursor:pointer;">Sales</button>
        <button type="button" id="dashtab-development-btn" onclick="switchDashboardTab('development')" style="padding:10px 4px; margin-right:22px; background:none; border:none; border-bottom:2px solid transparent; font-family:inherit; font-size:14px; font-weight:600; color:var(--ink-muted); cursor:pointer;">Development</button>
    </div>

    <div id="dashtab-sales">
    <div class="topbar" style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:22px; flex-wrap:wrap;">
        <div style="color:var(--ink-muted); font-size:13px;">
            Ringkasan performa {{ auth()->user()->canViewAll() ? 'semua mitra' : 'mitra kamu' }} &mdash; {{ $periodeLabel }}
        </div>
        @include('partials.bulan-tahun-filter', ['action' => route('dashboard'), 'bulan' => $bulanIni, 'tahun' => $tahunIni, 'isBulanIni' => $isBulanIni])
    </div>

    @php $kpiTileCount = ($companyTarget ? 1 : 0) + 1 + ($trendCard ? 1 : 0); @endphp
    <section style="display:grid; grid-template-columns:repeat(12, 1fr); gap:16px; margin-bottom:20px; align-items:stretch;">
        <div style="grid-column:span {{ $pencapaianTigaTier ? 6 : 12 }}; min-width:0; display:grid; grid-template-columns:repeat({{ $kpiTileCount }}, 1fr); gap:16px;">
            @if ($companyTarget)
                @php
                    $donutR = 40;
                    $donutCirc = 2 * M_PI * $donutR;
                    $donutFilled = min($companyAchPct, 100) / 100 * $donutCirc;
                @endphp
                <div class="card" style="min-width:0;">
                    <div class="info-label" style="margin-bottom:10px;">Target Perusahaan vs Pencapaian</div>
                    <div style="display:flex; align-items:center; gap:14px;">
                        <svg width="88" height="88" viewBox="0 0 100 100" style="flex-shrink:0;">
                            <circle cx="50" cy="50" r="{{ $donutR }}" fill="none" stroke="var(--line)" stroke-width="11"/>
                            <circle cx="50" cy="50" r="{{ $donutR }}" fill="none" stroke="var(--accent)" stroke-width="11"
                                stroke-linecap="round" transform="rotate(-90 50 50)"
                                stroke-dasharray="{{ $donutFilled }} {{ $donutCirc }}"/>
                            <text x="50" y="50" text-anchor="middle" dominant-baseline="central" class="tnum" style="font-size:19px; font-weight:700; fill:var(--ink);">{{ $companyAchPct }}%</text>
                        </svg>
                        <div style="flex:1; min-width:0;">
                            <div style="display:flex; align-items:center; gap:6px;">
                                <span style="width:8px; height:8px; border-radius:50%; background:var(--accent); flex-shrink:0;"></span>
                                <span style="font-size:11.5px; color:var(--ink-muted);">Pencapaian</span>
                            </div>
                            <div class="tnum" style="font-size:13px; font-weight:700; margin:2px 0 8px 14px;">{{ $rp($totalOmsetBulanIni) }}</div>
                            <div style="display:flex; align-items:center; gap:6px;">
                                <span style="width:8px; height:8px; border-radius:50%; background:var(--line); flex-shrink:0;"></span>
                                <span style="font-size:11.5px; color:var(--ink-muted);">Target</span>
                            </div>
                            <div class="tnum" style="font-size:13px; font-weight:700; margin:2px 0 0 14px;">{{ $rp($companyTarget) }}</div>
                        </div>
                    </div>
                </div>
            @endif
            <div class="card" style="min-width:0;">
                <div class="info-label" style="margin-bottom:10px;">MTD vs Bulan Lalu</div>
                <div style="display:flex; align-items:center; justify-content:space-between; gap:8px;">
                    <div style="font-size:19px; font-weight:700; min-width:0; flex-shrink:1; overflow-wrap:anywhere;" class="tnum">{{ $rp($mtdIni) }}</div>
                    @if ($mtdGrowthPct !== null)
                        <svg width="40" height="22" viewBox="0 0 40 22" fill="none" style="flex-shrink:0;">
                            <polyline points="{{ $mtdGrowthPct >= 0 ? '0,18 8,14 16,15 24,8 32,10 40,2' : '0,4 8,8 16,7 24,14 32,12 40,20' }}" stroke="{{ $mtdGrowthPct >= 0 ? 'var(--good)' : 'var(--critical)' }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    @endif
                </div>
                <div style="font-size:11.5px; color:var(--ink-muted); margin-top:2px;">vs {{ $rp($mtdLalu) }} ({{ $dayCap }} hari pertama bulan lalu)</div>
                <div style="margin-top:10px; display:flex; align-items:center; gap:5px;">
                    @if ($mtdGrowthPct === null)
                        <span style="font-size:12px; color:var(--ink-faint);">Tidak ada data pembanding</span>
                    @else
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="{{ $mtdGrowthPct >= 0 ? 'var(--good)' : 'var(--critical)' }}" stroke-width="2.5">
                            @if ($mtdGrowthPct >= 0)
                                <path d="M6 15l6-6 6 6" stroke-linecap="round" stroke-linejoin="round"/>
                            @else
                                <path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                            @endif
                        </svg>
                        <span class="tnum" style="font-weight:700; font-size:14px; color:{{ $mtdGrowthPct >= 0 ? 'var(--good)' : 'var(--critical)' }};">{{ $mtdGrowthPct >= 0 ? '+' : '' }}{{ $mtdGrowthPct }}%</span>
                    @endif
                </div>
            </div>
            @if ($trendCard)
                <div class="card" style="min-width:0;">
                    <div class="info-label" style="margin-bottom:10px;">{{ $trendCard['label'] }}</div>
                    @php $g = $trendCard['growth']; @endphp
                    <div style="display:flex; align-items:center; justify-content:space-between; gap:8px;">
                        <div style="font-size:19px; font-weight:700; min-width:0; flex-shrink:1; overflow-wrap:anywhere;" class="tnum">{{ $rp($trendCard['omset_ini']) }}</div>
                        @if ($g !== null)
                            <svg width="40" height="22" viewBox="0 0 40 22" fill="none" style="flex-shrink:0;">
                                <polyline points="{{ $g >= 0 ? '0,18 8,14 16,15 24,8 32,10 40,2' : '0,4 8,8 16,7 24,14 32,12 40,20' }}" stroke="{{ $g >= 0 ? 'var(--good)' : 'var(--critical)' }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        @endif
                    </div>
                    <div style="margin-top:10px; display:flex; align-items:center; gap:5px;">
                        @if ($g === null)
                            <span style="font-size:12px; color:var(--ink-faint);">Tidak ada data pembanding</span>
                        @else
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="{{ $g >= 0 ? 'var(--good)' : 'var(--critical)' }}" stroke-width="2.5">
                                @if ($g >= 0)
                                    <path d="M6 15l6-6 6 6" stroke-linecap="round" stroke-linejoin="round"/>
                                @else
                                    <path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                                @endif
                            </svg>
                            <span class="tnum" style="font-weight:700; font-size:14px; color:{{ $g >= 0 ? 'var(--good)' : 'var(--critical)' }};">{{ $g >= 0 ? '+' : '' }}{{ $g }}%</span>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        @if ($pencapaianTigaTier)
            <div class="card" style="grid-column:span 6; min-width:0;">
                <div class="card-head" style="flex-wrap:wrap; row-gap:4px;">
                    <div class="card-title" style="flex-shrink:0;">Pencapaian vs 3 Tier Target</div>
                    <div class="card-hint" style="white-space:nowrap;">Omset {{ $periodeLabel }}: {{ $rp($totalOmsetBulanIni) }}</div>
                </div>
                <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:16px; min-width:0;">
                    @foreach (['komit' => 'Komit', 'target' => 'Target', 'stretch' => 'Stretch'] as $key => $label)
                        @php $t = $pencapaianTigaTier[$key]; @endphp
                        <div style="min-width:0;">
                            <div class="info-label" style="margin-bottom:6px;">{{ $label }}</div>
                            <div style="font-size:13px; color:var(--ink-muted); margin-bottom:8px; overflow-wrap:anywhere;" class="tnum">{{ $rp($t['target']) }}</div>
                            <div style="height:6px; border-radius:4px; background:var(--line); overflow:hidden;">
                                <div style="height:100%; border-radius:4px; background:var(--accent); width:{{ $t['pct'] !== null ? min($t['pct'], 100) : 0 }}%;"></div>
                            </div>
                            <div style="margin-top:8px;">
                                @if ($t['pct'] === null)
                                    <span style="font-size:11.5px; color:var(--ink-faint);">Belum ada target</span>
                                @else
                                    @php $tColor = $t['pct'] >= 100 ? 'good' : ($t['pct'] >= 70 ? 'warn' : 'critical'); @endphp
                                    <span class="chip chip-{{ $tColor }}">{{ $t['pct'] }}%</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </section>

    @unless ($adaTargetBulanIni)
        <div class="alert-error" style="background:var(--warn-soft); color:var(--warn);">
            Target bulanan untuk {{ $periodeLabel }} belum di-import, jadi Achievement % dan status mitra belum bisa ditampilkan. KPI di bawah murni dari data order yang sudah masuk.
        </div>
    @endunless

    @if ($jumlahOrderBulanIni === 0)
        <div class="card" style="text-align:center; padding:40px 20px; color:var(--ink-muted);">
            Belum ada data order untuk {{ $periodeLabel }}.
            @if (auth()->user()->hasAdminAccess())
                <br>Mulai dengan <a href="{{ route('import.index') }}" style="color:var(--accent-ink); font-weight:600;">import data harian</a>.
            @endif
        </div>
    @else
        @if ($runRateWeekly)
            <section class="card table-card reveal-on-scroll" style="padding:0; margin-bottom:20px;">
                <div class="card-head" style="padding:18px 20px 0; margin-bottom:12px; text-align:center; display:block;">
                    <div class="card-title" style="text-transform:uppercase;">{{ auth()->user()->canViewAll() ? 'Kemitraan' : auth()->user()->name }}</div>
                    <div class="card-title" style="text-transform:uppercase;">Run Rate Weekly</div>
                </div>
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Week</th>
                                @foreach ($runRateWeekly as $w => $data)
                                    @php $isCurrentWeek = 'W'.$w === $currentWeekLabel; @endphp
                                    <th>
                                        W{{ $w }}
                                        <div style="font-weight:400; font-size:10.5px; color:var(--ink-muted); text-transform:none;">s/d {{ $runRateWeekEndDate[$w]->format('d/m') }}</div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            <tr style="font-weight:700;">
                                <td>Target</td>
                                @foreach ($runRateWeekly as $w => $data)
                                    @php $isCurrentWeek = 'W'.$w === $currentWeekLabel; @endphp
                                    <td class="tnum" style="{{ $isCurrentWeek ? 'background:var(--accent-soft);' : '' }}">{{ $rp($data['target']) }}</td>
                                @endforeach
                            </tr>
                            <tr>
                                <td>Run Rate Weekly</td>
                                @foreach ($runRateWeekly as $w => $data)
                                    @php $isCurrentWeek = 'W'.$w === $currentWeekLabel; @endphp
                                    <td class="tnum" style="color:var(--ink-muted); {{ $isCurrentWeek ? 'background:var(--accent-soft);' : '' }}">{{ $rp($data['run_rate']) }}</td>
                                @endforeach
                            </tr>
                            <tr>
                                <td>Growth</td>
                                @foreach ($runRateWeekly as $w => $data)
                                    @php $g = $data['growth']; $isCurrentWeek = 'W'.$w === $currentWeekLabel; @endphp
                                    <td class="tnum" style="{{ $isCurrentWeek ? 'background:var(--accent-soft);' : '' }} {{ $g !== null && $g < 0 ? 'color:var(--critical);' : ($g !== null ? 'color:var(--good);' : '') }}">
                                        {{ $g !== null ? $g.'%' : '—' }}
                                    </td>
                                @endforeach
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if ($adaTargetBulanIni && $specialDealPerformance->isNotEmpty())
            <section class="card table-card reveal-on-scroll" style="padding:0; margin-bottom:20px;">
                <div class="card-head" style="padding:18px 20px 0; margin-bottom:12px;">
                    <div class="card-title">Special Deal Performance</div>
                    <div class="card-hint">{{ $periodeLabel }}</div>
                </div>
                <div class="table-scroll" style="max-height:none; overflow-y:visible;">
                    <table>
                        <thead>
                            <tr>
                                <th>Segmen Mitra</th><th>Jumlah Mitra</th><th>Mitra Active</th><th>Mitra Belanja Full</th>
                                <th>Target</th><th>Ach</th><th>Ach %</th><th>Succes Rate</th><th>GAP</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($specialDealPerformance as $r)
                                @php $isTotal = $r->segmen === 'All Chanel'; $belumBelanja = $r->mitra_belum_belanja ?? collect(); $belanjaFullList = $r->mitra_belanja_full_list ?? collect(); @endphp
                                <tr style="{{ $isTotal ? 'font-weight:700; background:var(--surface-alt);' : '' }}">
                                    <td>
                                        <div>{{ $r->segmen }}</div>
                                        @if ($belumBelanja->isNotEmpty())
                                            <span class="reveal-toggle" style="margin-top:3px;" onclick="
                                                const d = document.getElementById('sdp-belum-{{ $loop->index }}');
                                                const open = d.style.display === 'none';
                                                d.style.display = open ? '' : 'none';
                                                this.classList.toggle('is-open', open);
                                            ">
                                                {{ $belumBelanja->count() }} belum belanja
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                                            </span>
                                        @endif
                                    </td>
                                    <td class="tnum">{{ $r->jumlah_mitra }}</td>
                                    <td class="tnum">{{ $r->mitra_active }}</td>
                                    <td class="tnum">
                                        <div>{{ $r->mitra_belanja_full }}</div>
                                        @if ($belanjaFullList->isNotEmpty())
                                            <span class="reveal-toggle" style="margin-top:3px;" onclick="
                                                const d = document.getElementById('sdp-full-{{ $loop->index }}');
                                                const open = d.style.display === 'none';
                                                d.style.display = open ? '' : 'none';
                                                this.classList.toggle('is-open', open);
                                            ">
                                                lihat mitra
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                                            </span>
                                        @endif
                                    </td>
                                    <td class="tnum">{{ $r->target !== null ? $rp($r->target) : '—' }}</td>
                                    <td class="tnum">{{ $rp($r->ach) }}</td>
                                    <td class="tnum">
                                        @if ($r->ach_pct === null)
                                            —
                                        @else
                                            @php $achColor = $r->ach_pct >= 100 ? 'good' : ($r->ach_pct >= 70 ? 'warn' : 'critical'); @endphp
                                            <span class="chip chip-{{ $achColor }}">{{ $r->ach_pct }}%</span>
                                        @endif
                                    </td>
                                    <td class="tnum">
                                        @if ($r->succes_rate === null)
                                            —
                                        @else
                                            @php $srColor = $r->succes_rate >= 70 ? 'good' : ($r->succes_rate >= 40 ? 'warn' : 'critical'); @endphp
                                            <span class="chip chip-{{ $srColor }}">{{ $r->succes_rate }}%</span>
                                        @endif
                                    </td>
                                    <td class="tnum" style="{{ $r->gap !== null && $r->gap < 0 ? 'color:var(--critical);' : ($r->gap !== null ? 'color:var(--good);' : '') }}">
                                        {{ $r->gap !== null ? $rp($r->gap) : '—' }}
                                    </td>
                                </tr>
                                @if ($belumBelanja->isNotEmpty())
                                    <tr id="sdp-belum-{{ $loop->index }}" style="display:none;">
                                        <td colspan="9" style="background:var(--surface-alt); padding:14px 20px;">
                                            <div class="info-label" style="margin-bottom:8px;">Mitra {{ $r->segmen }} yang belum belanja bulan ini ({{ $belumBelanja->count() }})</div>
                                            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(200px, 1fr)); gap:6px 16px;">
                                                @foreach ($belumBelanja as $bb)
                                                    <div style="font-size:12px; color:var(--ink-muted);">{{ $bb['nama'] }} <span style="color:var(--ink-faint);">({{ $bb['kode_mitra'] }})</span></div>
                                                @endforeach
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                                @if ($belanjaFullList->isNotEmpty())
                                    <tr id="sdp-full-{{ $loop->index }}" style="display:none;">
                                        <td colspan="9" style="background:var(--surface-alt); padding:14px 20px;">
                                            <div class="info-label" style="margin-bottom:8px;">Mitra {{ $r->segmen }} yang sudah belanja full bulan ini ({{ $belanjaFullList->count() }})</div>
                                            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(200px, 1fr)); gap:6px 16px;">
                                                @foreach ($belanjaFullList as $bf)
                                                    <div style="font-size:12px; color:var(--ink-muted);">{{ $bf['nama'] }} <span style="color:var(--ink-faint);">({{ $bf['kode_mitra'] }}, {{ $bf['pct'] }}%)</span></div>
                                                @endforeach
                                            </div>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if ($runRate)
            <section class="card table-card reveal-on-scroll" style="padding:0; margin-bottom:20px;">
                <div class="card-head" style="padding:18px 20px 0; margin-bottom:12px;">
                    <div class="card-title">Run Rate Mitra Active</div>
                    <div class="card-hint">YTD Januari&ndash;{{ $runRate['monthLabels'][$runRate['currentMonth']] }} {{ $tahunIni }} &middot; mitra dihitung unik per bulan, min. 1x belanja</div>
                </div>
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Agen</th>
                                @foreach ($runRate['months'] as $m)
                                    <th>{{ $runRate['monthLabels'][$m] }}</th>
                                @endforeach
                                <th>vs Target</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($runRate['rows'] as $r)
                                <tr>
                                    <td style="font-weight:600;">{{ $r['nama'] }}</td>
                                    @foreach ($runRate['months'] as $m)
                                        <td class="tnum" style="{{ $m === $runRate['currentMonth'] ? 'background:var(--accent-soft);' : '' }}">{{ $r['counts'][$m] }}</td>
                                    @endforeach
                                    <td class="tnum">{{ $r['vs_target'] !== null ? $r['vs_target'].'%' : '—' }}</td>
                                </tr>
                            @endforeach
                            <tr style="font-weight:700; background:var(--surface-alt);">
                                <td>Total</td>
                                @foreach ($runRate['months'] as $m)
                                    <td class="tnum" style="{{ $m === $runRate['currentMonth'] ? 'background:var(--accent-soft);' : '' }}">{{ $runRate['total']['counts'][$m] }}</td>
                                @endforeach
                                <td class="tnum">{{ $runRate['total']['vs_target'] !== null ? $runRate['total']['vs_target'].'%' : '—' }}</td>
                            </tr>
                            <tr style="font-weight:700;">
                                <td>Avg Daily</td>
                                @foreach ($runRate['months'] as $m)
                                    <td class="tnum" style="{{ $m === $runRate['currentMonth'] ? 'background:var(--accent-soft);' : '' }}">{{ $runRate['avg_daily'][$m] }}</td>
                                @endforeach
                                <td></td>
                            </tr>
                            <tr>
                                <td>% Growth</td>
                                @foreach ($runRate['months'] as $m)
                                    @php $g = $runRate['growth'][$m]; @endphp
                                    <td class="tnum" style="{{ $m === $runRate['currentMonth'] ? 'background:var(--accent-soft);' : '' }} {{ $g !== null && $g < 0 ? 'color:var(--critical);' : ($g !== null ? 'color:var(--good);' : '') }}">
                                        {{ $g !== null ? $g.'%' : '—' }}
                                    </td>
                                @endforeach
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if (auth()->user()->canViewAll() && $reactivationCandidates->isNotEmpty())
            <section class="card table-card reveal-on-scroll" style="padding:0; margin-bottom:20px;">
                <div class="card-head" style="padding:18px 20px; margin-bottom:0; cursor:pointer; align-items:center;" onclick="
                    const body = document.getElementById('reactivation-body');
                    const chevron = document.getElementById('reactivation-chevron');
                    const open = body.style.display === 'none';
                    body.style.display = open ? '' : 'none';
                    chevron.style.transform = open ? 'rotate(180deg)' : 'rotate(0deg)';
                ">
                    <div>
                        <div class="card-title">
                            Reactivation &amp; New Mitra
                            <span class="chip chip-accent" style="margin-left:6px;">New Mitra {{ $reactivationCandidates->where('is_new_mitra', true)->count() }}</span>
                            <span class="chip chip-neutral" style="margin-left:4px;">Reactivation {{ $reactivationCandidates->where('is_new_mitra', false)->count() }}</span>
                        </div>
                        <div class="card-hint">Mitra belanja bulan ini tapi tidak ada target &mdash; tandai yang mitra baru</div>
                    </div>
                    <svg id="reactivation-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:18px; height:18px; color:var(--ink-muted); transition:transform 0.15s ease; flex:none;"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </div>
                <div id="reactivation-body" style="display:none;">
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>Mitra</th><th>KAE</th><th>Omset Bulan Ini</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($reactivationCandidates as $rc)
                                <tr>
                                    <td>
                                        <div style="font-weight:600;">{{ $rc->nama }}</div>
                                        <div style="font-size:11.5px; color:var(--ink-muted);">{{ $rc->kode_mitra }}</div>
                                    </td>
                                    <td>{{ $rc->kae_code ? (\App\Models\User::kaeNameMap()[$rc->kae_code] ?? $rc->kae_code) : '—' }}</td>
                                    <td class="tnum">{{ $rp($rc->omset) }}</td>
                                    <td>
                                        @if ($rc->is_new_mitra)
                                            <span class="chip chip-accent">New Mitra</span>
                                        @else
                                            <span class="chip chip-neutral">Reactivation</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($rc->from_kategori ?? false)
                                            <span style="font-size:11px; color:var(--ink-faint);">Otomatis dari kategori</span>
                                        @else
                                            <form method="POST" action="{{ route('dashboard.toggle-new-mitra', $rc->mitra_id) }}">
                                                @csrf
                                                <input type="hidden" name="bulan" value="{{ $bulanIni }}">
                                                <input type="hidden" name="tahun" value="{{ $tahunIni }}">
                                                <button type="submit" class="btn" style="width:auto; font-size:11.5px; padding:6px 10px;">
                                                    {{ $rc->is_new_mitra ? 'Batal New Mitra' : 'Tandai New Mitra' }}
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                </div>
            </section>
        @endif

        <section style="display:grid; grid-template-columns:1fr 1fr; gap:16px; align-items:start;">
            <div class="card table-card reveal-on-scroll" style="padding:0;">
                <div class="card-head" style="padding:18px 20px; margin-bottom:0; cursor:pointer; align-items:center;" onclick="
                    const body = document.getElementById('top-mitra-body');
                    const chevron = document.getElementById('top-mitra-chevron');
                    const open = body.style.display === 'none';
                    body.style.display = open ? '' : 'none';
                    chevron.style.transform = open ? 'rotate(180deg)' : 'rotate(0deg)';
                ">
                    <div class="card-title">Top Mitra Bulan Ini <span class="chip chip-neutral" style="margin-left:6px;">{{ $topMitra->count() }}</span></div>
                    <svg id="top-mitra-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:18px; height:18px; color:var(--ink-muted); transition:transform 0.15s ease; flex:none;"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </div>
                <div id="top-mitra-body" style="display:none;">
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>Mitra</th><th>Omset</th></tr></thead>
                        <tbody>
                            @forelse ($topMitra as $m)
                                <tr>
                                    <td>
                                        <div style="font-weight:600;">{{ $m->nama }}</div>
                                        <div style="font-size:11.5px; color:var(--ink-muted);">{{ $m->kode_mitra }}</div>
                                    </td>
                                    <td class="tnum">{{ $rp($m->total) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" style="color:var(--ink-muted);">Belum ada data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                </div>
            </div>

            <div class="card table-card reveal-on-scroll" style="padding:0; transition-delay:0.1s;">
                <div class="card-head" style="padding:18px 20px; margin-bottom:0; cursor:pointer; align-items:center;" onclick="
                    const body = document.getElementById('order-terbaru-body');
                    const chevron = document.getElementById('order-terbaru-chevron');
                    const open = body.style.display === 'none';
                    body.style.display = open ? '' : 'none';
                    chevron.style.transform = open ? 'rotate(180deg)' : 'rotate(0deg)';
                ">
                    <div class="card-title">Order Terbaru <span class="chip chip-neutral" style="margin-left:6px;">{{ $orderTerbaru->count() }}</span></div>
                    <svg id="order-terbaru-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:18px; height:18px; color:var(--ink-muted); transition:transform 0.15s ease; flex:none;"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </div>
                <div id="order-terbaru-body" style="display:none;">
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>Tanggal</th><th>Mitra</th><th>Total</th></tr></thead>
                        <tbody>
                            @forelse ($orderTerbaru as $o)
                                <tr>
                                    <td class="tnum">{{ $o->tanggal_order->format('d/m/Y') }}</td>
                                    <td>{{ $o->mitra->nama ?? '—' }}</td>
                                    <td class="tnum">{{ $rp($o->total_transaksi) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" style="color:var(--ink-muted);">Belum ada data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                </div>
            </div>
        </section>
    @endif

    @include('partials.reveal-on-scroll')
    </div>

    <div id="dashtab-development" style="display:none;">
        <div class="card" style="text-align:center; padding:60px 20px; color:var(--ink-muted);">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" style="margin:0 auto 16px; opacity:0.5;"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <div style="font-size:15px; font-weight:600; color:var(--ink); margin-bottom:6px;">Segera Hadir</div>
            <div style="font-size:13px;">Dashboard Development masih dalam tahap perencanaan.</div>
        </div>
    </div>

    <script>
        function switchDashboardTab(tab) {
            ['sales', 'development'].forEach(function (t) {
                document.getElementById('dashtab-' + t).style.display = (t === tab) ? '' : 'none';
                var btn = document.getElementById('dashtab-' + t + '-btn');
                btn.style.borderBottomColor = (t === tab) ? 'var(--accent)' : 'transparent';
                btn.style.color = (t === tab) ? 'var(--ink)' : 'var(--ink-muted)';
                btn.style.fontWeight = (t === tab) ? '700' : '600';
            });
        }
    </script>
@endsection
