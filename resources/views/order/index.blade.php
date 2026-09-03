@extends('layouts.app')

@php
    $rp = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');
@endphp

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:22px; flex-wrap:wrap;">
        <div>
            <h1 class="display" style="font-size:24px;">Order / Transaksi</h1>
            <div style="color:var(--ink-muted); font-size:13px; margin-top:4px;">
                {{ $orders->total() }} order {{ auth()->user()->isAdmin() ? '' : 'kamu' }}
            </div>
        </div>
    </div>

    <form method="GET" action="{{ route('order.index') }}" class="field-row" style="align-items:flex-end;">
        <div class="field" style="margin-bottom:0; flex:1; min-width:200px;">
            <label>Cari</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="No order, nama/kode mitra...">
        </div>
        <div class="field" style="margin-bottom:0;">
            <label>Dari Tanggal</label>
            <input type="date" name="dari" value="{{ request('dari') }}">
        </div>
        <div class="field" style="margin-bottom:0;">
            <label>Sampai Tanggal</label>
            <input type="date" name="sampai" value="{{ request('sampai') }}">
        </div>
        <div class="field" style="margin-bottom:0;">
            <label>Pembayaran</label>
            <select name="status_pembayaran" class="select-pill">
                <option value="">Semua</option>
                <option value="Lunas" {{ request('status_pembayaran') === 'Lunas' ? 'selected' : '' }}>Lunas</option>
                <option value="Tempo" {{ request('status_pembayaran') === 'Tempo' ? 'selected' : '' }}>Tempo</option>
            </select>
        </div>
        <button type="submit" class="btn" style="width:auto;">Cari</button>
    </form>

    <section class="card table-card" style="padding:0; margin-top:16px;">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th><th>No Order</th><th>Mitra</th><th>KAE</th>
                        <th>Total</th><th>Pembayaran</th><th>Status</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $o)
                        <tr>
                            <td class="tnum">{{ $o->tanggal_order->format('d/m/Y') }}</td>
                            <td class="tnum">
                                {{ $o->no_order }}
                                @if ($o->is_edited)
                                    <span title="Sudah dikoreksi manual" style="color:var(--warn); font-size:11px;">&#9998;</span>
                                @endif
                            </td>
                            <td>
                                <div style="font-weight:600;">{{ $o->mitra->nama ?? '—' }}</div>
                                <div style="font-size:11.5px; color:var(--ink-muted);">{{ $o->mitra->kode_mitra ?? '—' }}</div>
                            </td>
                            <td>
                                @if ($o->kae_code)
                                    <span style="display:inline-block; padding:2px 8px; border-radius:6px; background:var(--accent-soft); color:var(--accent-ink); font-size:11px; font-weight:600;">{{ \App\Models\User::kaeNameMap()[$o->kae_code] ?? $o->kae_code }}</span>
                                @else
                                    —
                                @endif
                            </td>
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
                            <td><a href="{{ route('order.show', $o) }}" class="link-action" style="color:var(--accent-ink); font-size:12.5px; font-weight:600; text-decoration:none; border:1px solid var(--line); border-radius:7px; padding:5px 10px;">Detail</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" style="color:var(--ink-muted);">Belum ada order.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div style="margin-top:16px;">{{ $orders->links() }}</div>
@endsection
