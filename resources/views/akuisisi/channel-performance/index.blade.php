@extends('layouts.app')

@php
    $canEdit = auth()->user()->hasAdminAccess();
@endphp

@section('content')
    <div style="display:flex; flex-wrap:wrap; justify-content:space-between; align-items:flex-end; gap:12px; margin-bottom:20px;">
        <div>
            <h1 class="display" style="font-size:24px;">Performa Channel</h1>
            <div style="color:var(--ink-muted); font-size:13px; margin-top:4px;">Input manual Lead, Prospect, Closing, Biaya &amp; Investasi Mitra per channel per bulan &mdash; sumber data Dashboard &gt; Akuisisi.</div>
        </div>
        @include('partials.bulan-tahun-filter', ['action' => route('akuisisi-channel-performance.index'), 'bulan' => $bulan, 'tahun' => $tahun, 'isBulanIni' => $isBulanIni])
    </div>

    @if (session('status'))
        <div class="alert-success" style="margin-bottom:16px;">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert-error" style="margin-bottom:16px;">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('akuisisi-channel-performance.store') }}">
        @csrf
        <input type="hidden" name="bulan" value="{{ $bulan }}">
        <input type="hidden" name="tahun" value="{{ $tahun }}">

        <section class="card" style="padding:0;">
            <div class="card-head" style="padding:18px 20px 0; margin-bottom:12px; display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:8px;">
                <div>
                    <div class="card-title">Performa per Channel</div>
                    <div class="card-hint">{{ \Carbon\Carbon::create($tahun, $bulan, 1)->translatedFormat('F Y') }} &mdash; klik channel untuk input datanya</div>
                </div>
                <div style="display:flex; gap:6px;">
                    <button type="button" class="btn" style="width:auto; font-size:11.5px; padding:6px 10px;" onclick="document.querySelectorAll('.cp-panel').forEach(p => p.style.display = '')">Buka Semua</button>
                    <button type="button" class="btn" style="width:auto; font-size:11.5px; padding:6px 10px;" onclick="document.querySelectorAll('.cp-panel').forEach(p => p.style.display = 'none')">Tutup Semua</button>
                </div>
            </div>

            <div style="border-top:1px solid var(--line);">
                @foreach ($rows as $row)
                    @php
                        $ch = $row['channel'];
                        $d = $row['data'];
                        $lead = old('data.'.$ch->id.'.lead', $d->lead ?? 0);
                        $prospect = old('data.'.$ch->id.'.prospect', $d->prospect ?? 0);
                        $closing = old('data.'.$ch->id.'.closing', $d->closing ?? 0);
                        $biaya = old('data.'.$ch->id.'.biaya', $d->biaya ?? 0);
                        $investasi = old('data.'.$ch->id.'.investasi_mitra', $d->investasi_mitra ?? 0);
                        $sudahDiisi = ($lead > 0 || $prospect > 0 || $closing > 0 || $biaya > 0 || $investasi > 0);
                    @endphp
                    <div style="border-bottom:1px solid var(--line);">
                        <button type="button"
                            onclick="const p = document.getElementById('cp-panel-{{ $ch->id }}'); const open = p.style.display !== 'none'; p.style.display = open ? 'none' : ''; document.getElementById('cp-chevron-{{ $ch->id }}').textContent = open ? '▾' : '▴';"
                            style="width:100%; display:flex; align-items:center; justify-content:space-between; gap:12px; padding:14px 20px; background:none; border:none; cursor:pointer; text-align:left; font:inherit; color:var(--ink);">
                            <span style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                                <span style="font-weight:600;">{{ $ch->name }}</span>
                                <span class="chip {{ $sudahDiisi ? 'chip-good' : 'chip-neutral' }}">&#9679; {{ $sudahDiisi ? 'Sudah diisi' : 'Belum diisi' }}</span>
                                <span style="color:var(--ink-muted); font-size:12.5px;">Lead {{ $lead }} &middot; Prospect {{ $prospect }} &middot; Closing {{ $closing }}</span>
                            </span>
                            <span id="cp-chevron-{{ $ch->id }}" style="color:var(--ink-muted);">&#9662;</span>
                        </button>
                        <div id="cp-panel-{{ $ch->id }}" class="cp-panel" style="display:none; padding:4px 20px 18px;">
                            <div class="field-row">
                                <div class="field" style="margin-bottom:0; flex:1; min-width:110px;">
                                    <label>Lead</label>
                                    <input type="number" min="0" step="1" name="data[{{ $ch->id }}][lead]" value="{{ $lead }}" {{ $canEdit ? '' : 'disabled' }}>
                                </div>
                                <div class="field" style="margin-bottom:0; flex:1; min-width:110px;">
                                    <label>Prospect</label>
                                    <input type="number" min="0" step="1" name="data[{{ $ch->id }}][prospect]" value="{{ $prospect }}" {{ $canEdit ? '' : 'disabled' }}>
                                </div>
                                <div class="field" style="margin-bottom:0; flex:1; min-width:110px;">
                                    <label>Closing</label>
                                    <input type="number" min="0" step="1" name="data[{{ $ch->id }}][closing]" value="{{ $closing }}" {{ $canEdit ? '' : 'disabled' }}>
                                </div>
                                <div class="field" style="margin-bottom:0; flex:1; min-width:150px;">
                                    <label>Biaya (Rp)</label>
                                    <input type="number" min="0" step="0.01" name="data[{{ $ch->id }}][biaya]" value="{{ $biaya }}" {{ $canEdit ? '' : 'disabled' }}>
                                </div>
                                <div class="field" style="margin-bottom:0; flex:1; min-width:150px;">
                                    <label>Investasi Mitra (Rp)</label>
                                    <input type="number" min="0" step="0.01" name="data[{{ $ch->id }}][investasi_mitra]" value="{{ $investasi }}" {{ $canEdit ? '' : 'disabled' }}>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($canEdit)
                <div style="padding:16px 20px;">
                    <button type="submit" class="btn btn-primary" style="width:auto;">Simpan</button>
                </div>
            @endif
        </section>
    </form>
@endsection
