@extends('layouts.app')

@section('content')
    <h1 class="display" style="font-size:24px;">Data Development</h1>
    <div style="color:var(--ink-muted); font-size:13px; margin-top:4px; margin-bottom:24px;">
        Snapshot mitra Pareto &amp; RTP dengan pencapaian di bawah 100% (Kurang Belanja) atau di atas 120% (Warning) &mdash; disimpan manual, bukan dihitung live.
    </div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif

    <div class="field-row" style="align-items:flex-end; margin-bottom:8px;">
        <form method="GET" action="{{ route('data-development.index') }}" class="field-row" style="align-items:flex-end; margin:0;">
            <div class="field" style="margin-bottom:0;">
                <label>Bulan</label>
                <select class="select-pill" name="bulan" onchange="this.form.submit()">
                    @foreach (['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'] as $i => $nama)
                        <option value="{{ $i + 1 }}" {{ $bulan == $i + 1 ? 'selected' : '' }}>{{ $nama }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field" style="margin-bottom:0;">
                <label>Tahun</label>
                <select class="select-pill" name="tahun" onchange="this.form.submit()">
                    @for ($y = now()->year - 1; $y <= now()->year + 1; $y++)
                        <option value="{{ $y }}" {{ $tahun == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
        </form>
        <div style="margin-left:auto; display:flex; gap:8px; align-items:flex-end;">
            @if ($kurang->isNotEmpty() || $warning->isNotEmpty())
                <a href="{{ route('data-development.download', ['bulan' => $bulan, 'tahun' => $tahun]) }}" class="btn" style="width:auto;">Download Excel</a>
            @endif
            @if (auth()->user()->hasAdminAccess())
                <form method="POST" action="{{ route('data-development.store') }}" onsubmit="return confirm('Simpan snapshot untuk {{ $periodeLabel }}? Snapshot lama untuk periode ini (jika ada) akan ditimpa.');">
                    @csrf
                    <input type="hidden" name="bulan" value="{{ $bulan }}">
                    <input type="hidden" name="tahun" value="{{ $tahun }}">
                    <button type="submit" class="btn btn-primary" style="width:auto;">Simpan Snapshot Bulan Ini</button>
                </form>
            @endif
        </div>
    </div>

    <div style="font-size:11.5px; color:var(--ink-muted); margin-bottom:20px;">
        @if ($lastSnapshot)
            Snapshot terakhir untuk {{ $periodeLabel }}: {{ \Carbon\Carbon::parse($lastSnapshot)->format('d/m/Y H:i') }}
        @else
            Belum ada snapshot untuk {{ $periodeLabel }}.
        @endif
    </div>

    <section class="card table-card" style="padding:0; margin-bottom:20px;">
        <div class="card-head" style="padding:18px 20px 0; margin-bottom:12px;">
            <div class="card-title">Data Mitra Kurang dari Target (Pareto &amp; RTP)</div>
            <div class="card-hint">{{ $kurang->count() }} mitra</div>
        </div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>ID</th><th>Nama Mitra</th><th>KAE</th><th>Segmen</th><th>% vs Target</th><th>Status Bulan</th><th></th></tr></thead>
                <tbody>
                    @forelse ($kurang as $s)
                        <tr>
                            <td>{{ $s->kode_mitra }}</td>
                            <td><a href="{{ route('mitra.show', $s->mitra_id) }}" style="color:var(--ink); text-decoration:none; font-weight:600;">{{ $s->nama }}</a></td>
                            @php $kaeCode = $s->mitra->kae_code ?? $s->kae_code; @endphp
                            <td>{{ $kaeCode ? (\App\Models\User::kaeNameMap()[$kaeCode] ?? $kaeCode) : '—' }}</td>
                            <td>{{ $s->segmen }}</td>
                            <td class="tnum">{{ $s->pct }}%</td>
                            @php $subStatus = \App\Services\AchievementStatus::resolveWeeklyPlan((float) $s->realisasi_bulan, (float) $s->target_bulan, (float) $s->pct); $subLabel = \App\Services\AchievementStatus::label($subStatus); @endphp
                            <td><span class="chip chip-{{ \App\Services\AchievementStatus::color($subStatus) }}">&#9679; {{ $subLabel }}</span></td>
                            <td>
                                @if ($mitraSudahAssignment->has($s->mitra_id))
                                    <a href="{{ route('assignment.index') }}" class="link-action" style="color:var(--ink-muted); font-size:11.5px; font-weight:600; text-decoration:none; border:1px solid var(--line); border-radius:7px; padding:5px 10px; white-space:nowrap;">Sudah Dijadwalkan</a>
                                @else
                                    <button type="button" class="btn" style="width:auto; font-size:11.5px; padding:5px 10px;" onclick="openAssignmentModal('{{ $s->mitra_id }}', '{{ addslashes($s->nama) }}', '{{ addslashes($subLabel) }}')">Assignment</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" style="color:var(--ink-muted);">Tidak ada data untuk periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card table-card" style="padding:0;">
        <div class="card-head" style="padding:18px 20px 0; margin-bottom:12px;">
            <div class="card-title">Data Mitra Warning (Pareto &amp; RTP)</div>
            <div class="card-hint">{{ $warning->count() }} mitra</div>
        </div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>ID</th><th>Nama Mitra</th><th>KAE</th><th>Segmen</th><th>% vs Target</th><th>Flag</th><th></th></tr></thead>
                <tbody>
                    @forelse ($warning as $s)
                        <tr>
                            <td>{{ $s->kode_mitra }}</td>
                            <td><a href="{{ route('mitra.show', $s->mitra_id) }}" style="color:var(--ink); text-decoration:none; font-weight:600;">{{ $s->nama }}</a></td>
                            @php $kaeCode = $s->mitra->kae_code ?? $s->kae_code; @endphp
                            <td>{{ $kaeCode ? (\App\Models\User::kaeNameMap()[$kaeCode] ?? $kaeCode) : '—' }}</td>
                            <td>{{ $s->segmen }}</td>
                            <td class="tnum">{{ $s->pct }}%</td>
                            <td><span class="chip chip-warn">&#9679; Warning</span></td>
                            <td>
                                @if ($mitraSudahAssignment->has($s->mitra_id))
                                    <a href="{{ route('assignment.index') }}" class="link-action" style="color:var(--ink-muted); font-size:11.5px; font-weight:600; text-decoration:none; border:1px solid var(--line); border-radius:7px; padding:5px 10px; white-space:nowrap;">Sudah Dijadwalkan</a>
                                @else
                                    <button type="button" class="btn" style="width:auto; font-size:11.5px; padding:5px 10px;" onclick="openAssignmentModal('{{ $s->mitra_id }}', '{{ addslashes($s->nama) }}', 'Warning')">Assignment</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" style="color:var(--ink-muted);">Tidak ada data untuk periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="modal-overlay" id="modal-assignment" style="display:none;">
        <div class="modal-box">
            <div class="modal-title">Buat Assignment</div>
            <form method="POST" action="{{ route('assignment.store') }}">
                @csrf
                <input type="hidden" name="mitra_id" id="assign-mitra-id">
                <input type="hidden" name="status_bulan" id="assign-status-bulan-input">
                <input type="hidden" name="bulan" value="{{ $bulan }}">
                <input type="hidden" name="tahun" value="{{ $tahun }}">
                <div class="field">
                    <label>Nama Mitra</label>
                    <input type="text" id="assign-nama-display" disabled>
                </div>
                <div class="field">
                    <label>Status Bulan</label>
                    <input type="text" id="assign-status-display" disabled>
                </div>
                <div class="field">
                    <label>Jadwal Zoom</label>
                    <input type="datetime-local" name="jadwal_zoom" required>
                </div>
                <div class="modal-actions" style="margin-top:16px; display:flex; gap:8px; justify-content:flex-end;">
                    <button type="button" class="btn" style="width:auto;" onclick="closeAssignmentModal()">Batal</button>
                    <button type="submit" class="btn btn-primary" style="width:auto;">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAssignmentModal(mitraId, nama, statusBulan) {
            document.getElementById('assign-mitra-id').value = mitraId;
            document.getElementById('assign-status-bulan-input').value = statusBulan;
            document.getElementById('assign-nama-display').value = nama;
            document.getElementById('assign-status-display').value = statusBulan;
            document.getElementById('modal-assignment').style.display = 'flex';
        }
        function closeAssignmentModal() {
            document.getElementById('modal-assignment').style.display = 'none';
        }
    </script>
@endsection
