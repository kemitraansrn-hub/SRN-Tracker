@extends('layouts.app')

@php
    $rp = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');
    $segmenLabel = fn ($s) => $s === 'RTP (ROAD TO PARETO)' ? 'RTP (Road To Pareto)' : ucwords(strtolower($s));
@endphp

@section('content')
    <h1 class="display" style="font-size:24px;">Forecast</h1>
    <div style="color:var(--ink-muted); font-size:13px; margin-top:4px; margin-bottom:24px;">
        {{ $periodeLabel }} &middot; rencana RO (repeat order) yang diinput manual, dijumlah otomatis jadi Best Estimate Pencapaian per segmen. Untuk Pareto &amp; RTP, begitu mitranya beneran belanja, Plan RO itu otomatis tidak ditambahkan lagi ke Best Estimate (supaya tidak dobel hitung dengan Ach) &mdash; cek kolom Realisasi &amp; Status di tabel Daftar Plan RO.
    </div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    <form method="GET" action="{{ route('forecast.index') }}" class="field-row" style="align-items:flex-end; margin-bottom:16px;">
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

    @if ($bestEstimateVsCompany)
        <div class="card" style="max-width:360px; margin-bottom:20px;">
            <div class="info-label" style="margin-bottom:10px;">Best Estimate vs Target Perusahaan</div>
            <div style="font-size:22px; font-weight:700;" class="tnum">{{ $rp($bestEstimateVsCompany['best_estimate']) }}</div>
            <div style="font-size:11.5px; color:var(--ink-muted); margin-top:2px;">dari target {{ $rp($bestEstimateVsCompany['target']) }} / bulan</div>
            <div style="height:6px; border-radius:4px; background:var(--line); overflow:hidden; margin-top:10px;">
                <div style="height:100%; border-radius:4px; background:var(--accent); width:{{ min($bestEstimateVsCompany['pct'], 100) }}%;"></div>
            </div>
            <div style="margin-top:10px;">
                @php $c = $bestEstimateVsCompany['pct'] >= 100 ? 'good' : ($bestEstimateVsCompany['pct'] >= 70 ? 'warn' : 'critical'); @endphp
                <span class="chip chip-{{ $c }}">{{ $bestEstimateVsCompany['pct'] }}%</span>
            </div>
        </div>
    @endif

    <section class="card table-card reveal-on-scroll" style="padding:0; margin-bottom:20px;">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Segmen</th><th>Target</th><th>Ach Saat Ini</th><th>Total Plan RO</th>
                        <th>Best Estimate Pencapaian</th><th>Best Estimate %</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($summary as $s)
                        <tr>
                            <td>{{ $segmenLabel($s['segmen']) }}</td>
                            <td class="tnum">{{ $s['target'] > 0 ? $rp($s['target']) : '—' }}</td>
                            <td class="tnum">{{ $rp($s['ach']) }}</td>
                            <td class="tnum">{{ $s['total_plan_ro'] > 0 ? $rp($s['total_plan_ro']) : '—' }}</td>
                            <td class="tnum" style="font-weight:700;">{{ $rp($s['best_estimate']) }}</td>
                            <td class="tnum">
                                @if ($s['best_estimate_pct'] === null)
                                    —
                                @else
                                    @php $c = $s['best_estimate_pct'] >= 100 ? 'good' : ($s['best_estimate_pct'] >= 70 ? 'warn' : 'critical'); @endphp
                                    <span class="chip chip-{{ $c }}">{{ $s['best_estimate_pct'] }}%</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    <tr style="font-weight:700; background:var(--surface-alt);">
                        <td>{{ $totalRow['segmen'] }}</td>
                        <td class="tnum">{{ $rp($totalRow['target']) }}</td>
                        <td class="tnum">{{ $rp($totalRow['ach']) }}</td>
                        <td class="tnum">{{ $rp($totalRow['total_plan_ro']) }}</td>
                        <td class="tnum">{{ $rp($totalRow['best_estimate']) }}</td>
                        <td class="tnum">
                            @if ($totalRow['best_estimate_pct'] === null)
                                —
                            @else
                                @php $c = $totalRow['best_estimate_pct'] >= 100 ? 'good' : ($totalRow['best_estimate_pct'] >= 70 ? 'warn' : 'critical'); @endphp
                                <span class="chip chip-{{ $c }}">{{ $totalRow['best_estimate_pct'] }}%</span>
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

        <button type="button" class="btn" style="width:auto; margin-bottom:12px;" onclick="const d = document.getElementById('tambah-plan-ro'); d.style.display = d.style.display === 'none' ? '' : 'none';">+ Tambah Plan RO</button>

        <form method="POST" action="{{ route('forecast.store') }}" class="card" id="tambah-plan-ro" style="display:none; max-width:640px; margin-bottom:20px;">
            @csrf
            <input type="hidden" name="bulan" value="{{ $bulan }}">
            <input type="hidden" name="tahun" value="{{ $tahun }}">
            <div style="font-weight:700; margin-bottom:14px;">Tambah Plan RO</div>

            <div class="field-row">
                <div class="field" style="flex:1;">
                    <label>Segmen</label>
                    <select name="segmen" id="segmenSelect" onchange="toggleMitraField()">
                        @foreach ($segmenList as $seg)
                            <option value="{{ $seg }}">{{ $segmenLabel($seg) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="flex:1;" id="mitraField">
                    <label>Mitra</label>
                    <select name="mitra_id" id="mitraSelect">
                        <option value="">— pilih mitra —</option>
                    </select>
                </div>
            </div>

            <div class="field-row">
                <div class="field" style="flex:1;">
                    <label>Tgl Plan RO</label>
                    <input type="date" name="tanggal_plan_ro" required>
                </div>
                <div class="field" style="flex:1;">
                    <label>Plan RO (Rp)</label>
                    <input type="number" name="plan_ro" min="0" placeholder="mis. 5000000" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="width:auto; margin-top:8px;">Simpan</button>
        </form>

        <script>
            const mitraOptionsBySegmen = @json($mitraOptionsBySegmen);

            function toggleMitraField() {
                const segmen = document.getElementById('segmenSelect').value;

                const select = document.getElementById('mitraSelect');
                select.innerHTML = '<option value="">— pilih mitra —</option>';
                (mitraOptionsBySegmen[segmen] || []).forEach(function (m) {
                    const opt = document.createElement('option');
                    opt.value = m.id;
                    opt.textContent = m.nama;
                    select.appendChild(opt);
                });
            }
            toggleMitraField();
        </script>

    <section class="card table-card reveal-on-scroll" style="padding:0;">
        <div class="card-head" style="padding:18px 20px 0; margin-bottom:12px;">
            <div class="card-title">Daftar Plan RO</div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Segmen</th><th>Nama Mitra</th><th>Tgl Plan RO</th><th>Plan RO</th><th>Realisasi</th><th>Tgl Realisasi</th><th>Status</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entries as $e)
                        <tr>
                            <td>{{ $segmenLabel($e->segmen) }}</td>
                            <td>{{ $e->mitra->nama ?? '—' }}</td>
                            <td class="tnum">{{ $e->tanggal_plan_ro->format('d/m/Y') }}</td>
                            <td class="tnum">{{ $rp($e->plan_ro) }}</td>
                            <td class="tnum" style="color:var(--ink-muted);">{{ $e->realisasi !== null ? $rp($e->realisasi) : '—' }}</td>
                            <td style="font-size:12px; color:var(--ink-muted);">{{ $e->tanggal_realisasi->isNotEmpty() ? $e->tanggal_realisasi->map(fn ($d) => \Carbon\Carbon::parse($d)->format('d/m'))->implode(', ') : '—' }}</td>
                            <td>
                                @if ($e->tercapai === null)
                                    <span style="color:var(--ink-faint); font-size:12px;">—</span>
                                @elseif ($e->tercapai)
                                    <span class="chip chip-good">Tercapai</span>
                                @else
                                    <span class="chip chip-critical">Belum</span>
                                @endif
                            </td>
                            <td>
                                @if (auth()->user()->hasAdminAccess() || $e->created_by === auth()->id())
                                    <form method="POST" action="{{ route('forecast.destroy', $e) }}" onsubmit="return confirm('Hapus Plan RO ini?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn" style="width:auto; font-size:11.5px; padding:5px 10px; color:var(--critical);">Hapus</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" style="color:var(--ink-muted);">Belum ada Plan RO untuk {{ $periodeLabel }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @include('partials.reveal-on-scroll')
@endsection
