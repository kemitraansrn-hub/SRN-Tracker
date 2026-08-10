@extends('layouts.app')

@section('content')
    <a href="{{ route('special-deal.index') }}" style="display:inline-flex; align-items:center; gap:6px; font-size:12.5px; color:var(--ink-muted); text-decoration:none; margin-bottom:16px;">
        &larr; Kembali
    </a>

    <h1 class="display" style="font-size:22px; margin-bottom:20px;">{{ $deal->exists ? 'Edit Special Deal' : 'Ajukan Special Deal' }}</h1>

    @if ($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ $deal->exists ? route('special-deal.update', $deal) : route('special-deal.store') }}" class="card" style="max-width:640px;">
        @csrf
        @if ($deal->exists) @method('PUT') @endif

        <div class="field">
            <label>Mitra</label>
            <select name="mitra_id" required {{ $deal->exists ? 'disabled' : '' }}>
                <option value="">— Pilih mitra —</option>
                @foreach ($mitraOptions as $m)
                    <option value="{{ $m->id }}" {{ old('mitra_id', $selectedMitraId) == $m->id ? 'selected' : '' }}>{{ $m->nama }} ({{ $m->kode_mitra }})</option>
                @endforeach
            </select>
            @if ($deal->exists)
                <input type="hidden" name="mitra_id" value="{{ $deal->mitra_id }}">
            @endif
        </div>

        <div class="field">
            <label>Deskripsi Deal</label>
            <textarea name="deskripsi" rows="3" required style="font-family:inherit; font-size:14px; padding:10px 12px; border:1px solid var(--line); border-radius:8px; background:var(--surface-alt); color:var(--ink); resize:vertical;">{{ old('deskripsi', $deal->deskripsi) }}</textarea>
        </div>

        <div class="field-row">
            <div class="field" style="flex:1;">
                <label>Nilai Deal (Rp)</label>
                <input type="number" step="1" min="0" name="nilai" value="{{ old('nilai', $deal->nilai) }}">
            </div>
            <div class="field" style="flex:1;">
                <label>Status</label>
                <select name="status">
                    @foreach (\App\Http\Controllers\SpecialDealController::STATUSES as $s)
                        <option value="{{ $s }}" {{ old('status', $deal->status ?? 'diajukan') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="field-row">
            <div class="field" style="flex:1;">
                <label>Tanggal Mulai</label>
                <input type="date" name="tanggal_mulai" value="{{ old('tanggal_mulai', optional($deal->tanggal_mulai)->toDateString()) }}">
            </div>
            <div class="field" style="flex:1;">
                <label>Tanggal Selesai</label>
                <input type="date" name="tanggal_selesai" value="{{ old('tanggal_selesai', optional($deal->tanggal_selesai)->toDateString()) }}">
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width:auto; margin-top:8px;">{{ $deal->exists ? 'Simpan Perubahan' : 'Ajukan Deal' }}</button>
    </form>
@endsection
