@extends('layouts.app')

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:22px; flex-wrap:wrap;">
        <div>
            <h1 class="display" style="font-size:24px;">Assignment</h1>
            <div style="color:var(--ink-muted); font-size:13px; margin-top:4px;">
                {{ $assignments->count() }} assignment &mdash; follow-up Zoom mitra Kurang/Warning dari Data Development
            </div>
        </div>
        <a href="{{ route('data-development.index') }}" class="btn" style="width:auto;">Kembali ke Data Development</a>
    </div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif

    <section class="card table-card" style="padding:0;">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Nama Mitra</th><th>Status Bulan</th><th>Sesi</th><th>Jadwal Zoom</th><th>Status</th><th style="min-width:220px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($assignments as $a)
                        @php $aktif = $a->sesiAktif(); @endphp
                        <tr>
                            <td><a href="{{ route('mitra.show', $a->mitra_id) }}" style="color:var(--ink); text-decoration:none; font-weight:600;">{{ $a->mitra->nama ?? '—' }}</a></td>
                            <td>{{ $a->status_bulan }} <span style="color:var(--ink-muted); font-size:11px;">({{ $a->periodeLabel() }})</span></td>
                            <td>{{ $aktif?->label() ?? '—' }}</td>
                            <td class="tnum">{{ $aktif?->jadwal_zoom?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td>
                                @if ($a->status === 'selesai')
                                    <span class="chip chip-good">&#9679; Selesai</span>
                                @else
                                    <span class="chip chip-warn">&#9679; Terjadwal</span>
                                @endif
                            </td>
                            <td>
                                <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                    <button type="button" class="btn" style="width:auto; font-size:11.5px; padding:5px 10px;" onclick="const d = document.getElementById('riwayat-{{ $a->id }}'); d.style.display = d.style.display === 'none' ? '' : 'none';">Riwayat</button>
                                    @if ($a->status !== 'selesai')
                                        <button type="button" class="btn" style="width:auto; font-size:11.5px; padding:5px 10px;" onclick="openRescheduleModal({{ $a->id }}, '{{ $aktif?->jadwal_zoom?->format('Y-m-d\TH:i') }}')">Reschedule</button>
                                        <button type="button" class="btn btn-primary" style="width:auto; font-size:11.5px; padding:5px 10px;" onclick="openDoneModal({{ $a->id }}, {{ $aktif->urutan }}, {{ $aktif->urutan + 1 }})">Done</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        <tr id="riwayat-{{ $a->id }}" style="display:none;">
                            <td colspan="6" style="background:var(--surface-alt);">
                                <table style="width:100%; table-layout:fixed;">
                                    <colgroup>
                                        <col style="width:70px;"><col style="width:130px;"><col style="width:90px;">
                                        <col><col><col>
                                    </colgroup>
                                    <thead>
                                        <tr>
                                            <th style="padding:6px 10px;">Sesi</th><th style="padding:6px 10px;">Jadwal</th>
                                            <th style="padding:6px 10px;">Status</th><th style="padding:6px 10px;">Problem</th>
                                            <th style="padding:6px 10px;">Solusi</th><th style="padding:6px 10px;">Action Plan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($a->sesis->sortBy('urutan') as $sesi)
                                            <tr>
                                                <td style="padding:8px 10px; font-weight:600; vertical-align:top;">{{ $sesi->label() }}</td>
                                                <td style="padding:8px 10px; vertical-align:top;" class="tnum">{{ $sesi->jadwal_zoom?->format('d/m/Y H:i') }}</td>
                                                <td style="padding:8px 10px; vertical-align:top;">{{ $sesi->status === 'selesai' ? 'Selesai' : 'Terjadwal' }}</td>
                                                <td style="padding:8px 10px; vertical-align:top; white-space:normal; word-break:break-word; overflow-wrap:anywhere;">{{ $sesi->problem ?: '—' }}</td>
                                                <td style="padding:8px 10px; vertical-align:top; white-space:normal; word-break:break-word; overflow-wrap:anywhere;">{{ $sesi->solusi ?: '—' }}</td>
                                                <td style="padding:8px 10px; vertical-align:top; white-space:normal; word-break:break-word; overflow-wrap:anywhere;">{{ $sesi->action_plan ?: '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" style="color:var(--ink-muted);">Belum ada Assignment.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- Reschedule --}}
    <div class="modal-overlay" id="modal-reschedule" style="display:none;">
        <div class="modal-box">
            <div class="modal-title">Reschedule Jadwal Zoom</div>
            <form method="POST" id="form-reschedule">
                @csrf
                <div class="field">
                    <label>Jadwal Zoom Baru</label>
                    <input type="datetime-local" name="jadwal_zoom" id="reschedule-jadwal" required>
                </div>
                <div class="modal-actions" style="margin-top:16px; display:flex; gap:8px; justify-content:flex-end;">
                    <button type="button" class="btn" style="width:auto;" onclick="closeModal('modal-reschedule')">Batal</button>
                    <button type="submit" class="btn btn-primary" style="width:auto;">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Done: isi hasil sesi + lanjut/selesai --}}
    <div class="modal-overlay" id="modal-done" style="display:none;">
        <div class="modal-box">
            <div class="modal-title" id="done-title">Hasil Sesi</div>
            <form method="POST" id="form-done">
                @csrf
                <div class="field">
                    <label>Problem</label>
                    <textarea name="problem" rows="2" required></textarea>
                </div>
                <div class="field">
                    <label>Solusi</label>
                    <textarea name="solusi" rows="2" required></textarea>
                </div>
                <div class="field">
                    <label>Action Plan</label>
                    <textarea name="action_plan" rows="2" required></textarea>
                </div>
                <div class="field">
                    <label>Selanjutnya</label>
                    <div style="display:flex; gap:16px; font-size:13px;">
                        <label style="display:flex; align-items:center; gap:6px; font-weight:400;">
                            <input type="radio" name="next_action" value="lanjut" onchange="toggleNextJadwal(true)" checked>
                            <span id="done-lanjut-label">Lanjut Sesi Berikutnya</span>
                        </label>
                        <label style="display:flex; align-items:center; gap:6px; font-weight:400;">
                            <input type="radio" name="next_action" value="done" onchange="toggleNextJadwal(false)">
                            <span>Done</span>
                        </label>
                    </div>
                </div>
                <div class="field" id="done-next-jadwal-field">
                    <label>Jadwal Zoom Sesi Berikutnya</label>
                    <input type="datetime-local" name="next_jadwal" id="done-next-jadwal">
                </div>
                <div class="modal-actions" style="margin-top:16px; display:flex; gap:8px; justify-content:flex-end;">
                    <button type="button" class="btn" style="width:auto;" onclick="closeModal('modal-done')">Batal</button>
                    <button type="submit" class="btn btn-primary" style="width:auto;">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }
        function openRescheduleModal(assignmentId, currentJadwal) {
            document.getElementById('form-reschedule').action = '/assignment/' + assignmentId + '/reschedule';
            document.getElementById('reschedule-jadwal').value = currentJadwal || '';
            document.getElementById('modal-reschedule').style.display = 'flex';
        }
        function openDoneModal(assignmentId, sesiUrutan, nextUrutan) {
            document.getElementById('form-done').action = '/assignment/' + assignmentId + '/complete';
            document.getElementById('done-title').textContent = 'Hasil Sesi ' + sesiUrutan;
            document.getElementById('done-lanjut-label').textContent = 'Lanjut Sesi ' + nextUrutan;
            document.querySelector('#form-done input[name="next_action"][value="lanjut"]').checked = true;
            toggleNextJadwal(true);
            document.getElementById('modal-done').style.display = 'flex';
        }
        function toggleNextJadwal(show) {
            const field = document.getElementById('done-next-jadwal-field');
            const input = document.getElementById('done-next-jadwal');
            field.style.display = show ? '' : 'none';
            input.required = show;
        }
    </script>
@endsection
