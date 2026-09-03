@extends('layouts.app')

@php
    $rp = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');
@endphp

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:22px; flex-wrap:wrap;">
        <div>
            <h1 class="display" style="font-size:24px;">Weekly Plan</h1>
            <div style="color:var(--ink-muted); font-size:13px; margin-top:4px;">
                {{ $periodeLabel }} &middot; "Minggu Andalan" dihitung dari minggu dengan omset terbesar 6 bulan terakhir
            </div>
        </div>
        @if (auth()->user()->isAdmin())
            <a href="{{ route('pengaturan.minggu') }}" class="btn" style="width:auto;">Atur Periode Mingguan</a>
        @endif
    </div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif

    <section style="display:grid; grid-template-columns:repeat({{ count($weeks) }}, 1fr); gap:16px; margin-bottom:20px;">
        @foreach ($weeks as $w)
            @php $wt = $weekTotals[$w]; @endphp
            <div class="card">
                <div class="info-label" style="margin-bottom:10px;">{{ $w }}{{ $w === $currentWeekLabel ? ' (berjalan)' : '' }}</div>
                <div style="font-size:20px; font-weight:700;" class="tnum">{{ $rp($wt['realisasi']) }}</div>
                <div style="font-size:11.5px; color:var(--ink-muted); margin-top:2px;">dari target {{ $rp($wt['target']) }}</div>
                <div style="height:6px; border-radius:4px; background:var(--line); overflow:hidden; margin-top:10px;">
                    <div style="height:100%; border-radius:4px; background:var(--accent); width:{{ $wt['pct'] !== null ? min($wt['pct'], 100) : 0 }}%;"></div>
                </div>
                <div style="margin-top:8px;">
                    @if ($wt['pct'] === null)
                        <span style="font-size:11.5px; color:var(--ink-faint);">Belum ada target</span>
                    @else
                        @php $wColor = $wt['pct'] >= 100 ? 'good' : ($wt['pct'] >= 70 ? 'warn' : 'critical'); @endphp
                        <span class="chip chip-{{ $wColor }}">{{ $wt['pct'] }}%</span>
                    @endif
                </div>
            </div>
        @endforeach
    </section>

    <form method="GET" action="{{ route('weekly-plan.index') }}" class="field-row" style="align-items:flex-end; margin-bottom:16px;">
        <div class="field" style="margin-bottom:0;">
            <label>Cari Mitra</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Nama mitra...">
        </div>
        <div class="field" style="margin-bottom:0;">
            <label>Segmentasi</label>
            <select class="select-pill" name="segmen" onchange="this.form.submit()">
                <option value="">Semua</option>
                @foreach ($segmenOptions as $seg)
                    <option value="{{ $seg }}" {{ request('segmen') === $seg ? 'selected' : '' }}>{{ $seg }}</option>
                @endforeach
            </select>
        </div>
        <div class="field" style="margin-bottom:0;">
            <label>Minggu Andalan</label>
            <select class="select-pill" name="minggu_andalan" onchange="this.form.submit()">
                <option value="">Semua</option>
                @foreach ($weeks as $w)
                    <option value="{{ $w }}" {{ request('minggu_andalan') === $w ? 'selected' : '' }}>{{ $w }}</option>
                @endforeach
                <option value="none" {{ request('minggu_andalan') === 'none' ? 'selected' : '' }}>Belum ada histori</option>
            </select>
        </div>
        <div class="field" style="margin-bottom:0;">
            <label>Status Pencapaian</label>
            <select class="select-pill" name="status_pencapaian" onchange="this.form.submit()">
                <option value="">Semua</option>
                <option value="belum-belanja" {{ request('status_pencapaian') === 'belum-belanja' ? 'selected' : '' }}>Belum Belanja</option>
                <option value="kurang" {{ request('status_pencapaian') === 'kurang' ? 'selected' : '' }}>Kurang Belanja</option>
                <option value="mendekati" {{ request('status_pencapaian') === 'mendekati' ? 'selected' : '' }}>Mendekati</option>
                <option value="tercapai" {{ request('status_pencapaian') === 'tercapai' ? 'selected' : '' }}>Tercapai</option>
                <option value="over-ro" {{ request('status_pencapaian') === 'over-ro' ? 'selected' : '' }}>Over RO</option>
            </select>
        </div>
        <div class="field" style="margin-bottom:0;">
            <label>Status Minggu</label>
            <select class="select-pill" name="status_minggu" onchange="this.form.submit()">
                <option value="">Semua</option>
                <option value="oke" {{ request('status_minggu') === 'oke' ? 'selected' : '' }}>OKE</option>
                <option value="berjalan" {{ request('status_minggu') === 'berjalan' ? 'selected' : '' }}>Berjalan</option>
                <option value="terlewat" {{ request('status_minggu') === 'terlewat' ? 'selected' : '' }}>Terlewat</option>
                <option value="menunggu" {{ request('status_minggu') === 'menunggu' ? 'selected' : '' }}>Menunggu</option>
                <option value="belum-ada-data" {{ request('status_minggu') === 'belum-ada-data' ? 'selected' : '' }}>Belum ada data</option>
            </select>
        </div>
        <button type="submit" class="btn" style="width:auto;">Cari</button>
        @if (request('q') || request('segmen') || request('minggu_andalan') || request('status_pencapaian') || request('status_minggu'))
            <a href="{{ route('weekly-plan.index') }}" class="btn" style="width:auto;">Reset</a>
        @endif
        <div class="card" style="width:auto; padding:9px 14px; margin-bottom:0;">
            <span style="font-size:11.5px; color:var(--ink-muted);">Jumlah Mitra</span>
            <span class="tnum" style="font-weight:700; margin-left:6px;">{{ $plan->count() }}</span>
        </div>
    </form>

    <section class="card table-card" style="padding:0;">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Mitra</th>
                        <th>Tier Dipakai</th>
                        <th>Kategori</th>
                        <th>Keterangan</th>
                        <th>Target Bulan</th>
                        <th>KAE</th><th>Minggu Andalan</th>
                        @foreach ($weeks as $w)
                            <th>{{ $w }}{{ $w === $currentWeekLabel ? ' (skrg)' : '' }} Target</th>
                            <th>{{ $w }}{{ $w === $currentWeekLabel ? ' (skrg)' : '' }} Realisasi</th>
                        @endforeach
                        <th>Realisasi Bulan</th><th>% Bulan</th>
                        <th>Status Pencapaian</th>
                        <th>Status Minggu</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($plan as $p)
                        <tr>
                            <td style="white-space:nowrap;">
                                <a href="{{ route('mitra.show', $p->mitra) }}" style="color:var(--ink); text-decoration:none; font-weight:600;">{{ $p->mitra->nama }}</a>
                            </td>
                            <td>
                                @if ($p->target_row && auth()->user()->isAdmin())
                                    <form method="POST" action="{{ route('tier-target.update', $p->target_row) }}">
                                        @csrf
                                        <select name="tier_dipakai" class="select-pill" onchange="this.form.submit()">
                                            <option value="komit" {{ $p->target_row->tier_dipakai === 'komit' ? 'selected' : '' }}>Komit</option>
                                            <option value="target" {{ $p->target_row->tier_dipakai === 'target' ? 'selected' : '' }}>Target</option>
                                            <option value="stretch" {{ $p->target_row->tier_dipakai === 'stretch' ? 'selected' : '' }}>Stretch</option>
                                        </select>
                                    </form>
                                @elseif ($p->target_row)
                                    <span style="font-size:12px; color:var(--ink-muted);">{{ ucfirst($p->target_row->tier_dipakai ?? 'target') }}</span>
                                @else
                                    <span style="color:var(--ink-faint); font-size:12px;">—</span>
                                @endif
                            </td>
                            <td>
                                @if ($p->target_row?->kategori)
                                    <span class="chip">{{ $p->target_row->kategori }}</span>
                                @else
                                    <span style="color:var(--ink-faint); font-size:12px;">—</span>
                                @endif
                            </td>
                            <td style="max-width:220px; font-size:12px; color:var(--ink-muted); white-space:normal;">
                                {{ $p->target_row?->keterangan ?: '—' }}
                            </td>
                            <td class="tnum">{{ $p->target_bulan > 0 ? $rp($p->target_bulan) : '—' }}</td>
                            <td>
                                @if ($p->mitra->kae_code)
                                    <span style="display:inline-block; padding:2px 8px; border-radius:6px; background:var(--accent-soft); color:var(--accent-ink); font-size:11px; font-weight:600;">{{ \App\Models\User::kaeNameMap()[$p->mitra->kae_code] ?? $p->mitra->kae_code }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @if (! empty($p->minggu_andalan))
                                    <span class="chip" style="background:var(--accent-soft); color:var(--accent-ink);">{{ implode(', ', $p->minggu_andalan) }}</span>
                                @else
                                    <span style="color:var(--ink-faint); font-size:12px;">Belum ada histori</span>
                                @endif
                            </td>
                            @foreach ($weeks as $w)
                                @php
                                    $targetVal = $p->target_per_week[$w];
                                    $actualVal = $p->actual[$w];
                                    $realisasiBg = $targetVal > 0 ? ($actualVal >= $targetVal ? 'var(--surface-alt)' : 'var(--critical-soft)') : '';
                                @endphp
                                <td class="tnum">{{ $targetVal > 0 ? $rp($targetVal) : '—' }}</td>
                                <td class="tnum" style="{{ $realisasiBg ? 'background:'.$realisasiBg.';' : '' }} font-weight:700;">{{ $actualVal > 0 ? $rp($actualVal) : '—' }}</td>
                            @endforeach
                            @php $realisasiBulanBg = $p->target_bulan > 0 ? ($p->realisasi_bulan >= $p->target_bulan ? 'var(--surface-alt)' : 'var(--critical-soft)') : ''; @endphp
                            <td class="tnum" style="{{ $realisasiBulanBg ? 'background:'.$realisasiBulanBg.';' : '' }} font-weight:700;">{{ $rp($p->realisasi_bulan) }}</td>
                            <td class="tnum">
                                @if ($p->pct_bulan === null)
                                    —
                                @else
                                    <span class="chip chip-{{ \App\Services\AchievementStatus::color($p->status_pencapaian) }}">{{ $p->pct_bulan }}%</span>
                                @endif
                            </td>
                            <td>
                                <span class="chip chip-{{ \App\Services\AchievementStatus::color($p->status_pencapaian) }}">{{ \App\Services\AchievementStatus::label($p->status_pencapaian) }}</span>
                            </td>
                            <td>
                                @switch($p->status)
                                    @case('oke')
                                        <span class="chip chip-good">OKE</span>
                                        @break
                                    @case('berjalan')
                                        <span class="chip chip-warn">Berjalan</span>
                                        @break
                                    @case('terlewat')
                                        <span class="chip chip-critical">Terlewat</span>
                                        @break
                                    @case('menunggu')
                                        <span class="chip" style="background:var(--surface-alt); color:var(--ink-muted);">Menunggu</span>
                                        @break
                                    @default
                                        <span style="color:var(--ink-faint); font-size:12px;">—</span>
                                @endswitch
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="17" style="color:var(--ink-muted);">Belum ada mitra.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="card" style="margin-top:16px; font-size:12px; color:var(--ink-muted);">
        <b style="color:var(--ink);">OKE</b> = sudah belanja di minggu andalannya &middot;
        <b style="color:var(--ink);">Berjalan</b> = minggu andalannya sedang berjalan &middot;
        <b style="color:var(--ink);">Terlewat</b> = minggu andalannya sudah lewat tapi belum belanja &middot;
        <b style="color:var(--ink);">Menunggu</b> = minggu andalannya belum tiba.
    </div>
@endsection
