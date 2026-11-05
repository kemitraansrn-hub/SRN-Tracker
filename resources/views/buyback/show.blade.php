@extends('layouts.app')

@php
    $rp = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');
    $r = $buybackRequest;
@endphp

@section('content')
    <a href="{{ route('buyback.index') }}" style="display:inline-block; margin-bottom:12px; font-size:13px; color:var(--ink-muted); text-decoration:none;">&larr; Kembali ke daftar</a>

    <div style="display:flex; flex-wrap:wrap; justify-content:space-between; align-items:flex-start; gap:12px; margin-bottom:20px;">
        <div>
            <h1 class="display" style="font-size:24px;">Riwayat Pengajuan Buy Back</h1>
            <div style="color:var(--ink-muted); font-size:13px; margin-top:4px;">
                {{ $r->mitra->nama ?? '—' }} ({{ $r->mitra->kode_mitra ?? '—' }}) &middot; diajukan {{ $r->created_at->translatedFormat('d M Y') }} oleh {{ $r->creator->name ?? '—' }}
            </div>
        </div>
        @if ($r->isApproved())
            <span class="chip chip-good">Approved</span>
        @elseif ($r->isHeadApproved())
            <span class="chip chip-warn">Menunggu Finance</span>
        @else
            <span class="chip chip-warn">On Check</span>
        @endif
    </div>

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:12px; margin-bottom:20px;">
        @foreach ([
            ['AOV (rata-rata order YTD)', $rp($r->aov), 'var(--ink)'],
            ['Tingkat Penyusutan', rtrim(rtrim(number_format($r->tingkat_penyusutan, 2, ',', '.'), '0'), ',').'%', 'var(--ink)'],
            ['Total Nilai Beli', $rp($r->grand_nilai_beli), 'var(--ink)'],
            ['Total Nilai Penyusutan', $rp($r->grand_nilai_penyusutan), 'var(--critical)'],
            ['Total Nilai Buy Back', $rp($r->grand_nilai_buyback), 'var(--good)'],
        ] as [$label, $value, $color])
            <div class="card" style="padding:14px 16px;">
                <div style="font-size:11.5px; color:var(--ink-muted); font-weight:600;">{{ $label }}</div>
                <div class="tnum" style="font-size:19px; font-weight:700; color:{{ $color }}; margin-top:4px;">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    <section class="card table-card" style="padding:0; margin-bottom:20px;">
        <div class="card-head" style="padding:16px;">
            <div class="card-title">Rincian Produk</div>
            <div class="card-hint">Nilai Buy Back = Nilai Beli &times; (1 &minus; Tingkat Penyusutan)<sup>Umur Produk (bulan)</sup>, dihitung per produk saat pengajuan dibuat.</div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Produk</th><th>Harga</th><th>Qty</th><th>Nilai Beli</th><th>ED</th><th>Umur (bulan)</th><th>Nilai Penyusutan</th><th>Nilai Buy Back</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($r->items as $item)
                        <tr>
                            <td>{{ $item->nama_produk }}</td>
                            <td class="tnum">{{ $rp($item->harga) }}</td>
                            <td class="tnum">{{ $item->qty }}</td>
                            <td class="tnum">{{ $rp($item->subtotal) }}</td>
                            <td class="tnum">{{ $item->tanggal_ed->format('d/m/Y') }}</td>
                            <td class="tnum">{{ $item->umur_produk_bulan }}</td>
                            <td class="tnum" style="color:var(--critical);">{{ $rp($item->subtotal - $item->nilai_buyback) }}</td>
                            <td class="tnum" style="font-weight:700;">{{ $rp($item->nilai_buyback) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" style="text-align:right; font-weight:700;">Total:</td>
                        <td class="tnum" style="font-weight:700;">{{ $rp($r->grand_nilai_beli) }}</td>
                        <td colspan="2"></td>
                        <td class="tnum" style="font-weight:700; color:var(--critical);">{{ $rp($r->grand_nilai_penyusutan) }}</td>
                        <td class="tnum" style="font-weight:700;">{{ $rp($r->grand_nilai_buyback) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="card-title" style="margin-bottom:12px;">Approval</div>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:16px; font-size:12.5px;">
            <div>
                <div style="color:var(--ink-muted); font-weight:600;">Head of SRN</div>
                @if ($r->isHeadApproved())
                    <div style="margin-top:4px;"><span class="chip chip-good">{{ $r->headApprover->name ?? '✓' }}</span></div>
                    <div style="color:var(--ink-muted); margin-top:4px;">{{ $r->approved_by_head_at?->translatedFormat('d M Y H:i') }}</div>
                @else
                    <div style="margin-top:4px; color:var(--ink-faint);">Belum approve</div>
                @endif
            </div>
            <div>
                <div style="color:var(--ink-muted); font-weight:600;">Finance</div>
                @if ($r->isFinanceApproved())
                    <div style="margin-top:4px;"><span class="chip chip-good">{{ $r->financeApprover->name ?? '✓' }}</span></div>
                    <div style="color:var(--ink-muted); margin-top:4px;">{{ $r->approved_by_finance_at?->translatedFormat('d M Y H:i') }}</div>
                @else
                    <div style="margin-top:4px; color:var(--ink-faint);">Belum approve</div>
                @endif
            </div>
        </div>
    </section>
@endsection
