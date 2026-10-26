@extends('layouts.app')

@section('content')
    @php $tab = request('tab', 'npd'); @endphp

    <h1 class="display" style="font-size:24px;">Input NPD</h1>
    <div style="color:var(--ink-muted); font-size:13px; margin-top:4px; margin-bottom:20px;">
        Tandai produk baru (NPD) atau produk yang mau dipantau tren penjualannya (Trend SKU) di sini.
    </div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif

    <div class="field-row" style="margin-bottom:16px; gap:8px;">
        <a href="{{ route('npd.index', array_merge(request()->only(['q', 'brand']), ['tab' => 'npd'])) }}"
           class="btn {{ $tab === 'npd' ? 'btn-primary' : '' }}" style="width:auto;">Input NPD</a>
        <a href="{{ route('npd.index', array_merge(request()->only(['q', 'brand']), ['tab' => 'trend-sku'])) }}"
           class="btn {{ $tab === 'trend-sku' ? 'btn-primary' : '' }}" style="width:auto;">Input Trend SKU</a>
    </div>

    <form method="GET" action="{{ route('npd.index') }}" class="field-row" style="align-items:flex-end; margin-bottom:16px;">
        <input type="hidden" name="tab" value="{{ $tab }}">
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

    @if ($tab === 'trend-sku')
        <div style="color:var(--ink-muted); font-size:12px; margin-bottom:12px;">
            Produk yang ditandai di sini akan muncul sebagai pilihan di menu Sales &rarr; Cek Trend SKU.
        </div>

        <section class="card table-card" style="padding:0;">
            <div class="table-scroll" style="max-height:none; overflow-y:visible;">
                <table>
                    <thead><tr><th>Brand</th><th>Nama Produk</th><th>Mulai Campaign B2C</th><th style="width:220px;"></th></tr></thead>
                    <tbody>
                        @forelse ($produkList as $p)
                            @php $trendSku = $trendSkuByProdukId[$p->id] ?? null; @endphp
                            <tr>
                                <td>{{ $p->brand }}</td>
                                <td>{{ $p->nama }}</td>
                                <td>
                                    @if ($trendSku)
                                        <span class="chip chip-accent">{{ $trendSku->tanggal_mulai_campaign->format('d/m/Y') }}</span>
                                    @else
                                        <span style="color:var(--ink-faint); font-size:12px;">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($trendSku)
                                        <div style="display:flex; gap:6px;">
                                            <button type="button" class="btn" style="width:auto; font-size:11.5px; padding:6px 10px;"
                                                    onclick="document.getElementById('edit-trend-sku-{{ $trendSku->id }}').style.display='flex'">Ubah</button>
                                            <form method="POST" action="{{ route('trend-sku.destroy', $trendSku) }}"
                                                  onsubmit="return confirm('Cabut status Trend SKU untuk {{ addslashes($p->nama) }}?');">
                                                @csrf @method('DELETE')
                                                <input type="hidden" name="q" value="{{ request('q') }}">
                                                <input type="hidden" name="brand" value="{{ request('brand') }}">
                                                <button type="submit" class="btn" style="width:auto; font-size:11.5px; padding:6px 10px;">Hapus</button>
                                            </form>
                                        </div>

                                        <div class="modal-overlay" id="edit-trend-sku-{{ $trendSku->id }}" style="display:none;">
                                            <div class="modal-box">
                                                <div class="modal-title">Ubah Tanggal Mulai Campaign</div>
                                                <div class="modal-body">{{ $p->nama }}</div>
                                                <form method="POST" action="{{ route('trend-sku.update', $trendSku) }}">
                                                    @csrf @method('PUT')
                                                    <input type="hidden" name="q" value="{{ request('q') }}">
                                                    <input type="hidden" name="brand" value="{{ request('brand') }}">
                                                    <div class="field">
                                                        <label>Tanggal Mulai Campaign B2C</label>
                                                        <input type="date" name="tanggal_mulai_campaign" value="{{ $trendSku->tanggal_mulai_campaign->toDateString() }}" required>
                                                    </div>
                                                    <div class="modal-actions">
                                                        <button type="button" class="btn" style="width:auto;" onclick="document.getElementById('edit-trend-sku-{{ $trendSku->id }}').style.display='none'">Batal</button>
                                                        <button type="submit" class="btn btn-primary" style="width:auto;">Simpan</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    @else
                                        <form method="POST" action="{{ route('trend-sku.store', $p) }}" style="display:flex; gap:6px; align-items:center;">
                                            @csrf
                                            <input type="hidden" name="q" value="{{ request('q') }}">
                                            <input type="hidden" name="brand" value="{{ request('brand') }}">
                                            <input type="date" name="tanggal_mulai_campaign" required style="padding:6px 8px; font-size:11.5px;">
                                            <button type="submit" class="btn" style="width:auto; font-size:11.5px; padding:6px 10px; white-space:nowrap;">Tandai</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" style="color:var(--ink-muted);">Belum ada produk.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @else
        <section class="card table-card" style="padding:0;">
            <div class="table-scroll" style="max-height:none; overflow-y:visible;">
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
    @endif

    <div style="margin-top:16px;">{{ $produkList->links() }}</div>
@endsection
