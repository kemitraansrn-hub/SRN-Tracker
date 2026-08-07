@extends('layouts.app')

@section('content')
    <a href="{{ $mitra->exists ? route('mitra.show', $mitra) : route('mitra.index') }}" style="display:inline-flex; align-items:center; gap:6px; font-size:12.5px; color:var(--ink-muted); text-decoration:none; margin-bottom:16px;">
        &larr; Kembali
    </a>

    <h1 class="display" style="font-size:22px; margin-bottom:20px;">{{ $mitra->exists ? 'Edit Mitra' : 'Tambah Mitra Baru' }}</h1>

    @if ($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ $mitra->exists ? route('mitra.update', $mitra) : route('mitra.store') }}" class="card" style="max-width:640px;">
        @csrf
        @if ($mitra->exists) @method('PUT') @endif

        <div class="field-row">
            <div class="field" style="flex:1;">
                <label>Kode Mitra</label>
                <input type="text" name="kode_mitra" value="{{ old('kode_mitra', $mitra->kode_mitra) }}" required>
            </div>
            <div class="field" style="flex:1;">
                <label>Nama</label>
                <input type="text" name="nama" value="{{ old('nama', $mitra->nama) }}" required>
            </div>
        </div>

        <div class="field-row">
            <div class="field" style="flex:1;">
                <label>No. HP / WA</label>
                <input type="text" name="no_hp" value="{{ old('no_hp', $mitra->no_hp) }}">
            </div>
            <div class="field" style="flex:1;">
                <label>KAE</label>
                <select name="kae_code">
                    <option value="">— Belum ditentukan —</option>
                    @foreach ($kaeOptions as $kae)
                        <option value="{{ $kae->kae_code }}" {{ old('kae_code', $mitra->kae_code) === $kae->kae_code ? 'selected' : '' }}>{{ $kae->name }} ({{ $kae->kae_code }})</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="field">
            <label>Alamat</label>
            <input type="text" name="alamat" value="{{ old('alamat', $mitra->alamat) }}">
        </div>

        <div class="field-row">
            <div class="field" style="flex:1;">
                <label>Provinsi</label>
                <input type="text" name="provinsi" value="{{ old('provinsi', $mitra->provinsi) }}">
            </div>
            <div class="field" style="flex:1;">
                <label>Kota/Kab</label>
                <input type="text" name="kota" value="{{ old('kota', $mitra->kota) }}">
            </div>
        </div>

        <div class="field-row">
            <div class="field" style="flex:1;">
                <label>Kecamatan</label>
                <input type="text" name="kecamatan" value="{{ old('kecamatan', $mitra->kecamatan) }}">
            </div>
            <div class="field" style="flex:1;">
                <label>Desa</label>
                <input type="text" name="desa" value="{{ old('desa', $mitra->desa) }}">
            </div>
            <div class="field" style="width:120px;">
                <label>Kodepos</label>
                <input type="text" name="kodepos" value="{{ old('kodepos', $mitra->kodepos) }}">
            </div>
        </div>

        <div class="field">
            <label>Status</label>
            <select name="status">
                <option value="aktif" {{ old('status', $mitra->status ?? 'aktif') === 'aktif' ? 'selected' : '' }}>Aktif</option>
                <option value="nonaktif" {{ old('status', $mitra->status) === 'nonaktif' ? 'selected' : '' }}>Nonaktif</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary" style="width:auto; margin-top:8px;">{{ $mitra->exists ? 'Simpan Perubahan' : 'Tambah Mitra' }}</button>
    </form>
@endsection
