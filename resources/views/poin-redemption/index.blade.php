@extends('layouts.app')

@php
    $kaeNameMap = \App\Models\User::kaeNameMap();
@endphp

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:22px; flex-wrap:wrap;">
        <div>
            <h1 class="display" style="font-size:24px;">Penukaran Poin</h1>
            <div style="color:var(--ink-muted); font-size:13px; margin-top:4px;">{{ $redemptions->count() }} pengajuan</div>
        </div>
        <div style="display:flex; gap:8px;">
            <a href="{{ route('poin-redemption.export') }}" class="btn" style="width:auto; text-decoration:none;">Download Excel</a>
            <a href="{{ route('poin-redemption.create') }}" class="btn btn-primary" style="width:auto;">+ Penukaran Baru</a>
        </div>
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
                        <th>KAE</th><th>Tanggal</th><th>Nama Mitra</th><th>Reward</th><th>Qty</th>
                        <th>Keterangan</th><th>Poin</th><th>Note</th>
                        <th>Status</th><th>Approved By</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($redemptions as $r)
                        <tr>
                            <td>{{ $kaeNameMap[$r->mitra->kae_code ?? ''] ?? ($r->mitra->kae_code ?? '—') }}</td>
                            <td class="tnum">{{ $r->created_at->format('d/m/Y') }}</td>
                            <td>
                                <div style="font-weight:600;">{{ $r->mitra->nama ?? '—' }}</div>
                                <div style="font-size:11.5px; color:var(--ink-muted);">{{ $r->mitra->kode_mitra ?? '' }}</div>
                            </td>
                            <td>{{ $r->nama_reward }}</td>
                            <td class="tnum">{{ $r->qty }}</td>
                            <td>
                                @if ($r->keterangan === 'di-uangkan')
                                    <span class="chip chip-accent">Di Uangkan</span>
                                @else
                                    <span class="chip chip-neutral">Sesuai dengan Reward</span>
                                @endif
                            </td>
                            <td class="tnum" style="font-weight:700;">{{ $r->poin_terpakai }}</td>
                            <td style="font-size:12px; color:var(--ink-muted); max-width:220px;">{{ $r->note ?? '—' }}</td>
                            <td>
                                @if ($r->isApproved())
                                    <span class="chip chip-good">Approved</span>
                                @else
                                    <span class="chip chip-warn">On Check</span>
                                @endif
                            </td>
                            <td>{{ $r->approver->name ?? '—' }}</td>
                            <td>
                                <div style="display:flex; gap:6px;">
                                    @if (! $r->isApproved() && (auth()->user()->isAdmin() || auth()->user()->isHead()))
                                        <form method="POST" action="{{ route('poin-redemption.approve', $r) }}" onsubmit="return confirm('Approve penukaran poin untuk {{ $r->mitra->nama ?? 'mitra ini' }}?');">
                                            @csrf
                                            <button type="submit" class="btn btn-primary" style="width:auto; font-size:11.5px; padding:5px 10px;">Approve</button>
                                        </form>
                                    @endif
                                    @if (! $r->isApproved() && (auth()->user()->isAdmin() || $r->created_by === auth()->id()))
                                        <a href="{{ route('poin-redemption.edit', $r) }}" class="btn" style="width:auto; font-size:11.5px; padding:5px 10px;">Edit</a>
                                    @endif
                                    @if (auth()->user()->isAdmin() || ($r->created_by === auth()->id() && ! $r->isApproved()))
                                        <form method="POST" action="{{ route('poin-redemption.destroy', $r) }}" onsubmit="return confirm('Hapus penukaran poin untuk {{ $r->mitra->nama ?? 'mitra ini' }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger" style="width:auto; font-size:11.5px; padding:5px 10px;">Hapus</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" style="color:var(--ink-muted);">Belum ada penukaran poin.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
