@extends('layouts.app')

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:22px; flex-wrap:wrap;">
        <div>
            <h1 class="display" style="font-size:24px;">Follow Up</h1>
            <div style="color:var(--ink-muted); font-size:13px; margin-top:4px;">
                {{ $rowsPage->total() }} mitra dari Weekly Plan yang belum masuk Profiling
            </div>
        </div>
        <a href="{{ route('growth-specialist.followup.export', request()->query()) }}" class="btn" style="width:auto; display:inline-flex; align-items:center; gap:6px;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v13.5"/><path d="M7 12l5 5 5-5" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 20h16" stroke-linecap="round"/></svg>
            Download Excel
        </a>
    </div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    <form method="GET" action="{{ route('growth-specialist.followup.index') }}" class="field-row" style="align-items:flex-end; margin-bottom:16px;">
        <div class="field" style="margin-bottom:0;">
            <label>Cari Nama Mitra</label>
            <input type="text" name="q" value="{{ $q }}" placeholder="Ketik nama mitra...">
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
            <label>Status FU</label>
            <select class="select-pill" name="status_fu" onchange="this.form.submit()">
                <option value="">Semua</option>
                @foreach (['Belum FU', 'FU 1', 'FU 2', 'FU 3', 'Red Zone'] as $status)
                    <option value="{{ $status }}" {{ $statusFu === $status ? 'selected' : '' }}>{{ $status }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn" style="width:auto;">Cari</button>
        @if ($q !== '' || request('kae_code') || $statusFu !== '')
            <a href="{{ route('growth-specialist.followup.index') }}" class="btn" style="width:auto;">Reset</a>
        @endif
    </form>

    <section class="card table-card" style="padding:0;">
        <div class="table-scroll" style="max-height:none; overflow-y:visible;">
            <table>
                <thead>
                    <tr>
                        <th>Kode Mitra</th><th>Nama Mitra</th><th>Alamat</th><th>Segmen</th><th>No. WA</th><th>Status FU</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rowsPage as $r)
                        @php $m = $r->mitra; @endphp
                        <tr>
                            <td style="font-size:12px;">{{ $m->kode_mitra }}</td>
                            <td style="font-weight:600;"><a href="{{ route('mitra.show', $m) }}" style="color:var(--ink); text-decoration:none;">{{ $m->nama }}</a></td>
                            <td style="max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="{{ $m->alamat }}">{{ $m->alamat ?: '—' }}</td>
                            <td>@if ($r->segmen)<span class="chip chip-neutral">{{ $r->segmen }}</span>@else — @endif</td>
                            <td>{{ $m->no_wa ?: '—' }}</td>
                            <td style="max-width:280px;">
                                <div style="display:flex; gap:4px; flex-wrap:nowrap; overflow-x:auto; padding-bottom:2px;">
                                    @forelse ($r->logs as $log)
                                        <button type="button" class="chip chip-neutral" style="cursor:pointer; flex-shrink:0; white-space:nowrap;" title="Lihat kendala" onclick="openDetailFu('{{ $log->status_followup }}', '{{ $log->tanggal_fu->format('d/m/Y') }}', @js($log->alasan_kendala ?? ''), @js($log->catatan ?? ''), '{{ route('growth-specialist.followup.edit', $log) }}', '{{ route('growth-specialist.followup.destroy', $log) }}')">{{ $log->status_followup }}</button>
                                    @empty
                                        <span class="chip chip-neutral" style="flex-shrink:0; white-space:nowrap;">Belum FU</span>
                                    @endforelse
                                    @if ($r->sedangRedZone)
                                        <span class="chip chip-critical" style="flex-shrink:0; white-space:nowrap;" title="Belum bisa di-follow-up lagi sampai belanja">Red Zone</span>
                                    @endif
                                </div>
                            </td>
                            <td style="white-space:nowrap;">
                                @if ($r->sedangRedZone)
                                    <span style="font-size:11px; color:var(--ink-faint);">—</span>
                                @else
                                    <button type="button" class="btn btn-primary" style="width:auto; font-size:11.5px; padding:5px 10px;" onclick="openCatatFuModal({{ $m->id }}, @js($m->nama))">Catat Follow Up</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" style="color:var(--ink-muted);">{{ $q !== '' ? 'Tidak ada mitra yang cocok.' : 'Belum ada mitra di Weekly Plan periode ini.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div style="margin-top:16px;">{{ $rowsPage->links() }}</div>

    <div class="modal-overlay" id="modal-catat-fu" style="display:none;">
        <div class="modal-box">
            <div class="modal-title" id="catat-fu-title">Catat Follow-up</div>
            <form method="POST" action="{{ route('growth-specialist.followup.store') }}">
                @csrf
                <input type="hidden" name="mitra_id" id="catat-fu-mitra-id">
                <div class="field">
                    <label>Tanggal Follow-up</label>
                    <input type="date" name="tanggal_fu" value="{{ now()->toDateString() }}" required>
                </div>
                <div class="field">
                    <label>Alasan / Kendala</label>
                    <select name="alasan_kendala">
                        <option value="">— Tidak ada —</option>
                        @foreach ($alasanOptions as $alasan)
                            <option value="{{ $alasan }}">{{ $alasan }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Catatan / Tindak Lanjut</label>
                    <textarea name="catatan" rows="3" style="resize:vertical;"></textarea>
                </div>
                <div class="field-row">
                    <div class="field" style="flex:1;">
                        <label>Jam Mulai</label>
                        <input type="time" name="jam_mulai">
                    </div>
                    <div class="field" style="flex:1;">
                        <label>Jam Selesai</label>
                        <input type="time" name="jam_selesai">
                    </div>
                </div>
                <div class="modal-actions" style="margin-top:16px; display:flex; gap:8px; justify-content:flex-end;">
                    <button type="button" class="btn" style="width:auto;" onclick="closeCatatFuModal()">Batal</button>
                    <button type="submit" class="btn btn-primary" style="width:auto;">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="modal-detail-fu" style="display:none;">
        <div class="modal-box">
            <div class="modal-title" id="detail-fu-title">Follow-up</div>
            <div style="margin-bottom:10px;">
                <div class="info-label" style="margin-bottom:4px;">Alasan / Kendala</div>
                <div id="detail-fu-alasan" style="font-size:13px;">—</div>
            </div>
            <div style="margin-bottom:16px;">
                <div class="info-label" style="margin-bottom:4px;">Catatan / Tindak Lanjut</div>
                <div id="detail-fu-catatan" style="font-size:13px; white-space:pre-line;">—</div>
            </div>
            <div style="display:flex; gap:8px; justify-content:flex-end;">
                <button type="button" class="btn" style="width:auto;" onclick="closeDetailFu()">Tutup</button>
                <a id="detail-fu-edit-link" href="#" class="btn" style="width:auto; text-decoration:none; display:inline-block;">Edit</a>
                <form id="detail-fu-destroy-form" method="POST" action="#" onsubmit="return confirm('Hapus catatan follow-up ini?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger" style="width:auto;">Hapus</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openCatatFuModal(mitraId, nama) {
            document.getElementById('catat-fu-mitra-id').value = mitraId;
            document.getElementById('catat-fu-title').textContent = 'Catat Follow-up — ' + nama;
            document.getElementById('modal-catat-fu').style.display = 'flex';
        }
        function closeCatatFuModal() {
            document.getElementById('modal-catat-fu').style.display = 'none';
        }
        function openDetailFu(status, tanggal, alasan, catatan, editUrl, destroyUrl) {
            document.getElementById('detail-fu-title').textContent = 'Follow-up ' + tanggal + ' — ' + status;
            document.getElementById('detail-fu-alasan').textContent = alasan || '—';
            document.getElementById('detail-fu-catatan').textContent = catatan || '—';
            document.getElementById('detail-fu-edit-link').href = editUrl;
            document.getElementById('detail-fu-destroy-form').action = destroyUrl;
            document.getElementById('modal-detail-fu').style.display = 'flex';
        }
        function closeDetailFu() {
            document.getElementById('modal-detail-fu').style.display = 'none';
        }
    </script>
@endsection
