@extends('layouts.app')

@php
    $bulanLabel = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
@endphp

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:22px; flex-wrap:wrap;">
        <div>
            <h1 class="display" style="font-size:24px;">Poin Mitra</h1>
            <div style="color:var(--ink-muted); font-size:13px; margin-top:4px;">
                Poin dihitung otomatis dari qty produk terjual &mdash; reset tiap ganti tahun.
            </div>
        </div>
        <form method="GET" action="{{ route('poin.index') }}" class="field-row" style="margin-bottom:0; align-items:flex-end;">
            <div class="field" style="margin-bottom:0;">
                <label>Tahun</label>
                <select name="tahun" class="select-pill" onchange="this.form.submit()">
                    @for ($y = now()->year; $y >= now()->year - 4; $y--)
                        <option value="{{ $y }}" {{ $tahun == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
        </form>
    </div>

    <div style="display:flex; gap:16px; flex-wrap:wrap; margin-bottom:20px;">
        <section class="card" style="display:inline-block;">
            <div class="info-label" style="margin-bottom:10px;">Total Poin Semua Mitra ({{ $tahun }})</div>
            <div style="font-size:25px; font-weight:700;" class="tnum">{{ number_format($grandTotal, 0, ',', '.') }}</div>
        </section>
        <section class="card" style="display:inline-block;">
            <div class="info-label" style="margin-bottom:10px;">Budget ({{ $tahun }})</div>
            <div style="font-size:25px; font-weight:700;" class="tnum">Rp{{ number_format($budgetTotal, 0, ',', '.') }}</div>
            <div style="font-size:11.5px; color:var(--ink-muted); margin-top:4px;">{{ number_format($grandTotal, 0, ',', '.') }} poin &times; Rp{{ number_format($rupiahPerPoin, 0, ',', '.') }}</div>
        </section>
    </div>

    <section class="card table-card" style="padding:0;">
        <div class="card-head" style="padding:18px 20px 0; margin-bottom:12px;">
            <div class="card-title">Rincian Poin per Bulan</div>
            <div class="card-hint">{{ $rows->count() }} mitra</div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Mitra</th>
                        @foreach ($bulanLabel as $b)
                            <th class="tnum">{{ $b }}</th>
                        @endforeach
                        <th class="tnum">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                        <tr>
                            <td>
                                <div style="font-weight:600;">{{ $r->mitra->nama }}</div>
                                <div style="font-size:11.5px; color:var(--ink-muted);">{{ $r->mitra->kode_mitra }}</div>
                            </td>
                            @for ($m = 1; $m <= 12; $m++)
                                <td class="tnum" style="{{ $r->monthly[$m] > 0 ? '' : 'color:var(--ink-faint);' }}">{{ $r->monthly[$m] }}</td>
                            @endfor
                            <td class="tnum" style="font-weight:700;">{{ $r->total }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="14" style="color:var(--ink-muted);">Belum ada data poin untuk {{ $tahun }}.</td></tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot>
                        <tr>
                            <td style="text-align:right; font-weight:700;">Total:</td>
                            @for ($m = 1; $m <= 12; $m++)
                                <td class="tnum" style="font-weight:700;">{{ $monthTotals[$m] }}</td>
                            @endfor
                            <td class="tnum" style="font-weight:700;">{{ $grandTotal }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </section>
@endsection
