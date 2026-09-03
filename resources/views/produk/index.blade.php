@extends('layouts.app')

@php
    $rp = fn ($v) => $v === null ? '—' : 'Rp'.number_format((float) $v, 0, ',', '.');
    $clean = fn ($v) => $v === null ? '' : (int) $v;
    $poinFmt = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.');
@endphp

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:22px; flex-wrap:wrap;">
        <div>
            <h1 class="display" style="font-size:24px;">Master Produk</h1>
            <div style="color:var(--ink-muted); font-size:13px; margin-top:4px;">{{ $produkList->count() }} produk</div>
        </div>
        <button type="button" class="btn btn-primary" style="width:auto;" onclick="const d = document.getElementById('tambah-produk'); d.style.display = d.style.display === 'none' ? '' : 'none';">+ Tambah Produk</button>
    </div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    <section id="tambah-produk" class="card" style="margin-bottom:20px; display:none;">
        <div class="info-label" style="margin-bottom:10px;">Tambah Produk Baru</div>
        <form method="POST" action="{{ route('produk.store') }}" class="field-row" style="align-items:flex-end;">
            @csrf
            <div class="field" style="margin-bottom:0;">
                <label>Kode SKU</label>
                <input type="text" name="kode_sku" placeholder="mis. PUR-BL-185">
            </div>
            <div class="field" style="margin-bottom:0; flex:1; min-width:180px;">
                <label>Nama Produk</label>
                <input type="text" name="nama" required placeholder="mis. Purela Baby Lotion 185gr">
            </div>
            <div class="field" style="margin-bottom:0;">
                <label>Brand</label>
                <select name="brand" required>
                    <option value="Reglow">Reglow</option>
                    <option value="Amura">Amura</option>
                    <option value="Purela">Purela</option>
                    <option value="B.U.T">B.U.T</option>
                </select>
            </div>
            <div class="field" style="margin-bottom:0;">
                <label>Kategori</label>
                <input type="text" name="kategori" placeholder="opsional">
            </div>
            <div class="field" style="margin-bottom:0;">
                <label>Harga Jual (Rp)</label>
                <input type="number" name="harga" min="0" placeholder="mis. 54000">
            </div>
            <div class="field" style="margin-bottom:0;">
                <label>Qty per Poin</label>
                <input type="number" name="qty_per_poin" min="0.0001" step="0.0001" placeholder="mis. 3 atau 0.5">
            </div>
            <div class="field" style="margin-bottom:0;">
                <label>Status</label>
                <select name="status">
                    <option value="aktif">Aktif</option>
                    <option value="nonaktif">Nonaktif</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="width:auto;">Simpan</button>
        </form>
    </section>

    <section class="card table-card" style="padding:0;">
        <form method="GET" action="{{ route('produk.index') }}" class="field-row" style="padding:18px 20px 16px; margin-bottom:0; align-items:flex-end;">
            <div class="field" style="margin-bottom:0; flex:1; min-width:180px;">
                <label>Cari</label>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Nama atau kode SKU...">
            </div>
            <div class="field" style="margin-bottom:0;">
                <label>Brand</label>
                <select name="brand" class="select-pill" onchange="this.form.submit()">
                    <option value="">Semua Brand</option>
                    @foreach ($brands as $b)
                        <option value="{{ $b }}" {{ request('brand') === $b ? 'selected' : '' }}>{{ $b }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn" style="width:auto;">Cari</button>
        </form>

        <div class="table-scroll">
            <table>
                <thead>
                    <tr><th>Kode SKU</th><th>Nama</th><th>Brand</th><th>Kategori</th><th>Harga Jual</th><th>Qty per Poin</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($produkList as $p)
                        <tr>
                            <td class="tnum">{{ $p->kode_sku ?? '—' }}</td>
                            <td>{{ $p->nama }}</td>
                            <td>{{ $p->brand }}</td>
                            <td>{{ $p->kategori ?? '—' }}</td>
                            <td class="tnum">{{ $rp($p->harga) }}</td>
                            <td class="tnum">{{ $poinFmt($p->qty_per_poin) ?: '—' }}</td>
                            <td>
                                @if ($p->status === 'aktif')
                                    <span class="chip chip-good">Aktif</span>
                                @else
                                    <span class="chip chip-neutral">Nonaktif</span>
                                @endif
                            </td>
                            <td>
                                <div style="display:flex; gap:6px;">
                                    <button type="button" class="btn" style="width:auto; font-size:11.5px; padding:5px 10px;" onclick="const d = document.getElementById('edit-produk-{{ $p->id }}'); d.style.display = d.style.display === 'none' ? '' : 'none';">Edit</button>
                                    <form method="POST" action="{{ route('produk.destroy', $p) }}" onsubmit="return confirm('Hapus produk {{ $p->nama }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger" style="width:auto; font-size:11.5px; padding:5px 10px;">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <tr id="edit-produk-{{ $p->id }}" style="display:none;">
                            <td colspan="8" style="background:var(--surface-alt);">
                                <form method="POST" action="{{ route('produk.update', $p) }}" class="field-row" style="align-items:flex-end; padding:10px 4px;">
                                    @csrf
                                    @method('PUT')
                                    <div class="field" style="margin-bottom:0;">
                                        <label>Kode SKU</label>
                                        <input type="text" name="kode_sku" value="{{ $p->kode_sku }}">
                                    </div>
                                    <div class="field" style="margin-bottom:0; flex:1; min-width:180px;">
                                        <label>Nama Produk</label>
                                        <input type="text" name="nama" value="{{ $p->nama }}" required>
                                    </div>
                                    <div class="field" style="margin-bottom:0;">
                                        <label>Brand</label>
                                        <select name="brand" required>
                                            @foreach (['Reglow', 'Amura', 'Purela', 'B.U.T'] as $b)
                                                <option value="{{ $b }}" {{ $p->brand === $b ? 'selected' : '' }}>{{ $b }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="field" style="margin-bottom:0;">
                                        <label>Kategori</label>
                                        <input type="text" name="kategori" value="{{ $p->kategori }}">
                                    </div>
                                    <div class="field" style="margin-bottom:0;">
                                        <label>Harga Jual (Rp)</label>
                                        <input type="number" name="harga" min="0" value="{{ $clean($p->harga) }}">
                                    </div>
                                    <div class="field" style="margin-bottom:0;">
                                        <label>Qty per Poin</label>
                                        <input type="number" name="qty_per_poin" min="0.0001" step="0.0001" value="{{ $poinFmt($p->qty_per_poin) }}">
                                    </div>
                                    <div class="field" style="margin-bottom:0;">
                                        <label>Status</label>
                                        <select name="status">
                                            <option value="aktif" {{ $p->status === 'aktif' ? 'selected' : '' }}>Aktif</option>
                                            <option value="nonaktif" {{ $p->status === 'nonaktif' ? 'selected' : '' }}>Nonaktif</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary" style="width:auto;">Simpan</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" style="color:var(--ink-muted);">Belum ada produk.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
