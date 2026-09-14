@extends('layouts.app')

@php
    $skorChip = fn (?int $s) => $s === null ? 'chip-neutral' : ($s >= 4 ? 'chip-good' : ($s == 3 ? 'chip-warn' : 'chip-critical'));
    $tipeMitraChip = fn (?string $t) => $t === 'Prioritas' ? 'chip-highlight' : 'chip-neutral';
@endphp

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:4px;">
        <h1 class="display" style="font-size:24px;">Profiling Mitra</h1>
        <a href="{{ route('growth-specialist.profiling-mitra.create') }}" class="btn btn-primary" style="width:auto; padding:9px 18px; text-decoration:none; display:inline-block;">+ Input Mitra</a>
    </div>
    <div class="card-hint" style="margin-bottom:16px;">Master Database &mdash; {{ $totalMitra }} mitra sudah diinput datanya.</div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif

    <form method="GET" action="{{ route('growth-specialist.profiling-mitra') }}" class="card" style="margin-bottom:16px;">
        <div class="field-row" style="align-items:flex-end;">
            <div class="field" style="flex:2;">
                <label>Cari Nama Mitra</label>
                <input type="text" name="cari" value="{{ $filters['cari'] ?? '' }}" placeholder="Ketik nama mitra...">
            </div>
            <div class="field" style="flex:1;">
                <label>Tipe Mitra</label>
                <select name="tipe_mitra">
                    <option value="">— semua —</option>
                    <option value="Prioritas" @selected(($filters['tipe_mitra'] ?? '') === 'Prioritas')>Prioritas</option>
                    <option value="Standar" @selected(($filters['tipe_mitra'] ?? '') === 'Standar')>Standar</option>
                    <option value="belum" @selected(($filters['tipe_mitra'] ?? '') === 'belum')>Belum Lengkap</option>
                </select>
            </div>
            <div class="field" style="flex:1;">
                <label>KAE RO</label>
                <select name="kae_code">
                    <option value="">— semua —</option>
                    @foreach ($kaeOptions as $code => $nama)
                        <option value="{{ $code }}" @selected(($filters['kae_code'] ?? '') === $code)>{{ $nama }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field" style="flex:1;">
                <label>Status LMS</label>
                <select name="lms_status">
                    <option value="">— semua —</option>
                    <option value="Done" @selected(($filters['lms_status'] ?? '') === 'Done')>Done</option>
                    <option value="On Progress" @selected(($filters['lms_status'] ?? '') === 'On Progress')>On Progress</option>
                    <option value="belum" @selected(($filters['lms_status'] ?? '') === 'belum')>Belum Diisi</option>
                </select>
            </div>
            <div class="field" style="flex:none;">
                <button type="submit" class="btn btn-primary" style="width:auto; padding:9px 18px;">Cari</button>
            </div>
            <div class="field" style="flex:none;">
                <a href="{{ route('growth-specialist.profiling-mitra') }}" class="btn" style="width:auto; padding:9px 18px; text-decoration:none; display:inline-block;">Reset</a>
            </div>
        </div>
    </form>

    <section class="card table-card reveal-on-scroll" style="padding:0;">
        <div class="card-head" style="padding:18px 20px 0; margin-bottom:12px;">
            <div class="card-title">Master Database</div>
            <div class="card-hint">{{ $rowsPage->total() }} mitra cocok dengan pencarian</div>
        </div>
        <style>
            .gs-master-table thead tr:first-child th {
                border-right: 1px solid rgba(0,0,0,0.18);
                box-shadow: inset -2px 0 2px -1px rgba(0,0,0,0.15), inset 1px 0 0 rgba(255,255,255,0.5);
            }
            .gs-master-table thead tr:first-child th:last-child { border-right: none; box-shadow: none; }
            .gs-master-table :is(th, td):nth-child(7),
            .gs-master-table :is(th, td):nth-child(16),
            .gs-master-table :is(th, td):nth-child(19),
            .gs-master-table :is(th, td):nth-child(24),
            .gs-master-table :is(th, td):nth-child(27),
            .gs-master-table :is(th, td):nth-child(28),
            .gs-master-table :is(th, td):nth-child(29) {
                border-right: 1px solid rgba(0,0,0,0.18);
                box-shadow: inset -2px 0 2px -1px rgba(0,0,0,0.15), inset 1px 0 0 rgba(255,255,255,0.5);
            }
            .gs-sect-a { background: var(--accent-soft); }
            .gs-sect-b { background: var(--good-soft); }
            .gs-sect-c { background: var(--warn-soft); }
            .gs-sect-d { background: var(--highlight-soft); }
        </style>
        <div class="table-scroll" style="max-height:none; overflow-y:visible;">
            <table class="gs-master-table">
                <thead>
                    <tr>
                        <th colspan="7" class="gs-sect-a" style="text-align:center;">Identitas Mitra</th>
                        <th colspan="9" class="gs-sect-b" style="text-align:center;">Kekuatan Finansial &amp; Operasional</th>
                        <th colspan="3" class="gs-sect-c" style="text-align:center;">Channel Fokus</th>
                        <th colspan="5" class="gs-sect-d" style="text-align:center;">Klasifikasi Mitra</th>
                        <th colspan="3" class="gs-sect-a" style="text-align:center;">KPI Awal</th>
                        <th class="gs-sect-b" style="text-align:center;">Integrasi LMS</th>
                        <th class="gs-sect-c" style="text-align:center;">Status</th>
                        <th></th>
                    </tr>
                    <tr>
                        <th>Nama Mitra</th><th>KAE RO</th><th>Channel</th><th>Status</th><th>No. WA</th><th>Domisili/Kota</th><th>Tgl Onboarding</th>
                        <th>Modal Bisnis</th><th>Modal SRN</th><th>Cost</th><th>% Op. Cost</th><th>Toleransi Cashflow</th><th>Tim/Sendiri</th><th>Pengalaman Jualan</th><th>Platform Jualan</th><th>Jam Aktif</th>
                        <th>Tipe Channel</th><th>Channel Fokus 1</th><th>Channel Fokus 2</th>
                        <th>Motivasi</th><th>Kemampuan</th><th>Keaktifan</th><th>Tipe Mitra</th><th>Deadline Setup Channel</th>
                        <th>Target Traffic</th><th>Target Leads</th><th>Deadline Closing</th>
                        <th>Status LMS</th>
                        <th>Catatan</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rowsPage as $r)
                        @php $m = $r['mitra']; $p = $r['profil']; @endphp
                        <tr>
                            <td style="font-weight:600;">{{ $m->nama }}<div style="font-size:11px; color:var(--ink-faint); font-weight:400;">{{ $m->kode_mitra }}</div></td>
                            <td>{{ $r['kae_nama'] ?? '—' }}</td>
                            <td>@if($r['channel'])<span class="chip chip-neutral">{{ $r['channel'] }}</span>@else — @endif</td>
                            <td>@if($p->status)<span class="chip chip-accent">{{ $p->status }}</span>@else — @endif</td>
                            <td>{{ $p->no_wa ?? '—' }}</td>
                            <td>{{ $p->domisili_kota ?? '—' }}</td>
                            <td>{{ $p->tanggal_onboarding?->format('d/m/Y') ?? '—' }}</td>
                            <td class="tnum">{{ $p->modal_bisnis !== null ? 'Rp'.number_format((float) $p->modal_bisnis, 0, ',', '.') : '—' }}</td>
                            <td class="tnum">{{ $p->modal_srn !== null ? 'Rp'.number_format((float) $p->modal_srn, 0, ',', '.') : '—' }}</td>
                            <td class="tnum">{{ $p->cost !== null ? 'Rp'.number_format((float) $p->cost, 0, ',', '.') : '—' }}</td>
                            <td class="tnum">{{ $r['pct'] !== null ? $r['pct'].'%' : '—' }}</td>
                            <td>@if($r['toleransi_cashflow'])<span class="chip chip-neutral">{{ $r['toleransi_cashflow'] }}</span>@else — @endif</td>
                            <td>{{ $p->tim_sendiri ?? '—' }}</td>
                            <td>{{ $p->pengalaman_jualan ?? '—' }}</td>
                            <td>{{ $p->platform_jualan ? implode(', ', $p->platform_jualan) : '—' }}</td>
                            <td>{{ $p->jam_aktif ?? '—' }}</td>
                            <td>@if($p->tipe_channel)<span class="chip chip-neutral">{{ $p->tipe_channel }}</span>@else — @endif</td>
                            <td>{{ $p->channel_fokus_1 ? implode(', ', $p->channel_fokus_1) : '—' }}</td>
                            <td>{{ $p->channel_fokus_2 ? implode(', ', $p->channel_fokus_2) : '—' }}</td>
                            <td>@if($p->motivasi)<span class="chip {{ $skorChip($p->motivasi) }}">{{ $p->motivasi }}</span>@else — @endif</td>
                            <td>@if($p->kemampuan)<span class="chip {{ $skorChip($p->kemampuan) }}">{{ $p->kemampuan }}</span>@else — @endif</td>
                            <td>@if($p->keaktifan)<span class="chip {{ $skorChip($p->keaktifan) }}">{{ $p->keaktifan }}</span>@else — @endif</td>
                            <td>@if($r['tipe_mitra'])<span class="chip {{ $tipeMitraChip($r['tipe_mitra']) }}">{{ $r['tipe_mitra'] }}</span>@else — @endif</td>
                            <td>{{ $p->deadline_setup_channel?->format('d/m/Y') ?? '—' }}</td>
                            <td class="tnum">{{ $p->target_traffic ?? '—' }}</td>
                            <td class="tnum">{{ $p->target_leads ?? '—' }}</td>
                            <td>{{ $r['deadline_closing']?->format('d/m/Y') ?? '—' }}</td>
                            <td>@if($p->lms_status)<span class="chip {{ $p->lms_status === 'Done' ? 'chip-good' : 'chip-warn' }}">{{ $p->lms_status }}</span>@else — @endif</td>
                            <td style="max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="{{ $p->catatan }}">{{ $p->catatan ? \Illuminate\Support\Str::of($p->catatan)->replace("\n", ' ')->limit(60) : '—' }}</td>
                            <td style="white-space:nowrap;">
                                <a href="{{ route('growth-specialist.profiling-mitra.edit', $m) }}" class="btn" style="width:auto; font-size:11.5px; padding:5px 9px; text-decoration:none; display:inline-block;">Edit</a>
                                <a href="{{ route('growth-specialist.profiling-mitra.kartu', $m) }}" class="btn" style="width:auto; font-size:11.5px; padding:5px 9px; text-decoration:none; display:inline-block;" target="_blank">Kartu</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="30" style="text-align:center; color:var(--ink-muted); padding:24px;">Gak ada mitra yang cocok.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div style="margin-top:16px;">
        {{ $rowsPage->links() }}
    </div>
@endsection
