@extends('layouts.app')

@php
    $rp = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');
@endphp

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:22px; flex-wrap:wrap;">
        <div>
            <h1 class="display" style="font-size:24px;">Follow-up Log</h1>
            <div style="color:var(--ink-muted); font-size:13px; margin-top:4px;">
                {{ $logs->total() }} catatan follow-up {{ auth()->user()->isAdmin() ? '' : 'kamu' }}
            </div>
        </div>
        <div style="display:flex; gap:10px;">
            <a href="{{ route('followup.export') }}" class="btn" style="width:auto; display:inline-flex; align-items:center; gap:6px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v13.5"/><path d="M7 12l5 5 5-5" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 20h16" stroke-linecap="round"/></svg>
                Download Excel
            </a>
            <a href="{{ route('followup.create') }}" class="btn btn-primary" style="width:auto;">+ Catat Follow-up</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif

    <section class="card table-card" style="padding:0;">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th><th>Minggu</th><th>Mitra</th><th>KAE</th>
                        <th>Status FU</th><th>Status Belanja</th><th>Nominal</th><th>Durasi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="tnum">{{ $log->tanggal_fu->format('d/m/Y') }}</td>
                            <td>{{ $log->minggu }}</td>
                            <td>
                                <a href="{{ route('mitra.show', $log->mitra) }}" style="color:var(--ink); text-decoration:none; font-weight:600;">{{ $log->mitra->nama ?? '—' }}</a>
                            </td>
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
                                    <span class="chip chip-warn">Belanja Sebagian</span>
                                @else
                                    <span class="chip chip-critical">Belum Belanja</span>
                                @endif
                            </td>
                            <td class="tnum">{{ $log->nominal_belanja ? $rp($log->nominal_belanja) : '—' }}</td>
                            <td class="tnum">{{ $log->total_menit ? $log->total_menit.' mnt' : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" style="color:var(--ink-muted);">Belum ada catatan follow-up.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div style="margin-top:16px;">{{ $logs->links() }}</div>
@endsection
