@extends('layouts.app')

@section('content')
    <h1 class="display" style="font-size:24px;">Input NPD</h1>
    <div style="color:var(--ink-muted); font-size:13px; margin-top:4px; margin-bottom:24px;">
        Tandai produk baru (NPD) di sini. Status NPD otomatis berlaku {{ \App\Models\NpdProduct::MASA_BERLAKU_HARI }} hari sejak ditandai, dipakai untuk metrik NPD Adoption di Segmentasi Mitra.
    </div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif

    <form method="GET" action="{{ route('npd.index') }}" class="field-row" style="align-items:flex-end; margin-bottom:16px;">
        <div class="field" style="margin-bottom:0; flex:1; min-width:200px;">
            <label>Cari</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Nama produk...">
        </div>
        <div class="field" style="margin-bottom:0;">
            <label>Brand</label>
            <select name="brand" class="select-pill" onchange="this.form.submit()">
                <option value="">Semua Brand</option>
                @foreach ($brandOptions as $b)
                    <option value="{{ $b }}" {{ request('brand') === $b ? 'selected' : '' }}>{{ $b }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn" style="width:auto;">Cari</button>
    </form>

    <section class="card table-card" style="padding:0;">
        <div class="table-scroll">
            <table>
                <thead><tr><th>Brand</th><th>Nama Produk</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse ($produkList as $p)
                        @php $npd = $npdByProdukId[$p->id] ?? null; $aktif = $npd && $npd->isAktif(); @endphp
                        <tr>
                            <td>{{ $p->brand }}</td>
                            <td>{{ $p->nama }}</td>
                            <td>
                                @if ($aktif)
                                    <span class="chip chip-accent">NPD aktif &middot; sejak {{ $npd->tanggal_ditandai->format('d/m/Y') }}</span>
                                @elseif ($npd)
                                    <span class="chip chip-neutral">NPD kedaluwarsa &middot; {{ $npd->tanggal_ditandai->format('d/m/Y') }}</span>
                                @else
                                    <span style="color:var(--ink-faint); font-size:12px;">—</span>
                                @endif
                            </td>
                            <td>
                                <form method="POST" action="{{ route('npd.toggle', $p) }}">
                                    @csrf
                                    <input type="hidden" name="q" value="{{ request('q') }}">
                                    <input type="hidden" name="brand" value="{{ request('brand') }}">
                                    <button type="submit" class="btn" style="width:auto; font-size:11.5px; padding:6px 10px;">
                                        {{ $npd ? 'Cabut NPD' : 'Tandai NPD' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" style="color:var(--ink-muted);">Belum ada produk.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div style="margin-top:16px;">{{ $produkList->links() }}</div>
@endsection
