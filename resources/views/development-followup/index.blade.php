@extends('layouts.app')

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:22px; flex-wrap:wrap;">
        <div>
            <h1 class="display" style="font-size:24px;">Follow Up</h1>
            <div style="color:var(--ink-muted); font-size:13px; margin-top:4px;">
                {{ $logs->total() }} catatan follow-up — mitra dari roster Weekly Plan
            </div>
        </div>
        <div style="display:flex; gap:10px;">
            <a href="{{ route('growth-specialist.followup.export', request()->query()) }}" class="btn" style="width:auto; display:inline-flex; align-items:center; gap:6px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v13.5"/><path d="M7 12l5 5 5-5" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 20h16" stroke-linecap="round"/></svg>
                Download Excel
            </a>
            <a href="{{ route('growth-specialist.followup.create') }}" class="btn btn-primary" style="width:auto;">+ Catat Follow-up</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif

    <form method="GET" action="{{ route('growth-specialist.followup.index') }}" class="field-row" style="align-items:flex-end; margin-bottom:16px;">
        @if (request('mitra_id'))
            <input type="hidden" name="mitra_id" value="{{ request('mitra_id') }}">
        @endif
        <div class="field" style="margin-bottom:0;">
            <label>Cari Nama Mitra</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Ketik nama mitra...">
        </div>
        @if (auth()->user()->role !== 'kae')
            <div class="field" style="margin-bottom:0;">
                <label>KAE</label>
                <select class="select-pill" name="kae_code" onchange="this.form.submit()">
                    <option value="">Semua KAE</option>
                    @foreach ($kaeOptions as $kae)
                        <option value="{{ $kae->kae_code }}" {{ request('kae_code') === $kae->kae_code ? 'selected' : '' }}>{{ $kae->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="field" style="margin-bottom:0;">
            <label>Status Follow-up</label>
            <select class="select-pill" name="status_followup" onchange="this.form.submit()">
                <option value="">Semua</option>
                <option value="FU 1" {{ request('status_followup') === 'FU 1' ? 'selected' : '' }}>FU 1</option>
                <option value="FU 2" {{ request('status_followup') === 'FU 2' ? 'selected' : '' }}>FU 2</option>
                <option value="Red Zone" {{ request('status_followup') === 'Red Zone' ? 'selected' : '' }}>Red Zone</option>
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
        <div style="display:flex; gap:12px;">
            <div class="field" style="margin-bottom:0;">
                <label>Dari Tanggal</label>
                <input type="date" name="dari" value="{{ request('dari') }}">
            </div>
            <div class="field" style="margin-bottom:0;">
                <label>Sampai Tanggal</label>
                <input type="date" name="sampai" value="{{ request('sampai') }}">
            </div>
        </div>
        <button type="submit" class="btn" style="width:auto;">Cari</button>
        @if (request('q') || request('kae_code') || request('status_followup') || request('alasan_kendala') || request('dari') || request('sampai'))
            <a href="{{ route('growth-specialist.followup.index') }}" class="btn" style="width:auto;">Reset</a>
        @endif
    </form>

    <section class="card table-card" style="padding:0;">
        <div class="table-scroll" style="max-height:none; overflow-y:visible;">
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th><th>Minggu</th><th>Mitra</th><th>Dicatat Oleh</th>
                        <th>Status FU</th><th>Durasi</th><th></th>
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
                            <td>{{ $log->user->name ?? '—' }}</td>
                            <td>
                                @if ($log->status_followup === 'Red Zone')
                                    <span class="chip chip-critical">Red Zone</span>
                                @else
                                    <span class="chip chip-neutral">{{ $log->status_followup }}</span>
                                @endif
                            </td>
                            <td class="tnum">{{ $log->total_menit ? $log->total_menit.' mnt' : '—' }}</td>
                            <td>
                                <div style="display:flex; gap:6px;">
                                    <button type="button" class="link-action" style="color:var(--ink-muted); font-size:12px; font-weight:600; background:none; border:1px solid var(--line); border-radius:7px; padding:5px 9px; cursor:pointer;" onclick="
                                        const d = document.getElementById('fu-detail-{{ $log->id }}');
                                        d.style.display = d.style.display === 'none' ? '' : 'none';
                                    ">View</button>
                                    <a href="{{ route('growth-specialist.followup.edit', $log) }}" class="link-action" style="color:var(--accent-ink); font-size:12px; font-weight:600; text-decoration:none; border:1px solid var(--line); border-radius:7px; padding:5px 9px;">Edit</a>
                                    <form method="POST" action="{{ route('growth-specialist.followup.destroy', $log) }}" onsubmit="return confirm('Hapus catatan follow-up untuk {{ addslashes($log->mitra->nama ?? 'mitra ini') }} tanggal {{ $log->tanggal_fu->format('d/m/Y') }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="link-action" style="color:var(--critical); font-size:12px; font-weight:600; background:none; border:1px solid var(--line); border-radius:7px; padding:5px 9px; cursor:pointer;">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <tr id="fu-detail-{{ $log->id }}" style="display:none;">
                            <td colspan="7" style="background:var(--surface-alt); padding:14px 20px;">
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
                        <tr><td colspan="7" style="color:var(--ink-muted);">Belum ada catatan follow-up.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div style="margin-top:16px;">{{ $logs->links() }}</div>
@endsection
