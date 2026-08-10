@extends('layouts.app')

@section('content')
    <h1 class="display" style="font-size:24px;">Pengaturan Periode Mingguan</h1>
    <div style="color:var(--ink-muted); font-size:13px; margin-top:4px; margin-bottom:24px;">
        Tentukan rentang tanggal W1&ndash;W4 (atau W5) untuk bulan tertentu &mdash; dipakai di semua fitur yang menghitung per-minggu (Dashboard, Follow-up Log, Weekly Plan).
    </div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    <form method="GET" action="{{ route('pengaturan.minggu') }}" class="field-row" style="align-items:flex-end; margin-bottom:16px;">
        <div class="field" style="margin-bottom:0;">
            <label>Bulan</label>
            <select class="select-pill" name="bulan" onchange="this.form.submit()">
                @foreach (['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'] as $i => $nama)
                    <option value="{{ $i + 1 }}" {{ $bulan == $i + 1 ? 'selected' : '' }}>{{ $nama }}</option>
                @endforeach
            </select>
        </div>
        <div class="field" style="margin-bottom:0;">
            <label>Tahun</label>
            <select class="select-pill" name="tahun" onchange="this.form.submit()">
                @for ($y = now()->year - 1; $y <= now()->year + 1; $y++)
                    <option value="{{ $y }}" {{ $tahun == $y ? 'selected' : '' }}>{{ $y }}</option>
                @endfor
            </select>
        </div>
    </form>

    @unless ($sudahDiset)
        <div class="card" style="margin-bottom:16px; font-size:12.5px; color:var(--ink-muted);">
            Bulan ini belum pernah diset &mdash; di bawah sudah kuisi saran default (7 hari per minggu). Ubah kalau perlu, lalu Simpan.
        </div>
    @endunless

    <form method="POST" action="{{ route('pengaturan.minggu.update') }}" class="card" style="max-width:640px;">
        @csrf
        <input type="hidden" name="bulan" value="{{ $bulan }}">
        <input type="hidden" name="tahun" value="{{ $tahun }}">

        @foreach ($rows as $r)
            <div class="field-row">
                <div class="field" style="width:60px; margin-bottom:0;">
                    <label>&nbsp;</label>
                    <div style="padding:10px 0; font-weight:700;">{{ $r['minggu'] }}</div>
                </div>
                <div class="field" style="flex:1;">
                    <label>Mulai</label>
                    <input type="date" name="minggu[{{ $r['minggu'] }}][mulai]" value="{{ is_array($r) ? $r['tanggal_mulai'] : $r->tanggal_mulai->toDateString() }}" required>
                </div>
                <div class="field" style="flex:1;">
                    <label>Selesai</label>
                    <input type="date" name="minggu[{{ $r['minggu'] }}][selesai]" value="{{ is_array($r) ? $r['tanggal_selesai'] : $r->tanggal_selesai->toDateString() }}" required>
                </div>
            </div>
        @endforeach

        <button type="submit" class="btn btn-primary" style="width:auto; margin-top:8px;">Simpan Periode Mingguan</button>
    </form>
@endsection
