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
                <label>Produk</label>
                <input type="text" name="produk" value="{{ old('produk', $isEdit ? $cpCase->produk : '') }}" required>
            </div>
            <div class="field" style="flex:1;">
                <label>Kode Barcode</label>
                <input type="text" name="kode_barcode" value="{{ old('kode_barcode', $isEdit ? $cpCase->kode_barcode : '') }}">
            </div>
        </div>

        <div class="field-row">
            <div class="field" style="flex:1;">
                <label>Harga SOP (Rp)</label>
                <input type="number" step="1" min="0" name="harga_sop" value="{{ old('harga_sop', $isEdit ? $cpCase->harga_sop : '') }}" required>
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

        @if ($isEdit)
            <div style="border-top:1px solid var(--line); margin:20px 0; padding-top:16px;">
                <div style="font-weight:700; margin-bottom:14px;">Follow-up</div>
                @for ($i = 1; $i <= 3; $i++)
                    <div class="field-row">
                        <div class="field" style="flex:1;">
                            <label>Tanggal Follow-up {{ $i }}</label>
                            <input type="date" name="follow_up_{{ $i }}_tanggal" value="{{ old("follow_up_{$i}_tanggal", $cpCase->{"follow_up_{$i}_tanggal"}?->toDateString()) }}">
                        </div>
                        <div class="field" style="flex:1;">
                            <label style="display:flex; align-items:center; gap:7px; margin-top:8px;">
                                <input type="checkbox" name="follow_up_{{ $i }}_status" value="1" {{ old("follow_up_{$i}_status", $cpCase->{"follow_up_{$i}_status"}) ? 'checked' : '' }}>
                                Direspon mitra
                            </label>
                        </div>
                    </div>
                @endfor

                <div style="font-weight:700; margin:20px 0 14px;">Case Close & Takedown</div>
                <div class="field-row">
                    <div class="field" style="flex:1;">
                        <label>Tanggal Case Close</label>
                        <input type="date" name="tanggal_case_close" value="{{ old('tanggal_case_close', $cpCase->tanggal_case_close?->toDateString()) }}">
                    </div>
                    <div class="field" style="flex:1;">
                        <label>Bukti Case Close (link)</label>
                        <input type="text" name="bukti_case_close" value="{{ old('bukti_case_close', $cpCase->bukti_case_close) }}">
                    </div>
                </div>
                <div class="field-row">
                    <div class="field" style="flex:1;">
                        <label>Status Take Down</label>
                        <select name="status_takedown">
                            <option value="">—</option>
                            <option value="Pending" {{ old('status_takedown', $cpCase->status_takedown) === 'Pending' ? 'selected' : '' }}>Pending</option>
                            <option value="Approved" {{ old('status_takedown', $cpCase->status_takedown) === 'Approved' ? 'selected' : '' }}>Approved</option>
                            <option value="Rejected" {{ old('status_takedown', $cpCase->status_takedown) === 'Rejected' ? 'selected' : '' }}>Rejected</option>
                        </select>
                    </div>
                    <div class="field" style="flex:1; justify-content:flex-end;">
                        <label style="display:flex; align-items:center; gap:7px; margin-top:8px;">
                            <input type="checkbox" name="approval_takedown" value="1" {{ old('approval_takedown', $cpCase->approval_takedown) ? 'checked' : '' }}>
                            Approval Takedown disetujui
                        </label>
                        <label style="display:flex; align-items:center; gap:7px; margin-top:8px;">
                            <input type="checkbox" name="banding" value="1" {{ old('banding', $cpCase->banding) ? 'checked' : '' }}>
                            Mitra mengajukan banding
                        </label>
                    </div>
                </div>

                @if ($cpCase->takedownBanding)
                    <div class="alert-success" style="margin-top:8px;">
                        Kasus ini sudah masuk data Take Down &amp; Banding (status banding: {{ $cpCase->takedownBanding->status_banding ?? '—' }}).
                    </div>
                @endif
            </div>
        @endif

        <button type="submit" class="btn btn-primary" style="width:auto; margin-top:8px;">{{ $isEdit ? 'Simpan Perubahan' : 'Simpan Kasus' }}</button>
    </form>
@endsection
