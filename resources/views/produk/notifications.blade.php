@extends('layouts.app')

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:22px; flex-wrap:wrap;">
        <div>
            <h1 class="display" style="font-size:24px;">Notifikasi Produk Baru</h1>
            <div style="color:var(--ink-muted); font-size:13px; margin-top:4px;">
                SKU/nama yang gak ketemu di Master Produk pas import order harian, jadi otomatis dibuat &mdash; cek dulu (typo/duplikat) sebelum ditandai selesai.
            </div>
        </div>
        @if ($pending->isNotEmpty())
            <form method="POST" action="{{ route('produk.mark-all-reviewed') }}">
                @csrf
                <button type="submit" class="btn" style="width:auto;">Tandai Semua Sudah Dibaca</button>
            </form>
        @endif
    </div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif

    <section class="card table-card" style="padding:0; margin-bottom:20px;">
        <div class="card-head" style="padding:18px 20px 0; margin-bottom:12px;">
            <div class="card-title">Belum Dibaca</div>
            <div class="card-hint">{{ $pending->count() }} produk</div>
        </div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>Dibuat</th><th>Nama Produk</th><th>Brand</th><th>Kode SKU</th><th></th></tr></thead>
                <tbody>
                    @forelse ($pending as $p)
                        <tr>
                            <td class="tnum">{{ $p->created_at->format('d/m/Y H:i') }}</td>
                            <td style="font-weight:600;">{{ $p->nama }}</td>
                            <td>{{ $p->brand }}</td>
                            <td class="tnum">{{ $p->kode_sku ?? '—' }}</td>
                            <td>
                                <div style="display:flex; gap:6px;">
                                    <a href="{{ route('produk.index', ['q' => $p->kode_sku ?: $p->nama]) }}" class="btn" style="width:auto; font-size:11.5px; padding:5px 10px; text-decoration:none;">Buka di Master Produk</a>
                                    <form method="POST" action="{{ route('produk.mark-reviewed', $p) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-primary" style="width:auto; font-size:11.5px; padding:5px 10px;">Sudah Dibaca</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" style="color:var(--ink-muted);">Gak ada produk baru yang perlu di-review. Aman.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($recentlyReviewed->isNotEmpty())
        <section class="card table-card" style="padding:0;">
            <div class="card-head" style="padding:18px 20px 0; margin-bottom:12px;">
                <div class="card-title">Baru Saja Dibaca</div>
                <div class="card-hint">20 terakhir</div>
            </div>
            <div class="table-scroll">
                <table>
                    <thead><tr><th>Dibuat</th><th>Nama Produk</th><th>Brand</th><th>Kode SKU</th><th>Dibaca</th></tr></thead>
                    <tbody>
                        @foreach ($recentlyReviewed as $p)
                            <tr>
                                <td class="tnum" style="color:var(--ink-muted);">{{ $p->created_at->format('d/m/Y H:i') }}</td>
                                <td>{{ $p->nama }}</td>
                                <td>{{ $p->brand }}</td>
                                <td class="tnum">{{ $p->kode_sku ?? '—' }}</td>
                                <td class="tnum" style="color:var(--ink-muted);">{{ $p->reviewed_at->format('d/m/Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
