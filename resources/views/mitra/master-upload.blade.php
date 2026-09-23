@extends('layouts.app')

@section('content')
    <a href="{{ route('mitra.index') }}" style="display:inline-flex; align-items:center; gap:6px; font-size:12.5px; color:var(--ink-muted); text-decoration:none; margin-bottom:16px;">
        &larr; Kembali
    </a>

    <h1 class="display" style="font-size:22px; margin-bottom:6px;">Upload Master Mitra</h1>
    <div style="color:var(--ink-muted); font-size:13px; margin-bottom:20px;">
        Upload data master reseller (Reseller ID, Name, Phone, alamat, dst). Mitra yang Reseller ID-nya sudah terdaftar
        akan dilengkapi/diperbarui No. WA &amp; alamatnya (menimpa data lama kalau sudah ada isinya). Mitra yang Reseller
        ID-nya belum ada di sistem otomatis dibuat sebagai mitra baru (status Aktif, KAE belum ditentukan — atur manual
        belakangan di menu edit mitra). Segmen tidak diatur dari sini — segmen mitra diambil otomatis dari menu Special Deal.
    </div>

    @if ($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    <div class="card" style="max-width:640px;">
        <form method="POST" action="{{ route('mitra.master-upload.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="field">
                <label>File Master Mitra (.xlsx)</label>
                <input type="file" name="file" accept=".xlsx,.xls,.csv" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:auto;">Upload</button>
        </form>
    </div>

    <div class="card" style="max-width:640px; margin-top:16px; font-size:12.5px; color:var(--ink-muted);">
        <div style="font-weight:600; color:var(--ink); margin-bottom:8px;">Kolom yang dibaca</div>
        <div>Reseller ID (wajib, ini yang dicocokkan ke Kode Mitra), Name (wajib buat mitra baru), Phone (diisi ke No. WA), Alamat Pengiriman, Provinsi, Kota/Kab, Kecamatan, Desa, Kodepos, Kae (nama KAE-nya — dicocokkan ke nama user KAE di sistem; kosong = KAE mitra gak diubah, gak ketemu = dilewati dengan catatan). Kolom Id Kae di file diabaikan.</div>
    </div>
@endsection
