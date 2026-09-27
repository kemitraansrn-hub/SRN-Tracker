@extends('layouts.app')

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:22px; flex-wrap:wrap;">
        <div>
            <h1 class="display" style="font-size:24px;">Komit Tracker</h1>
            <div style="color:var(--ink-muted); font-size:13px; margin-top:4px;">
                {{ $rows->count() }} mitra &mdash; sudah Lengkap LMS &amp; tercatat di Tracking Performance
            </div>
        </div>
        @if ($rows->isNotEmpty())
            <a href="{{ route('growth-specialist.komit-tracker.download', request()->only(['bulan', 'tahun', 'q', 'status_belanja'])) }}" class="btn" style="width:auto;">Download Excel</a>
        @endif
    </div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif

    @php
        $totalAch = $rows->sum('pencapaian');
        $totalKomit = $rows->sum('komit');
        $totalPctAch = $totalKomit > 0 ? round($totalAch / $totalKomit * 100, 1) : null;
    @endphp
    <section style="display:grid; grid-template-columns:repeat(3, 1fr); gap:16px; margin-bottom:20px;">
        <div class="card" style="min-width:0;">
            <div class="info-label" style="margin-bottom:8px;">Total Ach ({{ $bulanNama }})</div>
            <div class="tnum" style="font-size:22px; font-weight:700;">Rp{{ number_format($totalAch, 0, ',', '.') }}</div>
        </div>
        <div class="card" style="min-width:0;">
            <div class="info-label" style="margin-bottom:8px;">Total Target Komit ({{ $bulanNama }})</div>
            <div class="tnum" style="font-size:22px; font-weight:700;">Rp{{ number_format($totalKomit, 0, ',', '.') }}</div>
        </div>
        <div class="card" style="min-width:0;">
            <div class="info-label" style="margin-bottom:8px;">% Ach</div>
            <div class="tnum" style="font-size:22px; font-weight:700; color:{{ $totalPctAch !== null && $totalPctAch >= 100 ? 'var(--good)' : 'var(--ink)' }};">{{ $totalPctAch !== null ? $totalPctAch.'%' : '—' }}</div>
        </div>
    </section>

    <form method="GET" action="{{ route('growth-specialist.komit-tracker') }}" class="field-row" style="align-items:flex-end;">
        <div class="field" style="margin-bottom:0; flex:1; min-width:180px;">
            <label>Cari Mitra</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Nama atau kode mitra...">
        </div>
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
                @for ($y = now()->year + 1; $y >= now()->year - 2; $y--)
                    <option value="{{ $y }}" {{ $tahun == $y ? 'selected' : '' }}>{{ $y }}</option>
                @endfor
            </select>
        </div>
        <div class="field" style="margin-bottom:0;">
            <label>Status Belanja</label>
            <select name="status_belanja" class="select-pill">
                <option value="">Semua</option>
                @foreach ($statusBelanjaOptions as $s)
                    <option value="{{ $s }}" {{ request('status_belanja') === $s ? 'selected' : '' }}>{{ $s }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn" style="width:auto;">Cari</button>
        @if (request()->anyFilled(['q', 'status_belanja']))
            <a href="{{ route('growth-specialist.komit-tracker', ['bulan' => $bulan, 'tahun' => $tahun]) }}" class="btn" style="width:auto;">Reset</a>
        @endif
    </form>

    <section class="card table-card" style="padding:0; margin-top:16px;">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th rowspan="2" style="vertical-align:bottom;">Nama Mitra</th>
                        <th rowspan="2" style="vertical-align:bottom;">KAE RO</th>
                        <th rowspan="2" style="vertical-align:bottom;">KAE Development</th>
                        <th rowspan="2" style="vertical-align:bottom;">Status LMS</th>
                        <th colspan="3" style="text-align:center;">{{ strtoupper($bulanNama) }}</th>
                        <th rowspan="2" style="vertical-align:bottom;">Status Belanja</th>
                        <th rowspan="2" style="vertical-align:bottom;"></th>
                    </tr>
                    <tr>
                        <th>Komit {{ $bulanNama }}</th>
                        <th>{{ $bulanNama }}</th>
                        <th>% Ach</th>
                    </tr>
                </thead>
                <tbody>
                    @php $statusBolehAssignment = ['Kurang Belanja', 'Belum Belanja', 'Over RO']; @endphp
                    @forelse ($rows as $r)
                        <tr>
                            <td><a href="{{ route('mitra.show', $r['mitra_id']) }}" style="color:var(--ink); text-decoration:none; font-weight:600;">{{ $r['nama'] }}</a></td>
                            <td>{{ $r['kae_ro'] }}</td>
                            <td>{{ $r['kae_development'] }}</td>
                            <td style="font-size:12px;">{{ $r['status_lms'] }}</td>
                            <td class="tnum">{{ number_format($r['komit'], 0, ',', '.') }}</td>
                            <td class="tnum">{{ number_format($r['pencapaian'], 0, ',', '.') }}</td>
                            <td class="tnum">{{ $r['pct_ach'] !== null ? $r['pct_ach'].'%' : '—' }}</td>
                            <td><span class="chip chip-{{ $r['status_belanja_color'] }}">&#9679; {{ $r['status_belanja_label'] }}</span></td>
                            <td>
                                @if (in_array($r['status_belanja_label'], $statusBolehAssignment, true))
                                    @if ($mitraSudahOneOnOne->has($r['mitra_id']))
                                        <a href="{{ route('one-on-one.index') }}" class="link-action" style="color:var(--ink-muted); font-size:11.5px; font-weight:600; text-decoration:none; border:1px solid var(--line); border-radius:7px; padding:5px 10px; white-space:nowrap;">Sudah Dijadwalkan</a>
                                    @else
                                        <button type="button" class="btn" style="width:auto; font-size:11.5px; padding:5px 10px;" onclick="openAssignmentModal('{{ $r['mitra_id'] }}', '{{ addslashes($r['nama']) }}', '{{ addslashes($r['status_belanja_label']) }}')">Assignment</button>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" style="color:var(--ink-muted);">Tidak ada mitra yang cocok.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="modal-overlay" id="modal-assignment" style="display:none;">
        <div class="modal-box">
            <div class="modal-title">Buat Assignment</div>
            <form method="POST" action="{{ route('one-on-one.store') }}">
                @csrf
                <input type="hidden" name="mitra_id" id="assign-mitra-id">
                <input type="hidden" name="status_belanja" id="assign-status-belanja-input">
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
        function openAssignmentModal(mitraId, nama, statusBelanja) {
            document.getElementById('assign-mitra-id').value = mitraId;
            document.getElementById('assign-status-belanja-input').value = statusBelanja;
            document.getElementById('assign-nama-display').value = nama;
            document.getElementById('assign-status-display').value = statusBelanja;
            document.getElementById('modal-assignment').style.display = 'flex';
        }
        function closeAssignmentModal() {
            document.getElementById('modal-assignment').style.display = 'none';
        }
    </script>
@endsection
