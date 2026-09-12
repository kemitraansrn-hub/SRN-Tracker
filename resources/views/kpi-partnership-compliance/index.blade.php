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
@endsection
