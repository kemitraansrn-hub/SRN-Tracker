@extends('layouts.app')

@php
    $rp = fn ($v) => $v !== null ? 'Rp'.number_format((float) $v, 0, ',', '.') : '—';
    $statusChip = function ($status) {
        return match ($status) {
            'diajukan' => '<span class="chip chip-warn">Diajukan</span>',
            'berjalan' => '<span class="chip chip-good">Berjalan</span>',
            'selesai' => '<span class="chip" style="background:var(--surface-alt); color:var(--ink-muted);">Selesai</span>',
            'batal' => '<span class="chip chip-critical">Batal</span>',
            default => $status,
        };
    };
@endphp

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:22px; flex-wrap:wrap;">
        <div>
            <h1 class="display" style="font-size:24px;">Special Deal</h1>
            <div style="color:var(--ink-muted); font-size:13px; margin-top:4px;">
                {{ $deals->total() }} deal {{ auth()->user()->isAdmin() ? '' : 'kamu' }}
            </div>
        </div>
        <a href="{{ route('special-deal.create') }}" class="btn btn-primary" style="width:auto;">+ Ajukan Special Deal</a>
    </div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif

    <form method="GET" action="{{ route('special-deal.index') }}" class="field-row" style="align-items:flex-end;">
        <div class="field" style="margin-bottom:0;">
            <label>Status</label>
            <select name="status" class="select-pill" onchange="this.form.submit()">
                <option value="">Semua Status</option>
                @foreach (\App\Http\Controllers\SpecialDealController::STATUSES as $s)
                    <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
        </div>
    </form>

    <section class="card table-card" style="padding:0; margin-top:16px;">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Mitra</th><th>KAE</th><th>Deskripsi</th><th>Nilai</th>
                        <th>Periode</th><th>Status</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($deals as $deal)
                        <tr>
                            <td>
                                <a href="{{ route('mitra.show', $deal->mitra) }}" style="color:var(--ink); text-decoration:none; font-weight:600;">{{ $deal->mitra->nama ?? '—' }}</a>
                            </td>
                            <td>{{ $deal->kae->name ?? '—' }}</td>
                            <td style="font-size:12.5px; color:var(--ink-muted); max-width:260px;">{{ \Illuminate\Support\Str::limit($deal->deskripsi, 70) }}</td>
                            <td class="tnum">{{ $rp($deal->nilai) }}</td>
                            <td class="tnum" style="font-size:12px;">
                                {{ $deal->tanggal_mulai?->format('d/m/Y') ?? '—' }} &ndash; {{ $deal->tanggal_selesai?->format('d/m/Y') ?? '—' }}
                            </td>
                            <td>{!! $statusChip($deal->status) !!}</td>
                            <td><a href="{{ route('special-deal.edit', $deal) }}" class="link-action" style="color:var(--accent-ink); font-size:12.5px; font-weight:600; text-decoration:none; border:1px solid var(--line); border-radius:7px; padding:5px 10px;">Edit</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" style="color:var(--ink-muted);">Belum ada special deal.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div style="margin-top:16px;">{{ $deals->links() }}</div>
@endsection
