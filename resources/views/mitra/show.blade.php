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
                    &middot; KAE <span style="display:inline-block; padding:2px 8px; border-radius:6px; background:var(--accent-soft); color:var(--accent-ink); font-size:11px; font-weight:600; vertical-align:middle;">{{ \App\Models\User::kaeNameMap()[$mitra->kae_code] ?? $mitra->kae_code }}</span>
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

    <section style="display:grid; grid-template-columns:repeat(3, 1fr); gap:16px; margin-bottom:20px;">
        <div class="card">
            <div class="info-label" style="margin-bottom:10px;">Omset Bulan Ini ({{ $periodeLabel }})</div>
            <div style="font-size:24px; font-weight:700;" class="tnum">{{ $rp($omsetBulanIni) }}</div>
        </div>
        <div class="card">
            <div class="info-label" style="margin-bottom:10px;">Total Order Bulan Ini</div>
            <div style="font-size:24px; font-weight:700;" class="tnum">{{ $jumlahOrderBulanIni }}</div>
        </div>
        <div class="card">
            <div class="info-label" style="margin-bottom:10px;">Stabilitas (Q{{ $stabilitas['kuartal'] }} {{ $stabilitas['tahun'] }})</div>
            @php $stabColor = $stabilitas['stabilitas'] === 'Stabil' ? 'good' : ($stabilitas['stabilitas'] === 'Naik-turun' ? 'warn' : 'critical'); @endphp
            <div style="margin-top:4px;"><span class="chip chip-{{ $stabColor }}">{{ $stabilitas['stabilitas'] }}</span></div>
            <div style="font-size:11.5px; color:var(--ink-muted); margin-top:8px;">{{ $stabilitas['bln_aktif'] }} dari 3 bulan aktif bertransaksi</div>
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
            <div class="card-title" style="margin-bottom:14px;">
                Target {{ $periodeLabel }} &mdash; Segmen {{ $targetBulanIni->segmen }}
                <span class="chip" style="background:var(--accent-soft); color:var(--accent-ink); margin-left:6px;">Tier: {{ ucfirst($targetBulanIni->tier_dipakai) }}</span>
                @if ($targetBulanIni->kategori)
                    <span class="chip" style="margin-left:6px;">{{ $targetBulanIni->kategori }}</span>
                @endif
            </div>
            <div class="info-grid">
                <div>
                    <div class="info-label">Komit</div>
                    <div class="info-value tnum" style="{{ $targetBulanIni->tier_dipakai === 'komit' ? 'font-weight:700; color:var(--accent-ink);' : '' }}">{{ $rp($targetBulanIni->komit ?? 0) }}</div>
                </div>
                <div>
                    <div class="info-label">Target</div>
                    <div class="info-value tnum" style="{{ $targetBulanIni->tier_dipakai === 'target' ? 'font-weight:700; color:var(--accent-ink);' : '' }}">{{ $rp($targetBulanIni->target) }}</div>
                </div>
                <div>
                    <div class="info-label">Stretch</div>
                    <div class="info-value tnum" style="{{ $targetBulanIni->tier_dipakai === 'stretch' ? 'font-weight:700; color:var(--accent-ink);' : '' }}">{{ $rp($targetBulanIni->stretch ?? 0) }}</div>
                </div>
                <div>
                    <div class="info-label">Pencapaian (vs Tier Dipakai)</div>
                    <div class="info-value tnum">{{ $targetBulanIni->effectiveTarget() > 0 ? round($omsetBulanIni / $targetBulanIni->effectiveTarget() * 100, 1) : 0 }}%</div>
                </div>
            </div>
            @if ($targetBulanIni->keterangan)
                <div style="margin-top:14px; font-size:12.5px; color:var(--ink-muted);">
                    <strong style="color:var(--ink);">Keterangan:</strong> {{ $targetBulanIni->keterangan }}
                </div>
            @endif
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
                            <td style="font-size:12px; color:var(--ink-muted); max-width:280px; white-space:normal;">{{ $log->catatan ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" style="color:var(--ink-muted);">Belum ada follow-up untuk mitra ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card table-card" style="padding:0; margin-top:16px;">
        <div class="card-head" style="padding:18px 20px 0; margin-bottom:12px;">
            <div>
                <div class="card-title">Special Deal</div>
                <div class="card-hint">{{ $specialDeals->count() }} deal terakhir</div>
            </div>
            <a href="{{ route('special-deal.create', ['mitra_id' => $mitra->id]) }}" class="btn" style="width:auto;">+ Ajukan Deal</a>
        </div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>Segmen</th><th>Deskripsi</th><th>Target Kuartal</th><th>Periode</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse ($specialDeals as $deal)
                        <tr>
                            <td style="font-size:12px;">{{ $deal->segmen ?: '—' }}</td>
                            <td style="font-size:12.5px; color:var(--ink-muted); max-width:220px;">{{ \Illuminate\Support\Str::limit($deal->deskripsi, 70) }}</td>
                            <td class="tnum">{{ $deal->target_kuartal ? $rp($deal->target_kuartal) : '—' }}</td>
                            <td class="tnum" style="font-size:12px;">{{ $deal->periodeLabel() ?? '—' }}</td>
                            <td>
                                @switch($deal->status)
                                    @case('proses') <span class="chip chip-warn">Proses</span> @break
                                    @case('done') <span class="chip chip-good">Done</span> @break
                                    @case('batal') <span class="chip chip-critical">Batal</span> @break
                                @endswitch
                            </td>
                            <td>
                                <div style="display:flex; gap:6px;">
                                    <a href="{{ route('special-deal.edit', $deal) }}" class="link-action" style="color:var(--accent-ink); font-size:12.5px; font-weight:600; text-decoration:none; border:1px solid var(--line); border-radius:7px; padding:5px 10px;">Edit</a>
                                    <a href="{{ route('special-deal.mou', $deal) }}" class="link-action" style="color:var(--ink); font-size:12.5px; font-weight:600; text-decoration:none; border:1px solid var(--line); border-radius:7px; padding:5px 10px;">Cetak MOU</a>
                                    @if (auth()->user()->isAdmin())
                                        <form method="POST" action="{{ route('special-deal.destroy', $deal) }}" onsubmit="return confirm('Hapus special deal ini? Data tidak bisa dikembalikan.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger" style="width:auto; font-size:11.5px; padding:5px 10px;">Hapus</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" style="color:var(--ink-muted);">Belum ada special deal untuk mitra ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
