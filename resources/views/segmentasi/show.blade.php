@extends('layouts.app')

@php
    $rp = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');
@endphp

@section('content')
    <h1 class="display" style="font-size:24px;">Segmentasi Mitra &mdash; {{ $segmen }}</h1>
    <div style="color:var(--ink-muted); font-size:13px; margin-top:4px; margin-bottom:24px;">
        {{ $periodeLabel }} &middot; berdasarkan Target Bulanan yang sudah di-import
    </div>

    <section style="display:grid; grid-template-columns:repeat(2, 1fr); gap:16px; margin-bottom:20px;">
        <div class="card">
            <div class="info-label" style="margin-bottom:10px;">Jumlah Mitra</div>
            <div style="font-size:24px; font-weight:700;" class="tnum">{{ $mitraList->count() }}</div>
        </div>
        <div class="card">
            <div class="info-label" style="margin-bottom:10px;">Total Omset Bulan Ini</div>
            <div style="font-size:24px; font-weight:700;" class="tnum">{{ $rp($totalOmset) }}</div>
        </div>
    </section>

    <section class="card table-card" style="padding:0;">
        <div class="card-head" style="padding:18px 20px 0; margin-bottom:12px;">
            <div class="card-title">Daftar Mitra &mdash; {{ $segmen }}</div>
            <div class="card-hint">{{ $mitraList->count() }} mitra</div>
        </div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>Mitra</th><th>KAE</th><th>Omset Bulan Ini</th><th>Target</th><th>% vs Target</th><th></th></tr></thead>
                <tbody>
                    @forelse ($mitraList as $m)
                        <tr>
                            <td>
                                <div style="font-weight:600;">{{ $m->nama }}</div>
                                <div style="font-size:11.5px; color:var(--ink-muted);">{{ $m->kode_mitra }}</div>
                            </td>
                            <td>
                                @if ($m->kae_code)
                                    <span style="display:inline-flex; align-items:center; justify-content:center; width:20px; height:20px; border-radius:6px; background:var(--accent-soft); color:var(--accent-ink); font-size:10.5px; font-weight:700;">{{ $m->kae_code }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="tnum">{{ $rp($m->omset) }}</td>
                            <td class="tnum">{{ $rp($m->target) }}</td>
                            <td class="tnum">
                                @php $color = $m->pct >= 80 ? 'good' : ($m->pct >= 60 ? 'warn' : 'critical'); @endphp
                                <span class="chip chip-{{ $color }}">{{ $m->pct }}%</span>
                            </td>
                            <td><a href="{{ route('mitra.show', $m->id) }}" class="link-action" style="color:var(--accent-ink); font-size:12.5px; font-weight:600; text-decoration:none; border:1px solid var(--line); border-radius:7px; padding:5px 10px;">Lihat detail</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" style="color:var(--ink-muted);">Belum ada mitra di segmen ini untuk {{ $periodeLabel }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
