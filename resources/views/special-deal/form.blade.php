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
            <textarea name="deskripsi" rows="3" required style="resize:vertical;">{{ old('deskripsi', $deal->deskripsi) }}</textarea>
        </div>

        <div class="field-row">
            <div class="field" style="flex:1;">
                <label>Segmentasi</label>
                <select name="segmen" required>
                    <option value="">— Pilih segmen —</option>
                    @foreach (\App\Models\SpecialDeal::SEGMEN_OPTIONS as $s)
                        <option value="{{ $s }}" {{ old('segmen', $deal->segmen) === $s ? 'selected' : '' }}>{{ $s }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field" style="flex:1;">
                <label>Status MOU</label>
                <select name="status">
                    @foreach (\App\Http\Controllers\SpecialDealController::STATUSES as $s)
                        <option value="{{ $s }}" {{ old('status', $deal->status ?? 'proses') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="field-row">
            <div class="field" style="flex:1;">
                <label>Kuartal</label>
                <select name="kuartal" required>
                    @foreach ([1, 2, 3, 4] as $q)
                        <option value="{{ $q }}" {{ old('kuartal', $deal->kuartal ?? now()->quarter) == $q ? 'selected' : '' }}>Q{{ $q }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field" style="flex:1;">
                <label>Tahun</label>
                <select name="tahun" required>
                    @for ($y = now()->year + 1; $y >= now()->year - 2; $y--)
                        <option value="{{ $y }}" {{ old('tahun', $deal->tahun ?? now()->year) == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
        </div>

        <div class="field-row">
            <div class="field" style="flex:1;">
                <label>Target Kuartal (Rp)</label>
                <input type="number" step="1" min="0" name="target_kuartal" value="{{ old('target_kuartal', $deal->target_kuartal !== null ? (int) $deal->target_kuartal : '') }}" required>
                <div style="font-size:11.5px; color:var(--ink-muted); margin-top:5px;">Target bulanan otomatis = target kuartal &divide; 3.</div>
            </div>
            <div class="field" style="flex:1;">
                <label>Budget (%)</label>
                <input type="number" step="0.01" min="0" max="100" name="budget_persen" value="{{ old('budget_persen', $deal->budget_persen) }}" required>
                <div style="font-size:11.5px; color:var(--ink-muted); margin-top:5px;">Nominal reward otomatis = target kuartal &times; budget%.</div>
            </div>
        </div>

        @php
            $subsidiValue = old('subsidi', $deal->subsidi);
            $subsidiIsCustom = $subsidiValue && ! in_array($subsidiValue, \App\Models\SpecialDeal::SUBSIDI_OPTIONS, true);
        @endphp
        <div class="field">
            <label>Subsidi</label>
            <select id="subsidi_pilihan" onchange="
                var manual = this.value === '__lainnya__';
                document.getElementById('subsidi').style.display = manual ? 'block' : 'none';
                if (! manual) { document.getElementById('subsidi').value = this.value; } else { document.getElementById('subsidi').value = ''; document.getElementById('subsidi').focus(); }
            ">
                <option value="">— Pilih subsidi —</option>
                @foreach (\App\Models\SpecialDeal::SUBSIDI_OPTIONS as $s)
                    <option value="{{ $s }}" {{ $subsidiValue === $s ? 'selected' : '' }}>{{ $s }}</option>
                @endforeach
                <option value="__lainnya__" {{ $subsidiIsCustom ? 'selected' : '' }}>Lainnya (isi manual)</option>
            </select>
            <input type="text" name="subsidi" id="subsidi" value="{{ $subsidiValue }}"
                placeholder="Tulis subsidi manual" required
                style="margin-top:8px; {{ $subsidiIsCustom ? '' : 'display:none;' }}">
        </div>

        <button type="submit" class="btn btn-primary" style="width:auto; margin-top:8px;">{{ $deal->exists ? 'Simpan Perubahan' : 'Ajukan Deal' }}</button>
    </form>
@endsection
