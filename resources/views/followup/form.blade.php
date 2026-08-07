@extends('layouts.app')

@section('content')
    <a href="{{ route('followup.index') }}" style="display:inline-flex; align-items:center; gap:6px; font-size:12.5px; color:var(--ink-muted); text-decoration:none; margin-bottom:16px;">
        &larr; Kembali
    </a>

    <h1 class="display" style="font-size:22px; margin-bottom:20px;">Catat Follow-up</h1>

    @if ($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('followup.store') }}" class="card" style="max-width:640px;">
        @csrf

        <div class="field-row">
            <div class="field" style="flex:1;">
                <label>Mitra</label>
                <select name="mitra_id" required>
                    <option value="">— Pilih mitra —</option>
                    @foreach ($mitraOptions as $m)
                        <option value="{{ $m->id }}" {{ old('mitra_id', $selectedMitraId) == $m->id ? 'selected' : '' }}>{{ $m->nama }} ({{ $m->kode_mitra }})</option>
                    @endforeach
                </select>
            </div>
            <div class="field" style="width:180px;">
                <label>Tanggal Follow-up</label>
                <input type="date" name="tanggal_fu" value="{{ old('tanggal_fu', now()->toDateString()) }}" required>
            </div>
        </div>

        <div class="field-row">
            <div class="field" style="flex:1;">
                <label>Status Follow-up</label>
                <select name="status_followup" required>
                    <option value="Terhubung" {{ old('status_followup') === 'Terhubung' ? 'selected' : '' }}>Terhubung</option>
                    <option value="Tidak ada respon" {{ old('status_followup') === 'Tidak ada respon' ? 'selected' : '' }}>Tidak ada respon</option>
                </select>
            </div>
            <div class="field" style="flex:1;">
                <label>Status Belanja</label>
                <select name="status_belanja" id="statusBelanja" onchange="toggleNominalKendala()" required>
                    <option value="Belanja Penuh" {{ old('status_belanja') === 'Belanja Penuh' ? 'selected' : '' }}>Belanja Penuh</option>
                    <option value="Belanja Sebagian" {{ old('status_belanja') === 'Belanja Sebagian' ? 'selected' : '' }}>Belanja Sebagian</option>
                    <option value="Belum Belanja" {{ old('status_belanja') === 'Belum Belanja' ? 'selected' : '' }}>Belum Belanja</option>
                </select>
            </div>
        </div>

        <div class="field-row">
            <div class="field" style="flex:1;" id="nominalField">
                <label>Nominal Belanja (Rp)</label>
                <input type="number" step="1" min="0" name="nominal_belanja" value="{{ old('nominal_belanja') }}">
            </div>
            <div class="field" style="flex:1;" id="kendalaField">
                <label>Alasan / Kendala</label>
                <select name="alasan_kendala">
                    <option value="">— Tidak ada —</option>
                    @foreach ($alasanOptions as $alasan)
                        <option value="{{ $alasan }}" {{ old('alasan_kendala') === $alasan ? 'selected' : '' }}>{{ $alasan }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="field">
            <label>Catatan / Tindak Lanjut</label>
            <textarea name="catatan" rows="3" style="font-family:inherit; font-size:14px; padding:10px 12px; border:1px solid var(--line); border-radius:8px; background:var(--surface-alt); color:var(--ink); resize:vertical;">{{ old('catatan') }}</textarea>
        </div>

        <div class="field-row">
            <div class="field" style="flex:1;">
                <label>Jam Mulai</label>
                <input type="time" name="jam_mulai" value="{{ old('jam_mulai') }}">
            </div>
            <div class="field" style="flex:1;">
                <label>Jam Selesai</label>
                <input type="time" name="jam_selesai" value="{{ old('jam_selesai') }}">
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width:auto; margin-top:8px;">Simpan Follow-up</button>
    </form>

    <script>
        function toggleNominalKendala() {
            const status = document.getElementById('statusBelanja').value;
            document.getElementById('nominalField').style.display = status === 'Belum Belanja' ? 'none' : '';
            document.getElementById('kendalaField').style.display = status === 'Belanja Penuh' ? 'none' : '';
        }
        toggleNominalKendala();
    </script>
@endsection
