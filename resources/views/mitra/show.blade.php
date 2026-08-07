@extends('layouts.app')

@php
    $rp = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');
@endphp

@section('content')
    <a href="{{ route('mitra.index') }}" style="display:inline-flex; align-items:center; gap:6px; font-size:12.5px; color:var(--ink-muted); text-decoration:none; margin-bottom:16px;">
        &larr; Kembali
    </a>

    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:18px; flex-wrap:wrap;">
        <div>
            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                <h1 class="display" style="font-size:22px;">{{ $mitra->nama }}</h1>
                @if ($mitra->status === 'aktif')
                    <span class="chip chip-good">Aktif</span>
                @else
                    <span class="chip chip-critical">Nonaktif</span>
                @endif
            </div>
            <div style="color:var(--ink-muted); font-size:13px; margin-top:6px;">
                {{ $mitra->kode_mitra }}
                @if ($mitra->kae_code)
                    &middot; KAE <span class="kae-tag" style="display:inline-flex; align-items:center; justify-content:center; width:20px; height:20px; border-radius:6px; background:var(--accent-soft); color:var(--accent-ink); font-size:10.5px; font-weight:700; vertical-align:middle;">{{ $mitra->kae_code }}</span>
                @endif
            </div>
        </div>
        @if (auth()->user()->isAdmin())
            <a href="{{ route('mitra.edit', $mitra) }}" class="btn" style="width:auto;">Edit Mitra</a>
        @endif
    </div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif

    <section style="display:grid; grid-template-columns:repeat(2, 1fr); gap:16px; margin-bottom:20px;">
        <div class="card">
            <div class="info-label" style="margin-bottom:10px;">Omset Bulan Ini ({{ $periodeLabel }})</div>
            <div style="font-size:24px; font-weight:700;" class="tnum">{{ $rp($omsetBulanIni) }}</div>
        </div>
        <div class="card">
            <div class="info-label" style="margin-bottom:10px;">Total Order Bulan Ini</div>
            <div style="font-size:24px; font-weight:700;" class="tnum">{{ $jumlahOrderBulanIni }}</div>
        </div>
    </section>

    <section class="card" style="margin-bottom:20px;">
        <div class="info-grid">
            <div>
                <div class="info-label">Alamat</div>
                <div class="info-value">{{ $mitra->alamat ?: '—' }}</div>
            </div>
            <div>
                <div class="info-label">No. HP / WA</div>
                <div class="info-value tnum">{{ $mitra->no_hp ?: '—' }}</div>
            </div>
            <div>
                <div class="info-label">Provinsi / Kota</div>
                <div class="info-value">{{ collect([$mitra->provinsi, $mitra->kota])->filter()->implode(', ') ?: '—' }}</div>
            </div>
            <div>
                <div class="info-label">Kecamatan / Desa</div>
                <div class="info-value">{{ collect([$mitra->kecamatan, $mitra->desa])->filter()->implode(', ') ?: '—' }}</div>
            </div>
        </div>
    </section>

    <section class="card table-card" style="padding:0;">
        <div class="card-head" style="padding:18px 20px 0; margin-bottom:12px;">
            <div class="card-title">Histori Order</div>
            <div class="card-hint">{{ $historiOrder->count() }} order terakhir</div>
        </div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>Tanggal</th><th>No Order</th><th>Total</th><th>Pembayaran</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($historiOrder as $o)
                        <tr>
                            <td class="tnum">{{ $o->tanggal_order->format('d/m/Y') }}</td>
                            <td class="tnum">{{ $o->no_order }}</td>
                            <td class="tnum">{{ $rp($o->total_transaksi) }}</td>
                            <td>
                                @if ($o->status_pembayaran === 'Lunas')
                                    <span class="chip chip-good">Lunas</span>
                                @elseif ($o->status_pembayaran)
                                    <span class="chip chip-warn">{{ $o->status_pembayaran }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $o->status ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" style="color:var(--ink-muted);">Belum ada order.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($targetBulanIni)
        <section class="card" style="margin-top:16px;">
            <div class="card-title" style="margin-bottom:14px;">Target {{ $periodeLabel }} &mdash; Segmen {{ $targetBulanIni->segmen }}</div>
            <div class="info-grid">
                <div>
                    <div class="info-label">Komit</div>
                    <div class="info-value tnum">{{ $rp($targetBulanIni->komit ?? 0) }}</div>
                </div>
                <div>
                    <div class="info-label">Target</div>
                    <div class="info-value tnum">{{ $rp($targetBulanIni->target) }}</div>
                </div>
                <div>
                    <div class="info-label">Stretch</div>
                    <div class="info-value tnum">{{ $rp($targetBulanIni->stretch ?? 0) }}</div>
                </div>
                <div>
                    <div class="info-label">Pencapaian</div>
                    <div class="info-value tnum">{{ $targetBulanIni->target > 0 ? round($omsetBulanIni / $targetBulanIni->target * 100, 1) : 0 }}%</div>
                </div>
            </div>
        </section>
    @else
        <div class="card" style="margin-top:16px; color:var(--ink-muted); font-size:12.5px;">
            Belum ada Target Bulanan untuk {{ $periodeLabel }} pada mitra ini.
        </div>
    @endif

    <section class="card table-card" style="padding:0; margin-top:16px;">
        <div class="card-head" style="padding:18px 20px 0; margin-bottom:12px;">
            <div>
                <div class="card-title">Follow-up Log</div>
                <div class="card-hint">{{ $followupLogs->count() }} catatan terakhir</div>
            </div>
            <a href="{{ route('followup.create', ['mitra_id' => $mitra->id]) }}" class="btn" style="width:auto;">+ Catat Follow-up</a>
        </div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>Tanggal</th><th>KAE</th><th>Status FU</th><th>Status Belanja</th><th>Kendala</th><th>Catatan</th></tr></thead>
                <tbody>
                    @forelse ($followupLogs as $log)
                        <tr>
                            <td class="tnum">{{ $log->tanggal_fu->format('d/m/Y') }} <span style="color:var(--ink-faint);">({{ $log->minggu }})</span></td>
                            <td>{{ $log->kae->name ?? '—' }}</td>
                            <td>
                                @if ($log->status_followup === 'Terhubung')
                                    <span class="chip chip-good">Terhubung</span>
                                @else
                                    <span class="chip chip-critical">Tidak ada respon</span>
                                @endif
                            </td>
                            <td>
                                @if ($log->status_belanja === 'Belanja Penuh')
                                    <span class="chip chip-good">Belanja Penuh</span>
                                @elseif ($log->status_belanja === 'Belanja Sebagian')
                                    <span class="chip chip-warn">Sebagian</span>
                                @else
                                    <span class="chip chip-critical">Belum Belanja</span>
                                @endif
                            </td>
                            <td style="font-size:12px; color:var(--ink-muted);">{{ $log->alasan_kendala ?: '—' }}</td>
                            <td style="font-size:12px; color:var(--ink-muted); max-width:220px;">{{ \Illuminate\Support\Str::limit($log->catatan, 60) ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" style="color:var(--ink-muted);">Belum ada follow-up untuk mitra ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
