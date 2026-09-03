@extends('layouts.app')

@php
    $rp = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');
@endphp

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:22px; flex-wrap:wrap;">
        <div>
            <h1 class="display" style="font-size:24px;">Input Penjualan</h1>
            <div style="color:var(--ink-muted); font-size:13px; margin-top:4px;">
                Kalkulator total order &mdash; bukan data penjualan resmi.
            </div>
        </div>
        <a href="{{ route('sales-draft.create') }}" class="btn btn-primary" style="width:auto;">+ Input Penjualan Baru</a>
    </div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif

    <section class="card table-card" style="padding:0;">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr><th>Tanggal Order</th><th>Mitra</th><th>Jumlah SKU</th><th>Diskon Promo</th><th>Diskon Ongkir</th><th>Ongkir</th><th>Grand Total</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($drafts as $d)
                        <tr>
                            <td class="tnum">{{ $d->tanggal_order->format('d/m/Y') }}</td>
                            <td>
                                <div style="font-weight:600;">{{ $d->mitra->nama ?? '—' }}</div>
                                <div style="font-size:11.5px; color:var(--ink-muted);">{{ $d->mitra->kode_mitra ?? '' }}</div>
                            </td>
                            <td class="tnum">{{ $d->items->count() }}</td>
                            <td class="tnum">{{ $rp($d->diskon_promo) }}</td>
                            <td class="tnum">{{ $rp($d->diskon_ongkir) }}</td>
                            <td class="tnum">{{ $rp($d->ongkir) }}</td>
                            <td class="tnum" style="font-weight:700;">{{ $rp($d->grand_total) }}</td>
                            <td>
                                <div style="display:flex; gap:6px;">
                                    <a href="{{ route('sales-draft.edit', $d) }}" class="btn" style="width:auto; font-size:11.5px; padding:5px 10px;">Edit</a>
                                    <form method="POST" action="{{ route('sales-draft.destroy', $d) }}" onsubmit="return confirm('Hapus input penjualan untuk {{ $d->mitra->nama ?? 'mitra ini' }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger" style="width:auto; font-size:11.5px; padding:5px 10px;">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" style="color:var(--ink-muted);">Belum ada input penjualan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
