@extends('layouts.app')

@php
    $isEdit = isset($cpCase);
@endphp

@section('content')
    <a href="{{ route('tracking-cp.index') }}" style="display:inline-flex; align-items:center; gap:6px; font-size:12.5px; color:var(--ink-muted); text-decoration:none; margin-bottom:16px;">
        &larr; Kembali
    </a>

    <h1 class="display" style="font-size:22px; margin-bottom:20px;">{{ $isEdit ? 'Koreksi Kasus '.$cpCase->kode : 'Catat Kasus Baru' }}</h1>

    @if ($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ $isEdit ? route('tracking-cp.update', $cpCase) : route('tracking-cp.store') }}" class="card" style="max-width:760px;">
        @csrf
        @if ($isEdit) @method('PUT') @endif

        <div class="field-row">
            <div class="field" style="flex:1;">
                <label>Tanggal Temuan</label>
                <input type="date" name="tanggal_temuan" value="{{ old('tanggal_temuan', $isEdit ? $cpCase->tanggal_temuan->toDateString() : '') }}" required>
            </div>
            <div class="field" style="flex:1;">
                <label>Platform</label>
                <select name="platform" required>
                    <option value="">— pilih —</option>
                    @foreach ($platformOptions as $p)
                        <option value="{{ $p }}" {{ old('platform', $isEdit ? $cpCase->platform : '') === $p ? 'selected' : '' }}>{{ $p }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="field-row">
            <div class="field" style="flex:1;">
                <label>Mitra (kosongkan kalau gak ketemu di database)</label>
                <select name="mitra_id">
                    <option value="">— manual / gak ketemu —</option>
                    @foreach ($mitraOptions as $m)
                        <option value="{{ $m->id }}" {{ (string) old('mitra_id', $isEdit ? $cpCase->mitra_id : '') === (string) $m->id ? 'selected' : '' }}>{{ $m->nama }} ({{ $m->kode_mitra }})</option>
                    @endforeach
                </select>
            </div>
            <div class="field" style="flex:1;">
                <label>Nama Mitra Manual</label>
                <input type="text" name="nama_mitra_manual" value="{{ old('nama_mitra_manual', $isEdit ? $cpCase->nama_mitra_manual : '') }}" placeholder="Isi kalau mitra di atas gak dipilih">
            </div>
        </div>

        <div class="field-row">
            <div class="field" style="flex:1;">
                <label>Nama Toko</label>
                <input type="text" name="nama_toko" value="{{ old('nama_toko', $isEdit ? $cpCase->nama_toko : '') }}" required>
            </div>
            <div class="field" style="flex:1;">
                <label>Terjual (unit)</label>
                <input type="number" step="1" min="0" name="terjual" value="{{ old('terjual', $isEdit ? $cpCase->terjual : '') }}">
            </div>
            <div class="field" style="flex:1;">
                <label>Terlaris (unit) — menentukan Status Toko</label>
                <input type="number" step="1" min="0" name="terlaris" value="{{ old('terlaris', $isEdit ? $cpCase->terlaris : '') }}">
                @if ($isEdit && $cpCase->statusToko())
                    <div style="font-size:11.5px; color:var(--ink-muted); margin-top:4px;">Status Toko: <strong>{{ $cpCase->statusToko() }}</strong></div>
                @endif
            </div>
        </div>

        <div class="field-row">
            <div class="field" style="flex:1;">
                <label>Kota Toko</label>
                <select name="kota_kabupaten_id">
                    <option value="">— pilih —</option>
                    @foreach ($kotaOptions as $k)
                        <option value="{{ $k->id }}" {{ (string) old('kota_kabupaten_id', $isEdit ? $cpCase->kota_kabupaten_id : '') === (string) $k->id ? 'selected' : '' }}>{{ $k->nama }} ({{ $k->provinsi }})</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="field">
            <label>Link Etalase / Produk</label>
            <input type="text" name="link_etalase" value="{{ old('link_etalase', $isEdit ? $cpCase->link_etalase : '') }}" placeholder="https://...">
        </div>

        <div class="field-row">
            <div class="field" style="flex:1;">
                <label>Produk (dari Master Produk)</label>
                <select name="produk_id" id="produkSelect" onchange="isiHargaHet()" required>
                    <option value="">— pilih —</option>
                    @foreach ($produkOptions as $p)
                        <option value="{{ $p->id }}" data-het="{{ $p->harga_het }}" {{ (string) old('produk_id', $isEdit ? $cpCase->produk_id : '') === (string) $p->id ? 'selected' : '' }}>{{ $p->nama }} ({{ $p->brand }})</option>
                    @endforeach
                </select>
            </div>
            <div class="field" style="flex:1;">
                <label>Kode Barcode</label>
                <input type="text" name="kode_barcode" value="{{ old('kode_barcode', $isEdit ? $cpCase->kode_barcode : '') }}">
            </div>
        </div>

        <div class="field-row">
            <div class="field" style="flex:1;">
                <label>Harga SOP (Rp) — otomatis dari Harga HET produk, bisa diubah</label>
                <input type="number" step="1" min="0" name="harga_sop" id="hargaSopInput" value="{{ old('harga_sop', $isEdit ? $cpCase->harga_sop : '') }}" required>
            </div>
            <div class="field" style="flex:1;">
                <label>Harga Pelanggaran (Rp)</label>
                <input type="number" step="1" min="0" name="harga_pelanggaran" value="{{ old('harga_pelanggaran', $isEdit ? $cpCase->harga_pelanggaran : '') }}" required>
            </div>
        </div>

        <div class="field">
            <label>Status Kasus</label>
            <select name="status_kasus" required>
                @foreach ($statusOptions as $s)
                    <option value="{{ $s }}" {{ old('status_kasus', $isEdit ? $cpCase->status_kasus : 'Baru Ditemukan') === $s ? 'selected' : '' }}>{{ $s }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label>Bukti Temuan (link)</label>
            <input type="text" name="bukti_temuan" value="{{ old('bukti_temuan', $isEdit ? $cpCase->bukti_temuan : '') }}" placeholder="https://drive.google.com/...">
        </div>

        <button type="submit" class="btn btn-primary" style="width:auto; margin-top:8px;">{{ $isEdit ? 'Simpan Perubahan' : 'Simpan Kasus' }}</button>
    </form>

    @if ($isEdit && $cpCase->takedownBanding)
        <div class="alert-success" style="max-width:760px; margin-top:16px;">
            Kasus ini sudah masuk data Take Down &amp; Banding (status banding: {{ $cpCase->takedownBanding->status_banding ?? '—' }}).
        </div>
    @endif

    <script>
        function isiHargaHet() {
            const select = document.getElementById('produkSelect');
            const opt = select.options[select.selectedIndex];
            const het = opt ? opt.getAttribute('data-het') : null;
            if (het && het !== '' && het !== 'null') {
                document.getElementById('hargaSopInput').value = Math.round(parseFloat(het));
            }
        }
    </script>
@endsection
