@extends('layouts.app')

@php
    $isEdit = isset($cpCase);
    $mitraOptionsFmt = $mitraOptions->map(fn ($m) => (object) ['id' => $m->id, 'label' => $m->nama.' ('.$m->kode_mitra.')']);
    $kotaOptionsFmt = $kotaOptions->map(fn ($k) => (object) ['id' => $k->id, 'label' => $k->nama.' ('.$k->provinsi.')']);
    $produkOptionsFmt = $produkOptions->map(fn ($p) => (object) ['id' => $p->id, 'label' => \Illuminate\Support\Str::limit($p->nama, 45).' ('.$p->brand.')', 'harga_het' => $p->harga_het]);
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

        <div class="field">
            <label>List Mitra SRN</label>
            @include('partials.searchable-select', [
                'name' => 'mitra_id',
                'options' => $mitraOptionsFmt,
                'selectedId' => old('mitra_id', $isEdit ? $cpCase->mitra_id : ''),
                'placeholder' => '— Mitra Belum Diketahui — ketik buat cari —',
            ])
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
                @include('partials.searchable-select', [
                    'name' => 'kota_kabupaten_id',
                    'options' => $kotaOptionsFmt,
                    'selectedId' => old('kota_kabupaten_id', $isEdit ? $cpCase->kota_kabupaten_id : ''),
                    'placeholder' => 'Ketik buat cari kota...',
                ])
            </div>
        </div>

        <div class="field">
            <label>Link Etalase / Produk</label>
            <input type="text" name="link_etalase" id="linkEtalaseInput" value="{{ old('link_etalase', $isEdit ? $cpCase->link_etalase : '') }}" placeholder="https://..." onblur="cekLinkEtalase(this.value)">
            <div id="linkEtalaseWarning" style="display:none; margin-top:8px; padding:10px 13px; border-radius:8px; background:var(--warn-soft); color:var(--warn); font-size:12.5px; line-height:1.6;"></div>
        </div>

        <div class="field-row">
            <div class="field" style="flex:1;">
                <label>Produk</label>
                @include('partials.searchable-select', [
                    'name' => 'produk_id',
                    'options' => $produkOptionsFmt,
                    'selectedId' => old('produk_id', $isEdit ? $cpCase->produk_id : ''),
                    'placeholder' => 'Ketik buat cari dari Master Produk...',
                    'extraAttr' => 'harga_het',
                    'onchangeJs' => 'isiHargaHet',
                ])
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
            <div style="padding:10px 13px; border:1px solid var(--line); border-radius:10px; background:var(--surface-alt); color:var(--ink-muted); font-size:14px;">
                {{ $isEdit ? $cpCase->status_kasus : 'Baru Ditemukan' }}
            </div>
            <input type="hidden" name="status_kasus" value="{{ $isEdit ? $cpCase->status_kasus : 'Baru Ditemukan' }}">
            @unless ($isEdit)
                <div style="font-size:11px; color:var(--ink-muted); margin-top:4px;">Status berubah otomatis lewat tombol di daftar Tracking CP, bukan di sini.</div>
            @endunless
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
        function isiHargaHet(produkId, het) {
            if (het && het !== '' && het !== 'null') {
                document.getElementById('hargaSopInput').value = Math.round(parseFloat(het));
            }
        }

        function cekLinkEtalase(link) {
            const box = document.getElementById('linkEtalaseWarning');
            link = (link || '').trim();
            if (! link) {
                box.style.display = 'none';
                return;
            }
            fetch('{{ route("tracking-cp.check-link-etalase") }}?link=' + encodeURIComponent(link), {
                headers: { 'Accept': 'application/json' },
            })
                .then(res => res.json())
                .then(data => {
                    if (! data.found) {
                        box.style.display = 'none';
                        return;
                    }
                    const rows = data.matches.map(m =>
                        '&bull; ' + m.mitra + ' (' + m.toko + ') &mdash; status: <strong>' + m.status + '</strong>'
                        + (m.tanggal_mulai ? ', periode ' + m.tanggal_mulai + ' s/d ' + m.tanggal_selesai : '')
                    ).join('<br>');
                    box.innerHTML = '&#9888; Link ini sudah ada di pengajuan Price Adjustment &mdash; kemungkinan bukan pelanggaran cutting price, tapi program titipan/diskon resmi yang sudah/sedang diajukan izinnya:<br>' + rows;
                    box.style.display = '';
                })
                .catch(() => { box.style.display = 'none'; });
        }

        document.addEventListener('DOMContentLoaded', function () {
            const initial = document.getElementById('linkEtalaseInput').value;
            if (initial) cekLinkEtalase(initial);
        });
    </script>
@endsection
