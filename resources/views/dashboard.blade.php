@extends('layouts.app')

@section('content')
    <h1 class="display" style="font-size:24px;">Dashboard</h1>
    <div style="color:var(--ink-muted); font-size:13px; margin-top:4px; margin-bottom:24px;">
        Login berhasil sebagai <b style="color:var(--ink);">{{ auth()->user()->name }}</b>
        ({{ auth()->user()->role === 'admin' ? 'Admin' : 'KAE — kode ' . auth()->user()->kae_code }}).
    </div>

    <div class="card" style="max-width:520px;">
        Halaman ini masih placeholder — isi dashboard sungguhan (KPI, tren, tabel mitra) akan dibangun berikutnya,
        menyambungkan ke data <code>orders</code> dan <code>target_bulanan</code> yang sebenarnya.
    </div>
@endsection
