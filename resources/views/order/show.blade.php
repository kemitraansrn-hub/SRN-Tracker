@extends('layouts.app')

@php
    $rp = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');
@endphp

@section('content')
    <a href="{{ route('order.index') }}" style="display:inline-flex; align-items:center; gap:6px; font-size:12.5px; color:var(--ink-muted); text-decoration:none; margin-bottom:16px;">
        &larr; Kembali
    </a>

    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:18px; flex-wrap:wrap;">
        <div>
            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                <h1 class="display" style="font-size:22px;">Order {{ $order->no_order }}</h1>
                @if ($order->is_edited)
                    <span class="chip chip-warn">Dikoreksi Manual</span>
                @endif
            </div>
            <div style="color:var(--ink-muted); font-size:13px; margin-top:6px;">
                {{ $order->tanggal_order->format('d/m/Y') }} &middot;
                <a href="{{ route('mitra.show', $order->mitra) }}" style="color:var(--accent-ink); text-decoration:none;">{{ $order->mitra->nama ?? '—' }}</a>
                ({{ $order->mitra->kode_mitra ?? '—' }})
            </div>
        </div>
        @if (auth()->user()->hasAdminAccess())
            <a href="{{ route('order.edit', $order) }}" class="btn" style="width:auto;">Koreksi Order</a>
        @endif
    </div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif

    <section style="display:grid; grid-template-columns:repeat(3, 1fr); gap:16px; margin-bottom:20px;">
        <div class="card">
            <div class="info-label" style="margin-bottom:10px;">Total Transaksi</div>
            <div style="font-size:22px; font-weight:700;" class="tnum">{{ $rp($order->total_transaksi) }}</div>
        </div>
        <div class="card">
            <div class="info-label" style="margin-bottom:10px;">Status Pembayaran</div>
            <div style="margin-top:4px;">
                @if ($order->status_pembayaran === 'Lunas')
                    <span class="chip chip-good">Lunas</span>
                @elseif ($order->status_pembayaran)
                    <span class="chip chip-warn">{{ $order->status_pembayaran }}</span>
                @else
                    <span style="color:var(--ink-faint);">—</span>
                @endif
            </div>
        </div>
        <div class="card">
            <div class="info-label" style="margin-bottom:10px;">Status</div>
            <div style="font-size:14px; font-weight:600; margin-top:6px;">{{ $order->status ?: '—' }}</div>
        </div>
    </section>

    <section class="card table-card" style="padding:0;">
        <div class="card-head" style="padding:18px 20px 0; margin-bottom:12px;">
            <div class="card-title">Detail Produk</div>
            <div class="card-hint">{{ $order->items->count() }} item</div>
        </div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>Brand</th><th>Produk</th><th>Qty</th><th>Subtotal</th></tr></thead>
                <tbody>
                    @forelse ($order->items as $item)
                        <tr>
                            <td>{{ $item->brand ?: '—' }}</td>
                            <td>{{ $item->nama_produk_raw }}</td>
                            <td class="tnum">{{ $item->qty }}</td>
                            <td class="tnum">{{ $rp($item->subtotal) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" style="color:var(--ink-muted);">Tidak ada detail item.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($order->is_edited)
        <div class="card" style="margin-top:16px; font-size:12px; color:var(--ink-muted);">
            Terakhir dikoreksi oleh <b style="color:var(--ink);">{{ $order->editor->name ?? '—' }}</b> pada {{ $order->edited_at?->format('d/m/Y H:i') }}.
        </div>
    @endif
@endsection
