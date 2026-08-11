@extends('layouts.app')

@php
    $rp = fn ($v) => $v !== null ? 'Rp'.number_format((float) $v, 0, ',', '.') : '—';
@endphp

@section('content')
    <h1 class="display" style="font-size:24px;">Tier Target per Mitra</h1>
    <div style="color:var(--ink-muted); font-size:13px; margin-top:4px; margin-bottom:24px;">
        {{ $periodeLabel }} &middot; pilih Komit / Target / Stretch mana yang jadi acuan pencapaian tiap mitra &mdash; dipakai di Dashboard, Weekly Plan, dan Segmentasi.
    </div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif

    <form method="GET" action="{{ route('tier-target.index') }}" class="field-row" style="align-items:flex-end; margin-bottom:16px;">
        <div class="field" style="margin-bottom:0;">
            <label>Bulan</label>
            <select class="select-pill" name="bulan" onchange="this.form.submit()">
                @foreach (['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'] as $i => $nama)
                    <option value="{{ $i + 1 }}" {{ $bulan == $i + 1 ? 'selected' : '' }}>{{ $nama }}</option>
                @endforeach
            </select>
        </div>
        <div class="field" style="margin-bottom:0;">
            <label>Tahun</label>
            <select class="select-pill" name="tahun" onchange="this.form.submit()">
                @for ($y = now()->year - 1; $y <= now()->year + 1; $y++)
                    <option value="{{ $y }}" {{ $tahun == $y ? 'selected' : '' }}>{{ $y }}</option>
                @endfor
            </select>
        </div>
    </form>

    <section class="card table-card" style="padding:0;">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Mitra</th><th>Segmen</th><th>Komit</th><th>Target</th><th>Stretch</th>
                        <th>Tier Dipakai</th><th>Target Efektif</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                        <tr>
                            <td>
                                <a href="{{ route('mitra.show', $r->mitra) }}" style="color:var(--ink); text-decoration:none; font-weight:600;">{{ $r->mitra->nama ?? '—' }}</a>
                                <div style="font-size:11.5px; color:var(--ink-muted);">{{ $r->mitra->kode_mitra ?? '—' }}</div>
                            </td>
                            <td>{{ $r->segmen }}</td>
                            <td class="tnum">{{ $rp($r->komit) }}</td>
                            <td class="tnum">{{ $rp($r->target) }}</td>
                            <td class="tnum">{{ $rp($r->stretch) }}</td>
                            <td>
                                <form method="POST" action="{{ route('tier-target.update', $r) }}">
                                    @csrf
                                    <select name="tier_dipakai" class="select-pill" onchange="this.form.submit()">
                                        <option value="komit" {{ $r->tier_dipakai === 'komit' ? 'selected' : '' }}>Komit</option>
                                        <option value="target" {{ $r->tier_dipakai === 'target' ? 'selected' : '' }}>Target</option>
                                        <option value="stretch" {{ $r->tier_dipakai === 'stretch' ? 'selected' : '' }}>Stretch</option>
                                    </select>
                                </form>
                            </td>
                            <td class="tnum" style="font-weight:700;">{{ $rp($r->effectiveTarget()) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" style="color:var(--ink-muted);">Belum ada Target Bulanan untuk periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
