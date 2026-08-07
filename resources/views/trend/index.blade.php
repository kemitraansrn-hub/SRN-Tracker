@extends('layouts.app')

@php
    $rp = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');
    $fmt = fn ($d) => \Carbon\Carbon::parse($d)->translatedFormat('d M Y');
@endphp

@section('content')
    <h1 class="display" style="font-size:24px;">Trend Mitra</h1>
    <div style="color:var(--ink-muted); font-size:13px; margin-top:4px; margin-bottom:24px;">
        Bandingkan omset antar 2 rentang tanggal bebas &mdash; kamu tentukan sendiri kedua periodenya.
    </div>

    <form method="GET" action="{{ route('trend.index') }}" class="card" style="margin-bottom:20px;">
        <div class="field-row">
            <div class="field" style="flex:1;">
                <label>Periode Ini &mdash; Mulai</label>
                <input type="date" name="ini_mulai" value="{{ $iniMulai }}">
            </div>
            <div class="field" style="flex:1;">
                <label>Periode Ini &mdash; Selesai</label>
                <input type="date" name="ini_selesai" value="{{ $iniSelesai }}">
            </div>
        </div>
        <div class="field-row">
            <div class="field" style="flex:1;">
                <label>Periode Pembanding &mdash; Mulai</label>
                <input type="date" name="lalu_mulai" value="{{ $laluMulai }}">
            </div>
            <div class="field" style="flex:1;">
                <label>Periode Pembanding &mdash; Selesai</label>
                <input type="date" name="lalu_selesai" value="{{ $laluSelesai }}">
            </div>
        </div>
        <button type="submit" class="btn btn-primary" style="width:auto;">Bandingkan</button>
    </form>

    @if ($error)
        <div class="alert-error">{{ $error }}</div>
    @elseif ($result)
        @php
            $growth = $result['growth'];
            $growthClass = $growth === null ? '' : ($growth >= 0 ? 'growth-pos' : 'growth-neg');
        @endphp
        <section class="card table-card">
            <div class="card-head">
                <div class="card-title">Perbandingan Omset</div>
            </div>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr><th>Basis Perbandingan</th><th>Periode Pembanding</th><th>Periode Ini</th><th>Growth</th></tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="mitra-name" style="font-weight:600;">
                                {{ $fmt($iniMulai) }} &ndash; {{ $fmt($iniSelesai) }}<br>
                                <span style="font-size:11px; color:var(--ink-muted); font-weight:400;">vs {{ $fmt($laluMulai) }} &ndash; {{ $fmt($laluSelesai) }}</span>
                            </td>
                            <td class="tnum">{{ $rp($result['omset_lalu']) }}</td>
                            <td class="tnum" style="font-weight:700;">{{ $rp($result['omset_ini']) }}</td>
                            <td class="tnum {{ $growthClass }}" style="font-weight:700;">
                                {{ $growth === null ? '—' : ($growth >= 0 ? '+' : '').$growth.'%' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            @if ($growth === null)
                <p style="padding:16px 20px 20px; margin:0; font-size:12px; color:var(--ink-muted); font-style:italic;">
                    Periode pembanding tidak punya data omset, jadi growth belum bisa dihitung.
                </p>
            @endif
        </section>
    @endif
@endsection
