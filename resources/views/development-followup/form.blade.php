@extends('layouts.app')

@section('content')
    <a href="{{ route('growth-specialist.followup.index') }}" style="display:inline-flex; align-items:center; gap:6px; font-size:12.5px; color:var(--ink-muted); text-decoration:none; margin-bottom:16px;">
        &larr; Kembali
    </a>

    <h1 class="display" style="font-size:22px; margin-bottom:20px;">{{ isset($log) ? 'Edit Follow-up' : 'Catat Follow-up' }}</h1>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ isset($log) ? route('growth-specialist.followup.update', $log) : route('growth-specialist.followup.store') }}" class="card" style="max-width:640px;">
        @csrf
        @if (isset($log))
            @method('PUT')
        @endif

        <div class="field-row">
            <div class="field" style="flex:1;">
                <label>Mitra</label>
                @include('partials.searchable-select', [
                    'name' => 'mitra_id',
                    'options' => $mitraOptions->map(fn ($m) => (object) ['id' => $m->id, 'label' => $m->nama.' ('.$m->kode_mitra.')']),
                    'selectedId' => old('mitra_id', $selectedMitraId),
                    'placeholder' => 'Ketik buat cari mitra...',
                    'required' => true,
                ])
            </div>
            <div class="field" style="width:180px;">
                <label>Tanggal Follow-up</label>
                <input type="date" name="tanggal_fu" value="{{ old('tanggal_fu', isset($log) ? $log->tanggal_fu->toDateString() : now()->toDateString()) }}" required>
            </div>
        </div>

        @if (isset($log))
            <div class="field">
                <label>Status Follow-up</label>
                <div>
                    @if ($log->status_followup === \App\Models\DevelopmentFollowupLog::STATUS_PEMICU_RED_ZONE)
                        <span class="chip chip-critical">{{ $log->status_followup }}</span>
                    @else
                        <span class="chip chip-neutral">{{ $log->status_followup }}</span>
                    @endif
                    <span style="color:var(--ink-muted); font-size:12px; margin-left:8px;">dihitung otomatis dari urutan follow-up mitra ini, gak bisa diubah manual</span>
                </div>
            </div>
        @endif

        <div class="field">
            <label>Alasan / Kendala</label>
            <select name="alasan_kendala">
                <option value="">— Tidak ada —</option>
                @foreach ($alasanOptions as $alasan)
                    <option value="{{ $alasan }}" {{ old('alasan_kendala', $log->alasan_kendala ?? '') === $alasan ? 'selected' : '' }}>{{ $alasan }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label>Catatan / Tindak Lanjut</label>
            <textarea name="catatan" rows="3" style="resize:vertical;">{{ old('catatan', $log->catatan ?? '') }}</textarea>
        </div>

        <div class="field-row">
            <div class="field" style="flex:1;">
                <label>Jam Mulai</label>
                <input type="time" name="jam_mulai" value="{{ old('jam_mulai', isset($log) ? substr((string) $log->jam_mulai, 0, 5) : '') }}">
            </div>
            <div class="field" style="flex:1;">
                <label>Jam Selesai</label>
                <input type="time" name="jam_selesai" value="{{ old('jam_selesai', isset($log) ? substr((string) $log->jam_selesai, 0, 5) : '') }}">
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width:auto; margin-top:8px;">{{ isset($log) ? 'Update Follow-up' : 'Simpan Follow-up' }}</button>
    </form>
@endsection
