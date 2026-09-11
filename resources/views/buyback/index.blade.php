@extends('layouts.app')

@php
    $rp = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');
@endphp

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:22px; flex-wrap:wrap;">
        <div>
            <h1 class="display" style="font-size:24px;">Pengajuan Buy Back</h1>
            <div style="color:var(--ink-muted); font-size:13px; margin-top:4px;">
                {{ $requests->count() }} pengajuan
            </div>
        </div>
        @unless (auth()->user()->isFinance())
            <a href="{{ route('buyback.create') }}" class="btn btn-primary" style="width:auto;">+ Pengajuan Baru</a>
        @endunless
    </div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    <section class="card table-card" style="padding:0;">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Mitra</th><th>Diajukan Oleh</th><th>Tanggal Pengajuan</th><th>Total Qty</th><th>Tingkat Penyusutan</th>
                        <th>Total Nilai Beli</th><th>Total Nilai Penyusutan</th><th>Total Nilai Buy Back</th>
                        <th>Status</th><th>Approval Head</th><th>Approval Finance</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requests as $r)
                        <tr>
                            <td>
                                <div style="font-weight:600;">{{ $r->mitra->nama ?? '—' }}</div>
                                <div style="font-size:11.5px; color:var(--ink-muted);">{{ $r->mitra->kode_mitra ?? '' }}</div>
                            </td>
                            <td>{{ $r->creator->name ?? '—' }}</td>
                            <td class="tnum">{{ $r->created_at->format('d/m/Y') }}</td>
                            <td class="tnum">{{ $r->items->sum('qty') }}</td>
                            <td class="tnum">{{ rtrim(rtrim(number_format($r->tingkat_penyusutan, 2, ',', '.'), '0'), ',') }}%</td>
                            <td class="tnum">{{ $rp($r->grand_nilai_beli) }}</td>
                            <td class="tnum" style="color:var(--critical);">{{ $rp($r->grand_nilai_penyusutan) }}</td>
                            <td class="tnum" style="font-weight:700;">{{ $rp($r->grand_nilai_buyback) }}</td>
                            <td>
                                @if ($r->isApproved())
                                    <span class="chip chip-good">Approved</span>
                                @elseif ($r->isHeadApproved())
                                    <span class="chip chip-warn">Menunggu Finance</span>
                                @else
                                    <span class="chip chip-warn">On Check</span>
                                @endif
                            </td>
                            <td>
                                @if ($r->isHeadApproved())
                                    <span class="chip chip-good">{{ $r->headApprover->name ?? '✓' }}</span>
                                    <div style="font-size:11px; color:var(--ink-muted); margin-top:3px;">{{ $r->approved_by_head_at?->format('d/m/Y') }}</div>
                                @elseif (auth()->user()->isAdmin() || auth()->user()->isHead())
                                    <form method="POST" action="{{ route('buyback.approve', $r) }}" onsubmit="return confirm('Approve (sebagai Head of SRN) pengajuan buy back untuk {{ $r->mitra->nama ?? 'mitra ini' }}?');">
                                        @csrf
                                        <button type="submit" class="btn btn-primary" style="width:auto; font-size:11.5px; padding:5px 10px;">Approve</button>
                                    </form>
                                @else
                                    <span style="color:var(--ink-faint); font-size:12px;">—</span>
                                @endif
                            </td>
                            <td>
                                @if ($r->isFinanceApproved())
                                    <span class="chip chip-good">{{ $r->financeApprover->name ?? '✓' }}</span>
                                    <div style="font-size:11px; color:var(--ink-muted); margin-top:3px;">{{ $r->approved_by_finance_at?->format('d/m/Y') }}</div>
                                @elseif (auth()->user()->isAdmin() || auth()->user()->isFinance())
                                    @if ($r->isHeadApproved())
                                        <form method="POST" action="{{ route('buyback.approve', $r) }}" onsubmit="return confirm('Approve (sebagai Finance) pengajuan buy back untuk {{ $r->mitra->nama ?? 'mitra ini' }}?');">
                                            @csrf
                                            <button type="submit" class="btn btn-primary" style="width:auto; font-size:11.5px; padding:5px 10px;">Approve</button>
                                        </form>
                                    @else
                                        <span style="color:var(--ink-faint); font-size:11.5px;">Menunggu Head</span>
                                    @endif
                                @else
                                    <span style="color:var(--ink-faint); font-size:12px;">—</span>
                                @endif
                            </td>
                            <td>
                                <div style="display:flex; gap:6px;">
                                    @if (! $r->isApproved() && (auth()->user()->hasAdminAccess() || $r->created_by === auth()->id()))
                                        <a href="{{ route('buyback.edit', $r) }}" class="btn" style="width:auto; font-size:11.5px; padding:5px 10px;">Edit</a>
                                    @endif
                                    @if (auth()->user()->hasAdminAccess() || ($r->created_by === auth()->id() && ! $r->isApproved()))
                                        <form method="POST" action="{{ route('buyback.destroy', $r) }}" onsubmit="return confirm('Hapus pengajuan buy back untuk {{ $r->mitra->nama ?? 'mitra ini' }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger" style="width:auto; font-size:11.5px; padding:5px 10px;">Hapus</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="12" style="color:var(--ink-muted);">Belum ada pengajuan buy back.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
