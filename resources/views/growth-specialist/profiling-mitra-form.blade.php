@extends('layouts.app')

@php
    $skorChip = fn (?int $s) => $s === null ? 'chip-neutral' : ($s >= 4 ? 'chip-good' : ($s == 3 ? 'chip-warn' : 'chip-critical'));
    $tipeMitraChip = fn (?string $t) => $t === 'Prioritas' ? 'chip-highlight' : 'chip-neutral';
    $tipeMitra = $profil->tipeMitra();
    $pct = $profil->persenOperationalCost();
    $tol = $profil->toleransiCashflow();
    $deadlineClosing = $profil->deadlineClosingPertama();
@endphp

@section('content')
    <a href="{{ route('growth-specialist.profiling-mitra') }}" style="display:inline-flex; align-items:center; gap:6px; font-size:12.5px; color:var(--ink-muted); text-decoration:none; margin-bottom:16px;">
        &larr; Kembali ke Tabel
    </a>

    <h1 class="display" style="font-size:22px; margin-bottom:4px;">Profiling Mitra &mdash; {{ $mitra->nama }}</h1>
    <div class="card-hint" style="margin-bottom:20px;">{{ $mitra->kode_mitra }}</div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('growth-specialist.profiling-mitra.update', $mitra) }}" style="max-width:820px;">
        @csrf
        @method('PUT')

        {{-- 1. Identitas Mitra --}}
        <div class="card" style="margin-bottom:20px;">
            <div class="card-title" style="margin-bottom:16px;">1. Identitas Mitra</div>

            <div class="field-row">
                <div class="field" style="flex:1;">
                    <label>Nama Mitra</label>
                    <input type="text" value="{{ $mitra->nama }}" disabled>
                </div>
                <div class="field" style="flex:1;">
                    <label>KAE RO</label>
                    <input type="text" value="{{ $kaeNama ?? '—' }}" disabled>
                </div>
                <div class="field" style="flex:1;">
                    <label>Channel</label>
                    <input type="text" value="{{ $channel ?? '—' }}" disabled>
                </div>
            </div>

            <div class="field-row">
                <div class="field" style="flex:1;">
                    <label>Status</label>
                    <select name="status">
                        <option value="">— pilih —</option>
                        <option value="Existing" @selected(old('status', $profil->status) === 'Existing')>Existing</option>
                        <option value="New Distri" @selected(old('status', $profil->status) === 'New Distri')>New Distri</option>
                    </select>
                </div>
                <div class="field" style="flex:1;">
                    <label>Tanggal Onboarding</label>
                    <input type="date" name="tanggal_onboarding" value="{{ old('tanggal_onboarding', $profil->tanggal_onboarding?->format('Y-m-d')) }}">
                </div>
            </div>
        </div>

        {{-- 2. Kekuatan Finansial & Operasional --}}
        <div class="card" style="margin-bottom:20px;">
            <div class="card-title" style="margin-bottom:16px;">2. Kekuatan Finansial &amp; Operasional</div>

            <div class="field-row">
                <div class="field" style="flex:1;">
                    <label>Modal Bisnis (Rp)</label>
                    <input type="number" min="0" step="1" name="modal_bisnis" value="{{ old('modal_bisnis', $profil->modal_bisnis) }}">
                </div>
                <div class="field" style="flex:1;">
                    <label>Modal SRN (Rp)</label>
                    <input type="number" min="0" step="1" name="modal_srn" value="{{ old('modal_srn', $profil->modal_srn) }}">
                </div>
                <div class="field" style="flex:1;">
                    <label>Cost Marketing + Operasional (Rp)</label>
                    <input type="number" min="0" step="1" name="cost" value="{{ old('cost', $profil->cost) }}">
                </div>
            </div>

            <div class="field-row">
                <div class="field" style="flex:1;">
                    <label>% Operational Cost <span style="font-weight:400; color:var(--ink-faint);">(otomatis)</span></label>
                    <input type="text" value="{{ $pct !== null ? $pct.'%' : '—' }}" disabled>
                </div>
                <div class="field" style="flex:1;">
                    <label>Toleransi Cashflow <span style="font-weight:400; color:var(--ink-faint);">(otomatis)</span></label>
                    <input type="text" value="{{ $tol ?? '—' }}" disabled>
                </div>
            </div>

            <div class="field-row">
                <div class="field" style="flex:1;">
                    <label>Tim / Sendiri</label>
                    <input type="text" name="tim_sendiri" value="{{ old('tim_sendiri', $profil->tim_sendiri) }}" placeholder="mis. Sendiri, Tim kecil (2), Tim Besar (5)">
                </div>
                <div class="field" style="flex:1;">
                    <label>Jam Aktif</label>
                    <input type="text" name="jam_aktif" value="{{ old('jam_aktif', $profil->jam_aktif) }}" placeholder="mis. 8 jam">
                </div>
            </div>

            <div class="field">
                <label>Platform Jualan</label>
                <div style="display:flex; flex-wrap:wrap; gap:8px 18px; padding-top:4px;">
                    @foreach ($platformOptions as $opt)
                        <label style="font-size:13px; display:flex; align-items:center; gap:5px; font-weight:400;">
                            <input type="checkbox" name="platform_jualan[]" value="{{ $opt }}" @checked(collect(old('platform_jualan', $profil->platform_jualan ?? []))->contains($opt))>
                            {{ $opt }}
                        </label>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- 3. Channel Fokus --}}
        <div class="card" style="margin-bottom:20px;">
            <div class="card-title" style="margin-bottom:16px;">3. Channel Fokus</div>

            <div class="field">
                <label>Tipe Channel</label>
                <select name="tipe_channel">
                    <option value="">— pilih —</option>
                    <option value="Online" @selected(old('tipe_channel', $profil->tipe_channel) === 'Online')>Online</option>
                    <option value="Offline" @selected(old('tipe_channel', $profil->tipe_channel) === 'Offline')>Offline</option>
                </select>
            </div>

            <div class="field-row">
                <div class="field" style="flex:1;">
                    <label>Channel Fokus 1</label>
                    <div style="display:flex; flex-wrap:wrap; gap:8px 18px; padding-top:4px;">
                        @foreach ($platformOptions as $opt)
                            <label style="font-size:13px; display:flex; align-items:center; gap:5px; font-weight:400;">
                                <input type="checkbox" name="channel_fokus_1[]" value="{{ $opt }}" @checked(collect(old('channel_fokus_1', $profil->channel_fokus_1 ?? []))->contains($opt))>
                                {{ $opt }}
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="field-row">
                <div class="field" style="flex:1;">
                    <label>Channel Fokus 2</label>
                    <div style="display:flex; flex-wrap:wrap; gap:8px 18px; padding-top:4px;">
                        @foreach ($platformOptions as $opt)
                            <label style="font-size:13px; display:flex; align-items:center; gap:5px; font-weight:400;">
                                <input type="checkbox" name="channel_fokus_2[]" value="{{ $opt }}" @checked(collect(old('channel_fokus_2', $profil->channel_fokus_2 ?? []))->contains($opt))>
                                {{ $opt }}
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- 4. Klasifikasi Mitra --}}
        <div class="card" style="margin-bottom:20px;">
            <div class="card-title" style="margin-bottom:16px;">4. Klasifikasi Mitra</div>

            <div class="field-row">
                @foreach (['motivasi' => 'Motivasi', 'kemampuan' => 'Kemampuan', 'keaktifan' => 'Keaktifan'] as $field => $label)
                    <div class="field" style="flex:1;">
                        <label>{{ $label }} (1&ndash;5)</label>
                        <select name="{{ $field }}">
                            <option value="">— pilih —</option>
                            @for ($i = 1; $i <= 5; $i++)
                                <option value="{{ $i }}" @selected((string) old($field, $profil->$field) === (string) $i)>{{ $i }}</option>
                            @endfor
                        </select>
                    </div>
                @endforeach
            </div>

            <div class="field-row">
                <div class="field" style="flex:1;">
                    <label>Tipe Mitra <span style="font-weight:400; color:var(--ink-faint);">(otomatis)</span></label>
                    <input type="text" value="{{ $tipeMitra ?? '—' }}" disabled>
                </div>
                <div class="field" style="flex:1;">
                    <label>Deadline Setup Channel</label>
                    <input type="date" name="deadline_setup_channel" value="{{ old('deadline_setup_channel', $profil->deadline_setup_channel?->format('Y-m-d')) }}">
                </div>
            </div>
        </div>

        {{-- 5. KPI Awal --}}
        <div class="card" style="margin-bottom:20px;">
            <div class="card-title" style="margin-bottom:16px;">5. KPI Awal</div>

            <div class="field-row">
                <div class="field" style="flex:1;">
                    <label>Target Traffic (per hari)</label>
                    <input type="number" min="0" step="1" name="target_traffic" value="{{ old('target_traffic', $profil->target_traffic) }}">
                </div>
                <div class="field" style="flex:1;">
                    <label>Target Leads (per hari)</label>
                    <input type="number" min="0" step="1" name="target_leads" value="{{ old('target_leads', $profil->target_leads) }}">
                </div>
                <div class="field" style="flex:1;">
                    <label>Deadline Closing Pertama <span style="font-weight:400; color:var(--ink-faint);">(otomatis)</span></label>
                    <input type="text" value="{{ $deadlineClosing?->format('d/m/Y') ?? '—' }}" disabled>
                </div>
            </div>
        </div>

        {{-- 6. Integrasi LMS --}}
        <div class="card" style="margin-bottom:20px;">
            <div class="card-title" style="margin-bottom:16px;">6. Integrasi LMS</div>

            <div class="field">
                <label>Status</label>
                <select name="lms_status">
                    <option value="">— pilih —</option>
                    <option value="Done" @selected(old('lms_status', $profil->lms_status) === 'Done')>Done</option>
                    <option value="On Progress" @selected(old('lms_status', $profil->lms_status) === 'On Progress')>On Progress</option>
                </select>
            </div>
        </div>

        {{-- 7. Status (Catatan) --}}
        <div class="card" style="margin-bottom:20px;">
            <div class="card-title" style="margin-bottom:16px;">7. Status (Catatan)</div>

            <div class="field">
                <label>Catatan</label>
                <textarea name="catatan" rows="3">{{ old('catatan', $profil->catatan) }}</textarea>
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width:auto; padding:10px 24px;">Simpan Profil</button>
    </form>
@endsection
