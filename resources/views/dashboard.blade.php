@extends('layouts.app')

@php
    $rp = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');
@endphp

@section('content')
    <div class="topbar" style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:22px; flex-wrap:wrap;">
        <div>
            <h1 class="display" style="font-size:24px;">Dashboard</h1>
            <div style="color:var(--ink-muted); font-size:13px; margin-top:4px;">
                Ringkasan performa {{ auth()->user()->isAdmin() ? 'semua mitra' : 'mitra kamu' }} &mdash; {{ $periodeLabel }}
            </div>
        </div>
    </div>

    @unless ($adaTargetBulanIni)
        <div class="alert-error" style="background:var(--warn-soft); color:var(--warn);">
            Target bulanan untuk {{ $periodeLabel }} belum di-import, jadi Achievement % dan status mitra belum bisa ditampilkan. KPI di bawah murni dari data order yang sudah masuk.
        </div>
    @endunless

    @if ($jumlahOrderBulanIni === 0)
        <div class="card" style="text-align:center; padding:40px 20px; color:var(--ink-muted);">
            Belum ada data order untuk {{ $periodeLabel }}.
            @if (auth()->user()->isAdmin())
                <br>Mulai dengan <a href="{{ route('import.index') }}" style="color:var(--accent-ink); font-weight:600;">import data harian</a>.
            @endif
        </div>
    @else
        @php $kpiCount = 3 + ($adaTargetBulanIni ? 1 : 0) + ($trendCard ? 1 : 0); @endphp
        <section style="display:grid; grid-template-columns:repeat({{ $kpiCount }}, 1fr); gap:16px; margin-bottom:20px;">
            <div class="card">
                <div class="info-label" style="margin-bottom:10px;">Omset Bulan Ini</div>
                <div style="font-size:25px; font-weight:700;" class="tnum">{{ $rp($totalOmsetBulanIni) }}</div>
                @if ($achievementPct !== null)
                    @php $achColor = $achievementPct >= 80 ? 'good' : ($achievementPct >= 60 ? 'warn' : 'critical'); @endphp
                    <div style="margin-top:10px;">
                        <span class="chip chip-{{ $achColor }}">{{ $achievementPct }}% dari target</span>
                    </div>
                    <div style="height:6px; border-radius:4px; background:var(--line); overflow:hidden; margin-top:10px;">
                        <div style="height:100%; border-radius:4px; background:var(--accent); width:{{ min($achievementPct, 100) }}%;"></div>
                    </div>
                @endif
            </div>
            <div class="card">
                <div class="info-label" style="margin-bottom:10px;">Jumlah Order</div>
                <div style="font-size:25px; font-weight:700;" class="tnum">{{ $jumlahOrderBulanIni }}</div>
            </div>
            <div class="card">
                <div class="info-label" style="margin-bottom:10px;">Mitra Aktif</div>
                <div style="font-size:25px; font-weight:700;" class="tnum">{{ $mitraAktifBulanIni }} <small style="font-size:13px; color:var(--ink-muted); font-weight:500;">/ {{ $totalMitra }}</small></div>
            </div>
            @if ($adaTargetBulanIni)
                <div class="card">
                    <div class="info-label" style="margin-bottom:10px;">Mitra Perlu Perhatian</div>
                    <div style="font-size:25px; font-weight:700;" class="tnum">{{ $mitraPerluPerhatian->count() }}</div>
                    @php
                        $kritis = $mitraPerluPerhatian->where('pct', '<', 60)->count();
                        $warning = $mitraPerluPerhatian->count() - $kritis;
                    @endphp
                    <div style="margin-top:10px;">
                        <span class="chip chip-critical">{{ $kritis }} kritis</span>
                        <span class="chip chip-warn" style="margin-left:6px;">{{ $warning }} warning</span>
                    </div>
                </div>
            @endif
            @if ($trendCard)
                <div class="card">
                    <div class="info-label" style="margin-bottom:10px;">{{ $trendCard['label'] }}</div>
                    <div style="font-size:25px; font-weight:700;" class="tnum">{{ $rp($trendCard['omset_ini']) }}</div>
                    @php $g = $trendCard['growth']; @endphp
                    <div style="margin-top:10px; display:flex; align-items:center; gap:5px;">
                        @if ($g === null)
                            <span style="font-size:12px; color:var(--ink-faint);">Tidak ada data pembanding</span>
                        @else
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="{{ $g >= 0 ? 'var(--good)' : 'var(--critical)' }}" stroke-width="2.5">
                                @if ($g >= 0)
                                    <path d="M6 15l6-6 6 6" stroke-linecap="round" stroke-linejoin="round"/>
                                @else
                                    <path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                                @endif
                            </svg>
                            <span class="tnum" style="font-weight:700; font-size:14px; color:{{ $g >= 0 ? 'var(--good)' : 'var(--critical)' }};">{{ $g >= 0 ? '+' : '' }}{{ $g }}%</span>
                        @endif
                    </div>
                </div>
            @endif
        </section>

        <section style="display:grid; grid-template-columns:1.4fr 1fr; gap:16px; margin-bottom:20px; align-items:start;">
            <div class="card">
                <div class="card-head">
                    <div class="card-title">Tren Omset Mingguan</div>
                    <div class="card-hint">{{ $periodeLabel }}</div>
                </div>
                @php $maxMinggu = $trenMingguan->max('total') ?: 1; @endphp
                <div style="display:flex; flex-direction:column; gap:12px;">
                    @for ($w = 1; $w <= 5; $w++)
                        @php $val = $trenMingguan[$w]->total ?? 0; @endphp
                        @if ($val > 0 || $w <= 4)
                            <div style="display:grid; grid-template-columns:36px 1fr 110px; align-items:center; gap:10px;">
                                <div style="font-size:12px; font-weight:700; color:var(--ink-muted);">W{{ $w }}</div>
                                <div style="height:8px; border-radius:4px; background:var(--line); overflow:hidden;">
                                    <div style="height:100%; border-radius:4px; background:var(--accent); width:{{ $maxMinggu ? round($val / $maxMinggu * 100) : 0 }}%;"></div>
                                </div>
                                <div class="tnum" style="font-size:12px; color:var(--ink-muted); text-align:right;">{{ $rp($val) }}</div>
                            </div>
                        @endif
                    @endfor
                </div>
            </div>

            <div class="card">
                <div class="card-head">
                    <div class="card-title">Omset per Brand</div>
                    <div class="card-hint">{{ $rp($totalItemOmset) }}</div>
                </div>
                <div style="display:flex; flex-direction:column; gap:13px;">
                    @forelse ($omsetPerBrand as $b)
                        @php $pct = $totalItemOmset > 0 ? round($b->total / $totalItemOmset * 100) : 0; @endphp
                        <div style="display:grid; grid-template-columns:84px 1fr 44px; align-items:center; gap:10px;">
                            <div style="font-size:12.5px; font-weight:600;">{{ $b->brand }}</div>
                            <div style="height:8px; border-radius:4px; background:var(--line); overflow:hidden;">
                                <div style="height:100%; border-radius:4px; background:var(--accent); width:{{ $pct }}%;"></div>
                            </div>
                            <div class="tnum" style="font-size:12px; color:var(--ink-muted); text-align:right;">{{ $pct }}%</div>
                        </div>
                    @empty
                        <div style="color:var(--ink-muted); font-size:12.5px;">Belum ada data.</div>
                    @endforelse
                </div>
            </div>
        </section>

        @if ($adaTargetBulanIni && $mitraPerluPerhatian->isNotEmpty())
            <section class="card table-card" style="padding:0; margin-bottom:20px;">
                <div class="card-head" style="padding:18px 20px 0; margin-bottom:12px;">
                    <div class="card-title">Mitra Perlu Perhatian</div>
                    <div class="card-hint">Diurutkan dari pencapaian terendah</div>
                </div>
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>Mitra</th><th>Segmen</th><th>Omset</th><th>Target</th><th>% vs Target</th><th>Status</th></tr></thead>
                        <tbody>
                            @foreach ($mitraPerluPerhatian as $m)
                                <tr>
                                    <td>
                                        <div style="font-weight:600;">{{ $m->nama }}</div>
                                        <div style="font-size:11.5px; color:var(--ink-muted);">{{ $m->kode_mitra }}</div>
                                    </td>
                                    <td>{{ $m->segmen }}</td>
                                    <td class="tnum">{{ $rp($m->omset) }}</td>
                                    <td class="tnum">{{ $rp($m->target) }}</td>
                                    <td class="tnum">{{ $m->pct }}%</td>
                                    <td>
                                        @if ($m->pct < 60)
                                            <span class="chip chip-critical">Kritis</span>
                                        @else
                                            <span class="chip chip-warn">Warning</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <section style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
            <div class="card table-card" style="padding:0;">
                <div class="card-head" style="padding:18px 20px 0; margin-bottom:12px;">
                    <div class="card-title">Top Mitra Bulan Ini</div>
                </div>
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>Mitra</th><th>Omset</th></tr></thead>
                        <tbody>
                            @forelse ($topMitra as $m)
                                <tr>
                                    <td>
                                        <div style="font-weight:600;">{{ $m->nama }}</div>
                                        <div style="font-size:11.5px; color:var(--ink-muted);">{{ $m->kode_mitra }}</div>
                                    </td>
                                    <td class="tnum">{{ $rp($m->total) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" style="color:var(--ink-muted);">Belum ada data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card table-card" style="padding:0;">
                <div class="card-head" style="padding:18px 20px 0; margin-bottom:12px;">
                    <div class="card-title">Order Terbaru</div>
                </div>
                <div class="table-scroll">
                    <table>
                        <thead><tr><th>Tanggal</th><th>Mitra</th><th>Total</th></tr></thead>
                        <tbody>
                            @forelse ($orderTerbaru as $o)
                                <tr>
                                    <td class="tnum">{{ $o->tanggal_order->format('d/m/Y') }}</td>
                                    <td>{{ $o->mitra->nama ?? '—' }}</td>
                                    <td class="tnum">{{ $rp($o->total_transaksi) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" style="color:var(--ink-muted);">Belum ada data.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    @endif
@endsection
