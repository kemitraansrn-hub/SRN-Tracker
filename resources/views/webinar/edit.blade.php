@extends('layouts.app')

@section('content')
    <a href="{{ route('tracking-webinar.index') }}" style="display:inline-block; margin-bottom:12px; font-size:13px; color:var(--ink-muted); text-decoration:none;">&larr; Kembali ke Tracking Webinar</a>

    <section class="card" style="max-width:420px;">
        <h1 class="display" style="font-size:20px; margin-bottom:16px;">Edit Webinar</h1>

        @if ($errors->any())
            <div class="alert-error" style="margin-bottom:16px;">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('tracking-webinar.update', $webinar) }}">
            @csrf
            @method('PUT')
            <div class="field">
                <label>Nama Webinar</label>
                <select name="nama_webinar" required>
                    @foreach ($namaOptions as $opt)
                        <option value="{{ $opt }}" {{ old('nama_webinar', $webinar->nama_webinar) === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>Tanggal Webinar</label>
                <input type="date" name="tanggal_webinar" value="{{ old('tanggal_webinar', $webinar->tanggal_webinar->toDateString()) }}" required>
            </div>
            <div class="field">
                <label>Jumlah Peserta</label>
                <input type="number" name="jumlah_peserta" min="0" value="{{ old('jumlah_peserta', $webinar->jumlah_peserta) }}" required>
            </div>
            <div class="field">
                <label>Bukti Zoom (link Google Drive)</label>
                <input type="url" name="bukti_zoom_url" placeholder="https://drive.google.com/..." value="{{ old('bukti_zoom_url', $webinar->bukti_zoom_url) }}" required>
            </div>
            <div style="display:flex; gap:10px; margin-top:4px;">
                <button type="submit" class="btn btn-primary" style="width:auto;">Simpan</button>
                <a href="{{ route('tracking-webinar.index') }}" class="btn" style="width:auto;">Batal</a>
            </div>
        </form>
    </section>
@endsection
