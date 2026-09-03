@extends('layouts.app')

@section('content')
    <h1 class="display" style="font-size:24px;">Tingkat Penyusutan Buy Back</h1>
    <div style="color:var(--ink-muted); font-size:13px; margin-top:4px; margin-bottom:24px;">
        Satu angka berlaku untuk semua Pengajuan Buy Back baru maupun yang sedang diedit &mdash; KAE tidak bisa mengubahnya sendiri.
    </div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('buyback-setting.update') }}" class="card" style="max-width:420px;">
        @csrf
        <div class="field">
            <label>Tingkat Penyusutan (%)</label>
            <input type="number" name="tingkat_penyusutan" min="0" max="100" step="0.01" value="{{ old('tingkat_penyusutan', $currentRate) }}" required>
            <div style="font-size:11.5px; color:var(--ink-muted); margin-top:4px;">Dipakai di rumus Nilai Buy Back: Subtotal &times; (1 &minus; rate)<sup>umur bulan</sup>.</div>
        </div>
        <button type="submit" class="btn btn-primary" style="width:auto; margin-top:8px;">Simpan</button>
    </form>
@endsection
