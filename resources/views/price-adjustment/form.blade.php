@extends('layouts.app')

@php
    $isEdit = isset($priceAdjustmentRequest);
@endphp

@section('content')
    <a href="{{ route('price-adjustment.index') }}" style="display:inline-flex; align-items:center; gap:6px; font-size:12.5px; color:var(--ink-muted); text-decoration:none; margin-bottom:16px;">
        &larr; Kembali
    </a>

    <h1 class="display" style="font-size:22px; margin-bottom:20px;">{{ $isEdit ? 'Koreksi Pengajuan' : 'Ajukan Izin Penyesuaian Harga' }}</h1>

    @if ($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ $isEdit ? route('price-adjustment.update', $priceAdjustmentRequest) : route('price-adjustment.store') }}" class="card" style="max-width:760px;">
        @csrf
        @if ($isEdit) @method('PUT') @endif

        <div class="field">
            <label>Mitra</label>
            <select name="mitra_id" required>
                <option value="">— pilih —</option>
                @foreach ($mitraOptions as $m)
                    <option value="{{ $m->id }}" {{ (string) old('mitra_id', $isEdit ? $priceAdjustmentRequest->mitra_id : '') === (string) $m->id ? 'selected' : '' }}>{{ $m->nama }} ({{ $m->kode_mitra }})</option>
                @endforeach
            </select>
        </div>

        <div class="field-row">
            <div class="field" style="flex:1;">
                <label>Nama Toko</label>
                <input type="text" name="toko" value="{{ old('toko', $isEdit ? $priceAdjustmentRequest->toko : '') }}" required>
            </div>
            <div class="field" style="flex:1;">
                <label>Marketplace</label>
                <select name="marketplace" required>
                    <option value="">— pilih —</option>
                    @foreach ($marketplaceOptions as $mp)
                        <option value="{{ $mp }}" {{ old('marketplace', $isEdit ? $priceAdjustmentRequest->marketplace : '') === $mp ? 'selected' : '' }}>{{ $mp }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="field">
            <label>Link Toko (opsional)</label>
            <input type="text" name="link_toko" value="{{ old('link_toko', $isEdit ? $priceAdjustmentRequest->link_toko : '') }}" placeholder="https://...">
        </div>

        <div class="field-row">
            <div class="field" style="flex:1;">
                <label>Tanggal Mulai</label>
                <input type="date" name="tanggal_mulai" value="{{ old('tanggal_mulai', $isEdit ? $priceAdjustmentRequest->tanggal_mulai->toDateString() : '') }}" required>
            </div>
            <div class="field" style="flex:1;">
                <label>Tanggal Selesai</label>
                <input type="date" name="tanggal_selesai" value="{{ old('tanggal_selesai', $isEdit ? $priceAdjustmentRequest->tanggal_selesai->toDateString() : '') }}" required>
            </div>
        </div>

        <div class="field">
            <label>Catatan (opsional)</label>
            <textarea name="catatan" rows="3" placeholder="Alasan mitra minta izin turun harga...">{{ old('catatan', $isEdit ? $priceAdjustmentRequest->catatan : '') }}</textarea>
        </div>

        @if ($isEdit)
            <div class="field">
                <label>Status</label>
                <div style="padding:10px 13px; border:1px solid var(--line); border-radius:10px; background:var(--surface-alt); color:var(--ink-muted); font-size:14px;">
                    {{ $priceAdjustmentRequest->status_approval }}
                </div>
                <div style="font-size:11px; color:var(--ink-muted); margin-top:4px;">Keputusan Approve/Reject dilakukan lewat tombol "Putuskan" di daftar, bukan di sini.</div>
            </div>
        @endif

        <button type="submit" class="btn btn-primary" style="width:auto; margin-top:8px;">{{ $isEdit ? 'Simpan Perubahan' : 'Simpan Pengajuan' }}</button>
    </form>
@endsection
