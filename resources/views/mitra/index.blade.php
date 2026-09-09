@extends('layouts.app')

@php
    $rp = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');
@endphp

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:22px; flex-wrap:wrap;">
        <div>
            <h1 class="display" style="font-size:24px;">Data Mitra</h1>
            <div style="color:var(--ink-muted); font-size:13px; margin-top:4px;">
                {{ $mitraList->total() }} mitra {{ auth()->user()->canViewAll() ? '' : 'kamu' }}
            </div>
        </div>
        @if (auth()->user()->isAdmin())
            <a href="{{ route('mitra.create') }}" class="btn btn-primary" style="width:auto;">+ Tambah Mitra</a>
        @endif
    </div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif

    <form method="GET" action="{{ route('mitra.index') }}" class="field-row" style="align-items:flex-end;">
        <div class="field" style="margin-bottom:0; flex:1; min-width:180px;">
            <label>Cari</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Nama atau kode mitra...">
        </div>
        @if (auth()->user()->canViewAll())
            <div class="field" style="margin-bottom:0;">
                <label>KAE</label>
                <select name="kae_code" class="select-pill" onchange="this.form.submit()">
                    <option value="">Semua KAE</option>
                    @foreach ($kaeOptions as $kae)
                        <option value="{{ $kae->kae_code }}" {{ request('kae_code') === $kae->kae_code ? 'selected' : '' }}>{{ $kae->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="field" style="margin-bottom:0;">
            <label>Status</label>
            <select name="status" class="select-pill" onchange="this.form.submit()">
                <option value="">Semua Status</option>
                <option value="aktif" {{ request('status') === 'aktif' ? 'selected' : '' }}>Aktif</option>
                <option value="nonaktif" {{ request('status') === 'nonaktif' ? 'selected' : '' }}>Nonaktif</option>
            </select>
        </div>
        <div class="field" style="margin-bottom:0;">
            <label>Stabilitas</label>
            <select name="stabilitas" class="select-pill" onchange="this.form.submit()">
                <option value="">Semua</option>
                <option value="Stabil" {{ request('stabilitas') === 'Stabil' ? 'selected' : '' }}>Stabil</option>
                <option value="Naik-turun" {{ request('stabilitas') === 'Naik-turun' ? 'selected' : '' }}>Naik-turun</option>
                <option value="Pasif" {{ request('stabilitas') === 'Pasif' ? 'selected' : '' }}>Pasif</option>
            </select>
        </div>
        <div class="field" style="margin-bottom:0;">
            <label style="display:flex; align-items:center; gap:6px; cursor:pointer;">
                <input type="checkbox" name="omset_nol" value="1" onchange="this.form.submit()" {{ request()->boolean('omset_nol') ? 'checked' : '' }}>
                Omset Bulan Ini = 0
            </label>
        </div>
        <button type="submit" class="btn" style="width:auto;">Cari</button>
        @if (request('q') || request('kae_code') || request('status') || request('stabilitas') || request('omset_nol'))
            <a href="{{ route('mitra.index') }}" class="btn" style="width:auto;">Reset</a>
        @endif
    </form>

    <section class="card table-card" style="padding:0; margin-top:16px;">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Mitra</th><th>KAE</th><th>Status</th><th>Stabilitas</th>
                        <th>{{ $blnAktifLabel }}</th><th>Omset Bulan Ini</th><th>{{ $lmLabel }}</th><th>% Growth</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($mitraList as $m)
                        @php $stab = $stabilitasByMitra[$m->id] ?? ['bln_aktif' => 0, 'stabilitas' => 'Pasif']; @endphp
                        <tr>
                            <td>
                                <div style="font-weight:600;">{{ $m->nama }}</div>
                                <div style="font-size:11.5px; color:var(--ink-muted);">{{ $m->kode_mitra }}</div>
                            </td>
                            <td>
                                @if ($m->kae_code)
                                    <span style="display:inline-block; padding:2px 8px; border-radius:6px; background:var(--accent-soft); color:var(--accent-ink); font-size:11px; font-weight:600;">{{ \App\Models\User::kaeNameMap()[$m->kae_code] ?? $m->kae_code }}</span>
                                @else
                                    <span style="color:var(--ink-faint);">—</span>
                                @endif
                            </td>
                            <td>
                                @if ($m->status === 'aktif')
                                    <span class="chip chip-good">Aktif</span>
                                @else
                                    <span class="chip chip-critical">Nonaktif</span>
                                @endif
                            </td>
                            <td>
                                @php $stabColor = $stab['stabilitas'] === 'Stabil' ? 'good' : ($stab['stabilitas'] === 'Naik-turun' ? 'warn' : 'critical'); @endphp
                                <span class="chip chip-{{ $stabColor }}">{{ $stab['stabilitas'] }}</span>
                            </td>
                            <td class="tnum">{{ $stab['bln_aktif'] }}/3</td>
                            <td class="tnum">{{ $rp($m->omset_bulan_ini ?? 0) }}</td>
                            <td class="tnum" style="color:var(--ink-muted);">{{ $rp($m->omset_bulan_lalu ?? 0) }}</td>
                            <td class="tnum">
                                @php
                                    $lm = (float) ($m->omset_bulan_lalu ?? 0);
                                    $ini = (float) ($m->omset_bulan_ini ?? 0);
                                    $growth = $lm > 0 ? round((($ini - $lm) / $lm) * 100, 1) : null;
                                @endphp
                                @if ($growth === null)
                                    <span style="color:var(--ink-faint); font-size:12px;">—</span>
                                @else
                                    <span style="color:{{ $growth >= 0 ? 'var(--good)' : 'var(--critical)' }}; font-weight:600;">{{ $growth >= 0 ? '+' : '' }}{{ $growth }}%</span>
                                @endif
                            </td>
                            <td style="white-space:nowrap;"><a href="{{ route('mitra.show', $m) }}" class="link-action" style="color:var(--accent-ink); font-size:12.5px; font-weight:600; text-decoration:none; border:1px solid var(--line); border-radius:7px; padding:5px 10px; white-space:nowrap; display:inline-block;">Lihat detail</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" style="color:var(--ink-muted);">Belum ada mitra.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div style="margin-top:16px;">{{ $mitraList->links() }}</div>
@endsection
