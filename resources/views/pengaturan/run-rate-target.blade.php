@extends('layouts.app')

@section('content')
    <h1 class="display" style="font-size:24px;">Target Perusahaan &amp; KAE</h1>
    <div style="color:var(--ink-muted); font-size:13px; margin-top:4px; margin-bottom:24px;">
        Target Rp per bulan, dipakai di card "Target Perusahaan vs Pencapaian" dan "Run Rate Weekly" pada Dashboard. Kosongkan kolom kalau belum mau set target untuk bulan tersebut.
    </div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif

    <form method="GET" action="{{ route('run-rate-target.edit') }}" class="field-row" style="align-items:flex-end; margin-bottom:16px;">
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

    <form method="POST" action="{{ route('run-rate-target.update') }}" class="card" style="max-width:480px;">
        @csrf
        <input type="hidden" name="bulan" value="{{ $bulan }}">
        <input type="hidden" name="tahun" value="{{ $tahun }}">

        <div style="font-weight:700; margin-bottom:14px;">{{ $periodeLabel }}</div>

        <div class="field">
            <label>Target Perusahaan (All Chanel)</label>
            <input type="number" name="company_target" value="{{ old('company_target', $companyTarget !== null ? (int) $companyTarget : '') }}" min="0" placeholder="mis. 5800000000">
        </div>

        @foreach ($kaeUsers as $u)
            <div class="field">
                <label>Target {{ $u->name }}</label>
                <input type="number" name="kae_target[{{ $u->kae_code }}]" value="{{ old('kae_target.'.$u->kae_code, $kaeTargets[$u->kae_code] ?? '') }}" min="0" placeholder="mis. 2000000000">
            </div>
        @endforeach

        <button type="submit" class="btn btn-primary" style="width:auto; margin-top:8px;">Simpan Target</button>
    </form>
@endsection
