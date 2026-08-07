@extends('layouts.app')

@php
    $rp = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');
@endphp

@section('content')
    <a href="{{ route('mitra.index') }}" style="display:inline-flex; align-items:center; gap:6px; font-size:12.5px; color:var(--ink-muted); text-decoration:none; margin-bottom:16px;">
        &larr; Kembali
    </a>

    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:18px; flex-wrap:wrap;">
        <div>
            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                <h1 class="display" style="font-size:22px;">{{ $mitra->nama }}</h1>
                @if ($mitra->status === 'aktif')
                    <span class="chip chip-good">Aktif</span>
                @else
                    <span class="chip chip-critical">Nonaktif</span>
                @endif
            </div>
            <div style="color:var(--ink-muted); font-size:13px; margin-top:6px;">
                {{ $mitra->kode_mitra }}
                @if ($mitra->kae_code)
                    &middot; KAE <span class="kae-tag" style="display:inline-flex; align-items:center; justify-content:center; width:20px; height:20px; border-radius:6px; background:var(--accent-soft); color:var(--accent-ink); font-size:10.5px; font-weight:700; vertical-align:middle;">{{ $mitra->kae_code }}</span>
                @endif
            </div>
        </div>
        @if (auth()->user()->isAdmin())
            <a href="{{ route('mitra.edit', $mitra) }}" class="btn" style="width:auto;">Edit Mitra</a>
        @endif
    </div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif

    <section style="display:grid; grid-template-columns:repeat(2, 1fr); gap:16px; margin-bottom:20px;">
        <div class="card">
            <div class="info-label" style="margin-bottom:10px;">Omset Bulan Ini ({{ $periodeLabel }})</div>
            <div style="font-size:24px; font-weight:700;" class="tnum">{{ $rp($omsetBulanIni) }}</div>
        </div>
        <div class="card">
            <div class="info-label" style="margin-bottom:10px;">Total Order Bulan Ini</div>
            <div style="font-size:24px; font-weight:700;" class="tnum">{{ $jumlahOrderBulanIni }}</div>
        </div>
    </section>

    <section class="card" style="margin-bottom:20px;">
        <div class="info-grid">
            <div>
                <div class="info-label">Alamat</div>
                <div class="info-value">{{ $mitra->alamat ?: '—' }}</div>
            </div>
            <div>
                <div class="info-label">No. HP / WA</div>
                <div class="info-value tnum">{{ $mitra->no_hp ?: '—' }}</div>
            </div>
            <div>
                <div class="info-label">Provinsi / Kota</div>
                <div class="info-value">{{ collect([$mitra->provinsi, $mitra->kota])->filter()->implode(', ') ?: '—' }}</div>
            </div>
            <div>
                <div class="info-label">Kecamatan / Desa</div>
                <div class="info-value">{{ collect([$mitra->kecamatan, $mitra->desa])->filter()->implode(', ') ?: '—' }}</div>
            </div>
        </div>
    </section>

    <section class="card table-card" style="padding:0;">
        <div class="card-head" style="padding:18px 20px 0; margin-bottom:12px;">
            <div class="card-title">Histori Order</div>
            <div class="card-hint">{{ $historiOrder->count() }} order terakhir</div>
        </div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>Tanggal</th><th>No Order</th><th>Total</th><th>Pembayaran</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($historiOrder as $o)
                        <tr>
                            <td class="tnum">{{ $o->tanggal_order->format('d/m/Y') }}</td>
                            <td class="tnum">{{ $o->no_order }}</td>
                            <td class="tnum">{{ $rp($o->total_transaksi) }}</td>
                            <td>
                                @if ($o->status_pembayaran === 'Lunas')
                                    <span class="chip chip-good">Lunas</span>
                                @elseif ($o->status_pembayaran)
                                    <span class="chip chip-warn">{{ $o->status_pembayaran }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $o->status ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" style="color:var(--ink-muted);">Belum ada order.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="card" style="margin-top:16px; color:var(--ink-muted); font-size:12.5px;">
        Target Mingguan &amp; Follow-up Log belum tersedia untuk mitra ini &mdash; menyusul setelah fitur Target Bulanan &amp; Follow-up dibangun.
    </div>
@endsection
