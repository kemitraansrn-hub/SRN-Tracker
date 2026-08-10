@extends('layouts.app')

@section('content')
    <a href="{{ route('order.show', $order) }}" style="display:inline-flex; align-items:center; gap:6px; font-size:12.5px; color:var(--ink-muted); text-decoration:none; margin-bottom:16px;">
        &larr; Kembali
    </a>

    <h1 class="display" style="font-size:22px; margin-bottom:6px;">Koreksi Order {{ $order->no_order }}</h1>
    <div style="color:var(--ink-muted); font-size:13px; margin-bottom:20px;">
        Data ini berasal dari import. Koreksi manual akan tercatat di riwayat aktivitas (data lama tidak hilang).
    </div>

    @if ($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('order.update', $order) }}" class="card" style="max-width:560px;">
        @csrf
        @method('PUT')

        <div class="field">
            <label>Total Transaksi (Rp)</label>
            <input type="number" step="1" min="0" name="total_transaksi" value="{{ old('total_transaksi', $order->total_transaksi) }}" required>
        </div>

        <div class="field-row">
            <div class="field" style="flex:1;">
                <label>Status Pembayaran</label>
                <input type="text" name="status_pembayaran" value="{{ old('status_pembayaran', $order->status_pembayaran) }}" placeholder="mis. Lunas / Tempo">
            </div>
            <div class="field" style="flex:1;">
                <label>Status</label>
                <input type="text" name="status" value="{{ old('status', $order->status) }}" placeholder="mis. Konfirmasi">
            </div>
        </div>

        <div class="field">
            <label>Alasan Koreksi</label>
            <textarea name="alasan_edit" rows="3" required style="font-family:inherit; font-size:14px; padding:10px 12px; border:1px solid var(--line); border-radius:8px; background:var(--surface-alt); color:var(--ink); resize:vertical;" placeholder="mis. Salah input total saat import, sudah dicek ulang dengan mitra">{{ old('alasan_edit') }}</textarea>
        </div>

        <button type="submit" class="btn btn-primary" style="width:auto; margin-top:8px;">Simpan Koreksi</button>
    </form>
@endsection
