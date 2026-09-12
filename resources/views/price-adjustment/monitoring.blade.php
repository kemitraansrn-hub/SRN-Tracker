@extends('layouts.app')

@php
    $statusChip = fn ($s) => match ($s) {
        'Approved' => 'chip-good',
        'Rejected' => 'chip-critical',
        default => 'chip-warn',
    };
@endphp

@section('content')
    <div style="margin-bottom:22px;">
        <h1 class="display" style="font-size:24px;">Price Adjustment Monitoring</h1>
        <div style="color:var(--ink-muted); font-size:13px; margin-top:4px;">
            Pantauan semua pengajuan izin penyesuaian harga — pengajuan & keputusan tetap dilakukan di menu "Price Adjustment" (Sales).
        </div>
    </div>

    <section style="display:grid; grid-template-columns:repeat(3, 1fr); gap:16px; margin-bottom:20px;">
        <div class="card">
            <div class="info-label" style="margin-bottom:8px;">Pending</div>
            <div style="font-size:25px; font-weight:700;" class="tnum">{{ $pendingCount }}</div>
        </div>
        <div class="card">
            <div class="info-label" style="margin-bottom:8px;">Approved</div>
            <div style="font-size:25px; font-weight:700; color:var(--good);" class="tnum">{{ $approvedCount }}</div>
        </div>
        <div class="card">
            <div class="info-label" style="margin-bottom:8px;">Rejected</div>
            <div style="font-size:25px; font-weight:700; color:var(--critical);" class="tnum">{{ $rejectedCount }}</div>
        </div>
    </section>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif

    <form method="GET" action="{{ route('price-adjustment-monitoring.index') }}" class="field-row" style="align-items:flex-end; margin-bottom:16px;">
        <div class="field" style="margin-bottom:0; flex:1; min-width:180px;">
            <label>Cari</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Nama mitra, nama toko...">
        </div>
        <div class="field" style="margin-bottom:0;">
            <label>Status</label>
            <select class="select-pill" name="status_approval" onchange="this.form.submit()">
                <option value="">Semua</option>
                @foreach ($statusOptions as $s)
                    <option value="{{ $s }}" {{ request('status_approval') === $s ? 'selected' : '' }}>{{ $s }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn" style="width:auto;">Cari</button>
        @if (request('q') || request('status_approval'))
            <a href="{{ route('price-adjustment-monitoring.index') }}" class="btn" style="width:auto;">Reset</a>
        @endif
    </form>

    <section class="card table-card" style="padding:0;">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Mitra</th><th>Toko / Marketplace</th><th>Periode</th>
                        <th>Diajukan Oleh</th><th>Status</th><th>Diputuskan Oleh</th><th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requests as $r)
                        <tr>
                            <td>{{ $r->mitra->nama ?? '—' }}</td>
                            <td>
                                <div style="font-weight:600;">{{ $r->toko }}</div>
                                <div style="font-size:11px; color:var(--ink-muted);">{{ $r->marketplace }}</div>
                            </td>
                            <td class="tnum">{{ $r->tanggal_mulai->format('d/m/Y') }} &ndash; {{ $r->tanggal_selesai->format('d/m/Y') }}</td>
                            <td>{{ $r->pengaju->name ?? '—' }}</td>
                            <td><span class="chip {{ $statusChip($r->status_approval) }}">{{ $r->status_approval }}</span></td>
                            <td>{{ $r->penyetuju->name ?? '—' }}</td>
                            <td style="max-width:220px; overflow:hidden; text-overflow:ellipsis;" title="{{ $r->catatan }}">{{ $r->catatan ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" style="color:var(--ink-muted);">Belum ada pengajuan tercatat.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div style="margin-top:16px;">{{ $requests->links() }}</div>
@endsection
