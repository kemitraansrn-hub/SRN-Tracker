@extends('layouts.app')

@php
    $rp = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');
@endphp

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:22px; flex-wrap:wrap;">
        <div>
            <h1 class="display" style="font-size:24px;">Follow-up Log</h1>
            <div style="color:var(--ink-muted); font-size:13px; margin-top:4px;">
                {{ $logs->total() }} catatan follow-up {{ auth()->user()->canViewAll() ? '' : 'kamu' }}
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

    <form method="GET" action="{{ route('followup.index') }}" class="field-row" style="align-items:flex-end; margin-bottom:16px;">
        @if (request('mitra_id'))
            <input type="hidden" name="mitra_id" value="{{ request('mitra_id') }}">
        @endif
        <div class="field" style="margin-bottom:0;">
            <label>Status Follow-up</label>
            <select class="select-pill" name="status_followup" onchange="this.form.submit()">
                <option value="">Semua</option>
                <option value="Terhubung" {{ request('status_followup') === 'Terhubung' ? 'selected' : '' }}>Terhubung</option>
                <option value="Tidak ada respon" {{ request('status_followup') === 'Tidak ada respon' ? 'selected' : '' }}>Tidak ada respon</option>
            </select>
        </div>
        <div class="field" style="margin-bottom:0;">
            <label>Status Belanja</label>
            <select class="select-pill" name="status_belanja" onchange="this.form.submit()">
                <option value="">Semua</option>
                <option value="Belanja Penuh" {{ request('status_belanja') === 'Belanja Penuh' ? 'selected' : '' }}>Belanja Penuh</option>
                <option value="Belanja Sebagian" {{ request('status_belanja') === 'Belanja Sebagian' ? 'selected' : '' }}>Belanja Sebagian</option>
                <option value="Belum Belanja" {{ request('status_belanja') === 'Belum Belanja' ? 'selected' : '' }}>Belum Belanja</option>
            </select>
        </div>
        <div class="field" style="margin-bottom:0;">
            <label>Alasan / Kendala</label>
            <select class="select-pill" name="alasan_kendala" onchange="this.form.submit()">
                <option value="">Semua</option>
                @foreach ($alasanOptions as $alasan)
                    <option value="{{ $alasan }}" {{ request('alasan_kendala') === $alasan ? 'selected' : '' }}>{{ $alasan }}</option>
                @endforeach
            </select>
        </div>
        @if (request('status_followup') || request('status_belanja') || request('alasan_kendala'))
            <div class="field" style="margin-bottom:0;">
                <a href="{{ route('followup.index') }}" class="btn" style="width:auto;">Reset</a>
            </div>
        @endif
    </form>

    <section class="card table-card" style="padding:0;">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th><th>Minggu</th><th>Mitra</th><th>KAE</th>
                        <th>Status FU</th><th>Status Belanja</th><th>Nominal</th><th>Durasi</th><th></th>
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
                            <td>
                                <div style="display:flex; gap:6px;">
                                    <button type="button" class="link-action" style="color:var(--ink-muted); font-size:12px; font-weight:600; background:none; border:1px solid var(--line); border-radius:7px; padding:5px 9px; cursor:pointer;" onclick="
                                        const d = document.getElementById('fu-detail-{{ $log->id }}');
                                        d.style.display = d.style.display === 'none' ? '' : 'none';
                                    ">View</button>
                                    <a href="{{ route('followup.edit', $log) }}" class="link-action" style="color:var(--accent-ink); font-size:12px; font-weight:600; text-decoration:none; border:1px solid var(--line); border-radius:7px; padding:5px 9px;">Edit</a>
                                    <form method="POST" action="{{ route('followup.destroy', $log) }}" onsubmit="return confirm('Hapus catatan follow-up untuk {{ addslashes($log->mitra->nama ?? 'mitra ini') }} tanggal {{ $log->tanggal_fu->format('d/m/Y') }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="link-action" style="color:var(--critical); font-size:12px; font-weight:600; background:none; border:1px solid var(--line); border-radius:7px; padding:5px 9px; cursor:pointer;">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <tr id="fu-detail-{{ $log->id }}" style="display:none;">
                            <td colspan="9" style="background:var(--surface-alt); padding:14px 20px;">
                                <div style="margin-bottom:8px;">
                                    <div class="info-label" style="margin-bottom:4px;">Alasan / Kendala</div>
                                    <div style="font-size:13px;">{{ $log->alasan_kendala ?: '—' }}</div>
                                </div>
                                <div>
                                    <div class="info-label" style="margin-bottom:4px;">Catatan / Tindak Lanjut</div>
                                    <div style="font-size:13px; white-space:pre-line;">{{ $log->catatan ?: '—' }}</div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" style="color:var(--ink-muted);">Belum ada catatan follow-up.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div style="margin-top:16px;">{{ $logs->links() }}</div>
@endsection
