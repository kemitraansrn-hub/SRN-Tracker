@extends('layouts.app')

@section('content')
    <h1 class="display" style="font-size:24px; margin-bottom:4px;">Kartu Profil Mitra</h1>
    <div class="card-hint" style="margin-bottom:20px;">Pilih mitra buat lihat/cetak kartu profilnya.</div>

    <div class="card" style="max-width:480px;">
        <div class="field">
            <label>Pilih Mitra</label>
            <select id="pilihMitraKartu" onchange="if (this.value) window.location.href = this.value;">
                <option value="">— pilih mitra —</option>
                @foreach ($mitraOptions as $m)
                    <option value="{{ route('growth-specialist.profiling-mitra.kartu', $m) }}">{{ $m->nama }} ({{ $m->kode_mitra }})</option>
                @endforeach
            </select>
        </div>
        @if ($mitraOptions->isEmpty())
            <div class="card-hint" style="margin-top:6px;">Belum ada mitra yang diisi profilnya. Isi dulu lewat menu Profiling Mitra.</div>
        @endif
    </div>
@endsection
