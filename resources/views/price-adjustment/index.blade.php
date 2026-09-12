@extends('layouts.app')

@php
    $statusChip = fn ($s) => match ($s) {
        'Approved' => 'chip-good',
        'Rejected' => 'chip-critical',
        default => 'chip-warn',
    };
@endphp

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:22px; flex-wrap:wrap;">
        <div>
            <h1 class="display" style="font-size:24px;">Price Adjustment</h1>
            <div style="color:var(--ink-muted); font-size:13px; margin-top:4px;">
                {{ $requests->total() }} pengajuan izin penyesuaian harga
            </div>
        </div>
        <a href="{{ route('price-adjustment.create') }}" class="btn btn-primary" style="width:auto;">+ Ajukan Izin Baru</a>
    </div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif

    <form method="GET" action="{{ route('price-adjustment.index') }}" class="field-row" style="align-items:flex-end; margin-bottom:16px;">
        <div class="field" style="margin-bottom:0; flex:1; min-width:180px;">
            <label>Cari</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Nama mitra, nama toko...">
        </div>
        <div class="field" style="margin-bottom:0;">
            <label>Status</label>
            <select class="select-pill" name="status_approval" onchange="this.form.submit()">
                <option value="">Semua</option>
                @foreach ($statusOptions as $s)
                    <option value="{{ $s }}" {{ request('status_approval') === $s ? 'selected' : '' }}>{{ $s }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn" style="width:auto;">Cari</button>
        @if (request('q') || request('status_approval'))
            <a href="{{ route('price-adjustment.index') }}" class="btn" style="width:auto;">Reset</a>
        @endif
    </form>

    <section class="card table-card" style="padding:0;">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Mitra</th><th>Toko / Marketplace</th><th>Link Toko</th><th>SKU</th><th>Periode</th>
                        <th>Diajukan Oleh</th><th>Status</th><th>Diputuskan Oleh</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requests as $r)
                        <tr>
                            <td>{{ $r->mitra->nama ?? '—' }}</td>
                            <td>
                                <div style="font-weight:600;">{{ $r->toko }}</div>
                                <div style="font-size:11px; color:var(--ink-muted);">{{ $r->marketplace }}</div>
                            </td>
                            <td>
                                @if ($r->link_toko)
                                    <a href="{{ $r->link_toko }}" target="_blank" rel="noopener" class="link-chip" title="Link Toko / Marketplace">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                                    </a>
                                @else
                                    <span style="color:var(--ink-faint); font-size:12px;">—</span>
                                @endif
                            </td>
                            <td>
                                <button type="button" class="btn" style="width:auto; font-size:11.5px; padding:5px 10px;" onclick="const d = document.getElementById('items-{{ $r->id }}'); d.style.display = d.style.display === 'none' ? '' : 'none';">{{ $r->items->count() }} SKU</button>
                            </td>
                            <td class="tnum">{{ $r->tanggal_mulai->format('d/m/Y') }} &ndash; {{ $r->tanggal_selesai->format('d/m/Y') }}</td>
                            <td>{{ $r->pengaju->name ?? '—' }}</td>
                            <td><span class="chip {{ $statusChip($r->status_approval) }}">{{ $r->status_approval }}</span></td>
                            <td>{{ $r->penyetuju->name ?? '—' }}</td>
                            <td>
                                <div style="display:flex; gap:6px; flex-wrap:nowrap;">
                                    @if ($r->status_approval === 'Pending')
                                        @if (auth()->user()->isHeadOrManager())
                                            <button type="button" class="btn" style="width:auto; font-size:11.5px; padding:5px 10px;" onclick="openStageModal('modal-decision-{{ $r->id }}')">Putuskan</button>
                                        @endif
                                        <a href="{{ route('price-adjustment.edit', $r) }}" class="link-action" style="color:var(--accent-ink); font-size:12px; font-weight:600; text-decoration:none; border:1px solid var(--line); border-radius:7px; padding:5px 9px; white-space:nowrap;">Edit</a>
                                    @endif
                                    <form method="POST" action="{{ route('price-adjustment.destroy', $r) }}" onsubmit="return confirm('Hapus pengajuan untuk {{ $r->mitra->nama ?? 'mitra ini' }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger" style="width:auto; font-size:11.5px; padding:5px 10px;">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <tr id="items-{{ $r->id }}" style="display:none;">
                            <td colspan="9" style="background:var(--surface-alt);">
                                <table style="width:100%;">
                                    <thead>
                                        <tr>
                                            <th style="padding:6px 10px;">Produk</th><th style="padding:6px 10px;">HET</th>
                                            <th style="padding:6px 10px;">Harga Diskon</th><th style="padding:6px 10px;">% Diskon</th>
                                            <th style="padding:6px 10px;">Link Etalase</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($r->items as $it)
                                            <tr>
                                                <td style="padding:6px 10px;">{{ $it->produk->nama ?? '—' }}</td>
                                                <td class="tnum" style="padding:6px 10px;">{{ number_format((float) $it->harga_het, 0, ',', '.') }}</td>
                                                <td class="tnum" style="padding:6px 10px;">{{ number_format((float) $it->harga_diskon, 0, ',', '.') }}</td>
                                                <td class="tnum" style="padding:6px 10px;">
                                                    @if ($it->persentaseDiskon() !== null)
                                                        <span class="chip chip-critical">{{ $it->persentaseDiskon() }}%</span>
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                                <td style="padding:6px 10px;">
                                                    <a href="{{ $it->link_etalase }}" target="_blank" rel="noopener" class="link-chip" title="Link Etalase SKU ini">
                                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                                                    </a>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="5" style="padding:6px 10px; color:var(--ink-faint);">Belum ada SKU tercatat.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" style="color:var(--ink-muted);">Belum ada pengajuan tercatat.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div style="margin-top:16px;">{{ $requests->links() }}</div>

    @foreach ($requests as $r)
        @if ($r->status_approval === 'Pending')
            <div class="modal-overlay" id="modal-decision-{{ $r->id }}" style="display:none;">
                <div class="modal-box">
                    <div class="modal-title">Putuskan Pengajuan — {{ $r->mitra->nama ?? '—' }}</div>
                    <div class="modal-body">
                        {{ $r->toko }} ({{ $r->marketplace }}) &mdash; {{ $r->tanggal_mulai->format('d/m/Y') }} s/d {{ $r->tanggal_selesai->format('d/m/Y') }}.
                        <div style="margin-top:10px; display:flex; flex-direction:column; gap:4px;">
                            @foreach ($r->items as $it)
                                <div style="font-size:12.5px;">
                                    <b>{{ $it->produk->nama ?? '—' }}</b>
                                    <span style="color:var(--ink-muted);">
                                        — HET {{ number_format((float) $it->harga_het, 0, ',', '.') }} &rarr; {{ number_format((float) $it->harga_diskon, 0, ',', '.') }}
                                        @if ($it->persentaseDiskon() !== null) ({{ $it->persentaseDiskon() }}%) @endif
                                    </span>
                                </div>
                            @endforeach
                        </div>
                        @if ($r->catatan)
                            <br><b>Catatan:</b> {{ $r->catatan }}
                        @endif
                    </div>
                    <div style="display:flex; flex-direction:column; gap:8px;">
                        <form method="POST" action="{{ route('price-adjustment.decision', $r) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="keputusan" value="Approved">
                            <button type="submit" class="btn btn-primary" style="width:100%;">Approve</button>
                        </form>
                        <form method="POST" action="{{ route('price-adjustment.decision', $r) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="keputusan" value="Rejected">
                            <button type="submit" class="btn btn-danger" style="width:100%;">Reject</button>
                        </form>
                    </div>
                    <div class="modal-actions" style="margin-top:16px;">
                        <button type="button" class="btn" style="width:auto;" onclick="closeStageModal('modal-decision-{{ $r->id }}')">Batal</button>
                    </div>
                </div>
            </div>
        @endif
    @endforeach

    <script>
        function openStageModal(id) {
            document.getElementById(id).style.display = 'flex';
        }
        function closeStageModal(id) {
            document.getElementById(id).style.display = 'none';
        }
    </script>
@endsection
