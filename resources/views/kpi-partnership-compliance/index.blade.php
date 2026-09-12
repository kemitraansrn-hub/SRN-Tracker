@extends('layouts.app')

@php
    $bulanNama = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'][$bulan];
@endphp

@section('content')
    <div class="topbar" style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:22px; flex-wrap:wrap;">
        <div>
            <h1 class="display" style="font-size:24px;">KPI Partnership Compliance</h1>
            <div style="color:var(--ink-muted); font-size:13px; margin-top:4px;">
                Scorecard bulanan tim Compliance &mdash; {{ $bulanNama }} {{ $tahun }} ({{ $totalKasus }} kasus tercatat)
            </div>
        </div>
        @include('partials.bulan-tahun-filter', ['action' => route('kpi-partnership-compliance.index'), 'bulan' => $bulan, 'tahun' => $tahun, 'isBulanIni' => $isBulanIni, 'resetAction' => route('kpi-partnership-compliance.index')])
    </div>

    @if ($totalKasus === 0)
        <div class="alert-error" style="background:var(--surface-alt); color:var(--ink-muted);">
            Belum ada kasus Tracking CP yang tercatat di {{ $bulanNama }} {{ $tahun }} — Realisasi belum bisa dihitung.
        </div>
    @endif

    <section class="card table-card" style="padding:0;">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>No</th><th>KPI</th><th>Bobot</th><th>Target</th><th>Realisasi</th><th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($kpis as $kpi)
                        <tr>
                            <td class="tnum">{{ $kpi['no'] }}</td>
                            <td style="font-weight:600;">{{ $kpi['nama'] }}</td>
                            <td class="tnum">{{ $kpi['bobot'] }}%</td>
                            <td class="tnum">{{ $kpi['target_label'] }}</td>
                            <td class="tnum">
                                @if ($kpi['realisasi'] === null)
                                    <span style="color:var(--ink-faint);">—</span>
                                @else
                                    <span class="chip {{ $kpi['tercapai'] ? 'chip-good' : 'chip-critical' }}">{{ $kpi['realisasi'] }}%</span>
                                @endif
                            </td>
                            <td style="white-space:normal; max-width:420px; color:var(--ink-muted); font-size:12.5px;">{{ $kpi['keterangan'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <div style="margin:28px 0 14px;">
        <h2 style="font-size:16px; font-weight:700; margin:0;">Contoh Perhitungan</h2>
        <div style="color:var(--ink-muted); font-size:12.5px; margin-top:4px;">
            Angka nyata dari data {{ $bulanNama }} {{ $tahun }}, biar tim ngerti asal Realisasi di atas dihitung dari mana.
        </div>
    </div>

    <div style="display:flex; flex-direction:column; gap:12px;">
        @foreach ($kpis as $kpi)
            <div class="card">
                <div style="display:flex; align-items:baseline; gap:8px; margin-bottom:6px;">
                    <span style="font-weight:700; font-size:13.5px;">{{ $kpi['no'] }}. {{ $kpi['nama'] }}</span>
                    <span style="font-size:11px; color:var(--ink-muted);">(bobot {{ $kpi['bobot'] }}%, target {{ $kpi['target_label'] }})</span>
                </div>
                <div style="font-size:12.5px; color:var(--ink-muted); margin-bottom:10px;">Rumus: {{ $kpi['rumus'] }}</div>
                <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; font-size:13.5px;">
                    <span class="tnum" style="font-weight:700;">{{ $kpi['numerator'] }}</span>
                    <span style="color:var(--ink-muted); font-size:12.5px;">{{ $kpi['numerator_label'] }}</span>
                    <span style="color:var(--ink-faint);">&divide;</span>
                    <span class="tnum" style="font-weight:700;">{{ $kpi['denominator'] }}</span>
                    <span style="color:var(--ink-muted); font-size:12.5px;">{{ $kpi['denominator_label'] }}</span>
                    <span style="color:var(--ink-faint);">=</span>
                    @if ($kpi['realisasi'] === null)
                        <span style="color:var(--ink-faint); font-size:12.5px;">— (belum ada data buat dibagi)</span>
                    @else
                        <span class="chip {{ $kpi['tercapai'] ? 'chip-good' : 'chip-critical' }}">{{ $kpi['realisasi'] }}%</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endsection
