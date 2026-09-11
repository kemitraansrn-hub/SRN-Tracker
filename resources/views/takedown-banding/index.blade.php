@extends('layouts.app')

@php
    $statusChip = fn ($s) => match ($s) {
        'Approved' => 'chip-good',
        'Rejected' => 'chip-critical',
        'Pending' => 'chip-warn',
        default => 'chip-neutral',
    };
@endphp

@section('content')
    <div style="margin-bottom:22px;">
        <h1 class="display" style="font-size:24px;">Take Down &amp; Banding</h1>
        <div style="color:var(--ink-muted); font-size:13px; margin-top:4px;">
            {{ $rows->total() }} kasus yang sudah resmi take down
        </div>
    </div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif

    <form method="GET" action="{{ route('takedown-banding.index') }}" class="field-row" style="align-items:flex-end; margin-bottom:16px;">
        <div class="field" style="margin-bottom:0; flex:1; min-width:180px;">
            <label>Cari</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Kode, mitra, nama toko...">
        </div>
        <button type="submit" class="btn" style="width:auto;">Cari</button>
        @if (request('q'))
            <a href="{{ route('takedown-banding.index') }}" class="btn" style="width:auto;">Reset</a>
        @endif
    </form>

    <section class="card table-card" style="padding:0;">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Kode</th><th>Toko / Platform</th><th>Mitra</th>
                        <th>Tanggal Takedown</th><th>Jumlah Follow Up</th><th>Alasan Takedown</th>
                        <th>Banding</th><th>SP</th><th>Status Final</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                        <tr>
                            <td class="tnum">{{ $r->cpCase->kode }}</td>
                            <td>
                                <div style="font-weight:600;">{{ $r->cpCase->nama_toko }}</div>
                                <div style="font-size:11px; color:var(--ink-muted);">{{ $r->cpCase->platform }}</div>
                            </td>
                            <td>{{ $r->cpCase->namaMitraTampil() }}</td>
                            <td class="tnum">{{ $r->tanggal_takedown?->format('d/m/Y') ?? '—' }}</td>
                            <td class="tnum">{{ $r->jumlah_follow_up ?? '—' }}</td>
                            <td style="max-width:220px; overflow:hidden; text-overflow:ellipsis;" title="{{ $r->alasan_takedown }}">{{ $r->alasan_takedown ?? '—' }}</td>
                            <td>
                                @if ($r->status_banding)
                                    <span class="chip {{ $statusChip($r->status_banding) }}">{{ $r->status_banding }}</span>
                                @else
                                    <span style="color:var(--ink-faint); font-size:12px;">Belum banding</span>
                                @endif
                            </td>
                            <td>{{ $r->sp ?? '—' }}</td>
                            <td>
                                @if ($r->status_takedown_final)
                                    <span class="chip {{ $statusChip($r->status_takedown_final) }}">{{ $r->status_takedown_final }}</span>
                                @else
                                    <span style="color:var(--ink-faint); font-size:12px;">—</span>
                                @endif
                            </td>
                            <td>
                                <button type="button" class="btn" style="width:auto; font-size:11.5px; padding:5px 10px;" onclick="openStageModal('modal-banding-{{ $r->id }}')">Banding</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" style="color:var(--ink-muted);">Belum ada kasus yang take down.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div style="margin-top:16px;">{{ $rows->links() }}</div>

    @foreach ($rows as $r)
        <div class="modal-overlay" id="modal-banding-{{ $r->id }}" style="display:none;">
            <div class="modal-box" style="max-width:520px;">
                <div class="modal-title">Banding — {{ $r->cpCase->kode }}</div>
                <form method="POST" action="{{ route('takedown-banding.update', $r) }}">
                    @csrf
                    @method('PATCH')
                    <div class="field">
                        <label>Alasan Takedown</label>
                        <textarea name="alasan_takedown" rows="2">{{ $r->alasan_takedown }}</textarea>
                    </div>
                    <div class="field-row">
                        <div class="field" style="flex:1;">
                            <label>Tanggal Banding</label>
                            <input type="date" name="tanggal_banding" value="{{ $r->tanggal_banding?->toDateString() }}">
                            <div style="font-size:11px; color:var(--ink-muted);">Isi kalau mitra mengajukan banding</div>
                        </div>
                        <div class="field" style="flex:1;">
                            <label>Status Banding</label>
                            <select name="status_banding">
                                <option value="">— belum banding —</option>
                                @foreach ($statusOptions as $s)
                                    <option value="{{ $s }}" {{ $r->status_banding === $s ? 'selected' : '' }}>{{ $s }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="field-row">
                        <div class="field" style="flex:1;">
                            <label>SP</label>
                            <select name="sp">
                                <option value="">—</option>
                                @foreach ($spOptions as $s)
                                    <option value="{{ $s }}" {{ $r->sp === $s ? 'selected' : '' }}>{{ $s }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field" style="flex:1;">
                            <label>Status Final</label>
                            <select name="status_takedown_final">
                                <option value="">—</option>
                                @foreach ($statusOptions as $s)
                                    <option value="{{ $s }}" {{ $r->status_takedown_final === $s ? 'selected' : '' }}>{{ $s }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="field" style="margin-bottom:20px;">
                        <label>Keputusan Final</label>
                        <textarea name="keputusan_final" rows="2">{{ $r->keputusan_final }}</textarea>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn" style="width:auto;" onclick="closeStageModal('modal-banding-{{ $r->id }}')">Batal</button>
                        <button type="submit" class="btn btn-primary" style="width:auto;">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
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
