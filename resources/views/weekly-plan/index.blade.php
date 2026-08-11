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

    <section class="card table-card" style="padding:0;">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Mitra</th><th>KAE</th><th>Minggu Andalan</th>
                        @foreach ($weeks as $w)
                            <th>{{ $w }}{{ $w === $currentWeekLabel ? ' (skrg)' : '' }} Target</th>
                            <th>{{ $w }}{{ $w === $currentWeekLabel ? ' (skrg)' : '' }} Realisasi</th>
                        @endforeach
                        <th>Target Bulan</th><th>Realisasi Bulan</th><th>% Bulan</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($plan as $p)
                        <tr>
                            <td>
                                <a href="{{ route('mitra.show', $p->mitra) }}" style="color:var(--ink); text-decoration:none; font-weight:600;">{{ $p->mitra->nama }}</a>
                                <div style="font-size:11.5px; color:var(--ink-muted);">{{ $p->mitra->kode_mitra }}</div>
                            </td>
                            <td>
                                @if ($p->mitra->kae_code)
                                    <span style="display:inline-flex; align-items:center; justify-content:center; width:20px; height:20px; border-radius:6px; background:var(--accent-soft); color:var(--accent-ink); font-size:10.5px; font-weight:700;">{{ $p->mitra->kae_code }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @if ($p->minggu_andalan)
                                    <span class="chip" style="background:var(--accent-soft); color:var(--accent-ink);">{{ $p->minggu_andalan }}</span>
                                @else
                                    <span style="color:var(--ink-faint); font-size:12px;">Belum ada histori</span>
                                @endif
                            </td>
                            @foreach ($weeks as $w)
                                @php $highlight = $w === $p->minggu_andalan ? 'background:var(--accent-soft);' : ''; @endphp
                                <td class="tnum" style="{{ $highlight }}">{{ $p->target_per_week[$w] > 0 ? $rp($p->target_per_week[$w]) : '—' }}</td>
                                <td class="tnum" style="{{ $highlight }} font-weight:700;">{{ $p->actual[$w] > 0 ? $rp($p->actual[$w]) : '—' }}</td>
                            @endforeach
                            <td class="tnum">{{ $p->target_bulan > 0 ? $rp($p->target_bulan) : '—' }}</td>
                            <td class="tnum" style="font-weight:700;">{{ $rp($p->realisasi_bulan) }}</td>
                            <td class="tnum">
                                @if ($p->pct_bulan === null)
                                    —
                                @else
                                    @php $pctColor = $p->pct_bulan >= 100 ? 'good' : ($p->pct_bulan >= 70 ? 'warn' : 'critical'); @endphp
                                    <span class="chip chip-{{ $pctColor }}">{{ $p->pct_bulan }}%</span>
                                @endif
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
                        <tr><td colspan="15" style="color:var(--ink-muted);">Belum ada mitra.</td></tr>
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
