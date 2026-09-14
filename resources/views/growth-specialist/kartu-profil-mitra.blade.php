@extends('layouts.app')

@section('content')
    @php
        $skorChip = fn (?int $s) => $s === null ? 'chip-neutral' : ($s >= 4 ? 'chip-good' : ($s == 3 ? 'chip-warn' : 'chip-critical'));
        $tipeMitraChip = fn (?string $t) => $t === 'Prioritas' ? 'chip-highlight' : ($t === 'Standar' ? 'chip-neutral' : 'chip-neutral');
    @endphp

    <h1 class="display" style="font-size:24px; margin-bottom:4px;">Kartu Profil Mitra</h1>
    <div class="card-hint" style="margin-bottom:20px;">Master Database &mdash; {{ $mitraList->count() }} mitra. Klik "Edit" di baris mana pun buat isi/ubah data.</div>

    {{-- 1. Identitas Mitra --}}
    <section class="card table-card reveal-on-scroll" style="padding:0; margin-bottom:20px;">
        <div class="card-head" style="padding:18px 20px 0; margin-bottom:12px;">
            <div class="card-title">1. Identitas Mitra</div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr><th>Nama Mitra</th><th>KAE RO</th><th>Channel</th><th>Status</th><th>Tanggal Onboarding</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($mitraList as $item)
                        @php $m = $item['mitra']; $p = $item['profil']; @endphp
                        <tr data-mitra-id="{{ $m->id }}">
                            <td style="font-weight:600;">{{ $m->nama }}<div style="font-size:11px; color:var(--ink-faint); font-weight:400;">{{ $m->kode_mitra }}</div></td>
                            <td>{{ $item['kae_nama'] ?? '—' }}</td>
                            <td>@if($item['channel'])<span class="chip chip-neutral">{{ $item['channel'] }}</span>@else — @endif</td>
                            <td>
                                <span class="gs-view">@if($p->status)<span class="chip chip-accent">{{ $p->status }}</span>@else — @endif</span>
                                <select class="gs-edit" data-field="status" hidden>
                                    <option value="">—</option>
                                    <option value="Existing" @selected($p->status === 'Existing')>Existing</option>
                                    <option value="New Distri" @selected($p->status === 'New Distri')>New Distri</option>
                                </select>
                            </td>
                            <td>
                                <span class="gs-view">{{ $p->tanggal_onboarding?->format('d/m/Y') ?? '—' }}</span>
                                <input type="date" class="gs-edit" data-field="tanggal_onboarding" value="{{ $p->tanggal_onboarding?->format('Y-m-d') }}" hidden>
                            </td>
                            <td class="gs-actions" style="white-space:nowrap;">
                                <button type="button" class="btn gs-edit-btn" style="width:auto; font-size:11.5px; padding:5px 9px;" onclick="gsEdit(this)">Edit</button>
                                <button type="button" class="btn gs-save-btn" style="width:auto; font-size:11.5px; padding:5px 9px;" onclick="gsSave(this, {{ $m->id }})" hidden>Simpan</button>
                                <button type="button" class="btn gs-cancel-btn" style="width:auto; font-size:11.5px; padding:5px 9px;" onclick="gsCancel(this)" hidden>Batal</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    {{-- 2. Kekuatan Finansial & Operasional --}}
    <section class="card table-card reveal-on-scroll" style="padding:0; margin-bottom:20px;">
        <div class="card-head" style="padding:18px 20px 0; margin-bottom:12px;">
            <div class="card-title">2. Kekuatan Finansial &amp; Operasional</div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Nama Mitra</th><th>Modal Bisnis</th><th>Modal SRN</th><th>Cost (Marketing + Operasional)</th>
                        <th>% Operational Cost</th><th>Toleransi Cashflow</th><th>Tim / Sendiri</th><th>Platform Jualan</th><th>Jam Aktif</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($mitraList as $item)
                        @php $m = $item['mitra']; $p = $item['profil']; $pct = $p->persenOperationalCost(); $tol = $p->toleransiCashflow(); @endphp
                        <tr data-mitra-id="{{ $m->id }}">
                            <td style="font-weight:600;">{{ $m->nama }}</td>
                            <td class="tnum">
                                <span class="gs-view">{{ $p->modal_bisnis !== null ? 'Rp'.number_format((float) $p->modal_bisnis, 0, ',', '.') : '—' }}</span>
                                <input type="number" min="0" step="1" class="gs-edit" data-field="modal_bisnis" value="{{ $p->modal_bisnis }}" hidden style="width:120px;">
                            </td>
                            <td class="tnum">
                                <span class="gs-view">{{ $p->modal_srn !== null ? 'Rp'.number_format((float) $p->modal_srn, 0, ',', '.') : '—' }}</span>
                                <input type="number" min="0" step="1" class="gs-edit" data-field="modal_srn" value="{{ $p->modal_srn }}" hidden style="width:120px;">
                            </td>
                            <td class="tnum">
                                <span class="gs-view">{{ $p->cost !== null ? 'Rp'.number_format((float) $p->cost, 0, ',', '.') : '—' }}</span>
                                <input type="number" min="0" step="1" class="gs-edit" data-field="cost" value="{{ $p->cost }}" hidden style="width:120px;">
                            </td>
                            <td class="tnum gs-computed" data-computed="persen_operational_cost">{{ $pct !== null ? $pct.'%' : '—' }}</td>
                            <td class="gs-computed" data-computed="toleransi_cashflow">@if($tol)<span class="chip chip-neutral">{{ $tol }}</span>@else — @endif</td>
                            <td>
                                <span class="gs-view">{{ $p->tim_sendiri ?? '—' }}</span>
                                <input type="text" class="gs-edit" data-field="tim_sendiri" value="{{ $p->tim_sendiri }}" placeholder="mis. Sendiri, Tim kecil (2)" hidden style="width:140px;">
                            </td>
                            <td>
                                <span class="gs-view">{{ $p->platform_jualan ? implode(', ', $p->platform_jualan) : '—' }}</span>
                                <div class="gs-edit" hidden style="display:flex; flex-wrap:wrap; gap:4px 10px; width:180px;">
                                    @foreach ($platformOptions as $opt)
                                        <label style="font-size:11px; display:flex; align-items:center; gap:3px;">
                                            <input type="checkbox" data-field="platform_jualan" value="{{ $opt }}" @checked($p->platform_jualan && in_array($opt, $p->platform_jualan))>
                                            {{ $opt }}
                                        </label>
                                    @endforeach
                                </div>
                            </td>
                            <td>
                                <span class="gs-view">{{ $p->jam_aktif ?? '—' }}</span>
                                <input type="text" class="gs-edit" data-field="jam_aktif" value="{{ $p->jam_aktif }}" placeholder="mis. 8 jam" hidden style="width:80px;">
                            </td>
                            <td class="gs-actions" style="white-space:nowrap;">
                                <button type="button" class="btn gs-edit-btn" style="width:auto; font-size:11.5px; padding:5px 9px;" onclick="gsEdit(this)">Edit</button>
                                <button type="button" class="btn gs-save-btn" style="width:auto; font-size:11.5px; padding:5px 9px;" onclick="gsSave(this, {{ $m->id }})" hidden>Simpan</button>
                                <button type="button" class="btn gs-cancel-btn" style="width:auto; font-size:11.5px; padding:5px 9px;" onclick="gsCancel(this)" hidden>Batal</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    {{-- 3. Channel Fokus --}}
    <section class="card table-card reveal-on-scroll" style="padding:0; margin-bottom:20px;">
        <div class="card-head" style="padding:18px 20px 0; margin-bottom:12px;">
            <div class="card-title">3. Channel Fokus</div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr><th>Nama Mitra</th><th>Tipe Channel</th><th>Channel Fokus 1</th><th>Channel Fokus 2</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($mitraList as $item)
                        @php $m = $item['mitra']; $p = $item['profil']; @endphp
                        <tr data-mitra-id="{{ $m->id }}">
                            <td style="font-weight:600;">{{ $m->nama }}</td>
                            <td>
                                <span class="gs-view">@if($p->tipe_channel)<span class="chip chip-neutral">{{ $p->tipe_channel }}</span>@else — @endif</span>
                                <select class="gs-edit" data-field="tipe_channel" hidden>
                                    <option value="">—</option>
                                    <option value="Online" @selected($p->tipe_channel === 'Online')>Online</option>
                                    <option value="Offline" @selected($p->tipe_channel === 'Offline')>Offline</option>
                                </select>
                            </td>
                            <td>
                                <span class="gs-view">{{ $p->channel_fokus_1 ? implode(', ', $p->channel_fokus_1) : '—' }}</span>
                                <div class="gs-edit" hidden style="display:flex; flex-wrap:wrap; gap:4px 10px; width:180px;">
                                    @foreach ($platformOptions as $opt)
                                        <label style="font-size:11px; display:flex; align-items:center; gap:3px;">
                                            <input type="checkbox" data-field="channel_fokus_1" value="{{ $opt }}" @checked($p->channel_fokus_1 && in_array($opt, $p->channel_fokus_1))>
                                            {{ $opt }}
                                        </label>
                                    @endforeach
                                </div>
                            </td>
                            <td>
                                <span class="gs-view">{{ $p->channel_fokus_2 ? implode(', ', $p->channel_fokus_2) : '—' }}</span>
                                <div class="gs-edit" hidden style="display:flex; flex-wrap:wrap; gap:4px 10px; width:180px;">
                                    @foreach ($platformOptions as $opt)
                                        <label style="font-size:11px; display:flex; align-items:center; gap:3px;">
                                            <input type="checkbox" data-field="channel_fokus_2" value="{{ $opt }}" @checked($p->channel_fokus_2 && in_array($opt, $p->channel_fokus_2))>
                                            {{ $opt }}
                                        </label>
                                    @endforeach
                                </div>
                            </td>
                            <td class="gs-actions" style="white-space:nowrap;">
                                <button type="button" class="btn gs-edit-btn" style="width:auto; font-size:11.5px; padding:5px 9px;" onclick="gsEdit(this)">Edit</button>
                                <button type="button" class="btn gs-save-btn" style="width:auto; font-size:11.5px; padding:5px 9px;" onclick="gsSave(this, {{ $m->id }})" hidden>Simpan</button>
                                <button type="button" class="btn gs-cancel-btn" style="width:auto; font-size:11.5px; padding:5px 9px;" onclick="gsCancel(this)" hidden>Batal</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    {{-- 4. Klasifikasi Mitra --}}
    <section class="card table-card reveal-on-scroll" style="padding:0; margin-bottom:20px;">
        <div class="card-head" style="padding:18px 20px 0; margin-bottom:12px;">
            <div class="card-title">4. Klasifikasi Mitra</div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Nama Mitra</th><th>Motivasi</th><th>Kemampuan</th><th>Keaktifan</th>
                        <th>Tipe Mitra</th><th>Deadline Setup Channel</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($mitraList as $item)
                        @php $m = $item['mitra']; $p = $item['profil']; $tipeMitra = $p->tipeMitra(); @endphp
                        <tr data-mitra-id="{{ $m->id }}">
                            <td style="font-weight:600;">{{ $m->nama }}</td>
                            @foreach (['motivasi', 'kemampuan', 'keaktifan'] as $skorField)
                                <td>
                                    <span class="gs-view">@if($p->$skorField)<span class="chip {{ $skorChip($p->$skorField) }}">{{ $p->$skorField }}</span>@else — @endif</span>
                                    <select class="gs-edit" data-field="{{ $skorField }}" hidden>
                                        <option value="">—</option>
                                        @for ($i = 1; $i <= 5; $i++)
                                            <option value="{{ $i }}" @selected($p->$skorField == $i)>{{ $i }}</option>
                                        @endfor
                                    </select>
                                </td>
                            @endforeach
                            <td class="gs-computed" data-computed="tipe_mitra">@if($tipeMitra)<span class="chip {{ $tipeMitraChip($tipeMitra) }}">{{ $tipeMitra }}</span>@else — @endif</td>
                            <td>
                                <span class="gs-view">{{ $p->deadline_setup_channel?->format('d/m/Y') ?? '—' }}</span>
                                <input type="date" class="gs-edit" data-field="deadline_setup_channel" value="{{ $p->deadline_setup_channel?->format('Y-m-d') }}" hidden>
                            </td>
                            <td class="gs-actions" style="white-space:nowrap;">
                                <button type="button" class="btn gs-edit-btn" style="width:auto; font-size:11.5px; padding:5px 9px;" onclick="gsEdit(this)">Edit</button>
                                <button type="button" class="btn gs-save-btn" style="width:auto; font-size:11.5px; padding:5px 9px;" onclick="gsSave(this, {{ $m->id }})" hidden>Simpan</button>
                                <button type="button" class="btn gs-cancel-btn" style="width:auto; font-size:11.5px; padding:5px 9px;" onclick="gsCancel(this)" hidden>Batal</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    {{-- 5. KPI Awal --}}
    <section class="card table-card reveal-on-scroll" style="padding:0; margin-bottom:20px;">
        <div class="card-head" style="padding:18px 20px 0; margin-bottom:12px;">
            <div class="card-title">5. KPI Awal</div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr><th>Nama Mitra</th><th>Target Traffic (per hari)</th><th>Target Leads (per hari)</th><th>Deadline Closing Pertama</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($mitraList as $item)
                        @php $m = $item['mitra']; $p = $item['profil']; $deadlineClosing = $p->deadlineClosingPertama(); @endphp
                        <tr data-mitra-id="{{ $m->id }}">
                            <td style="font-weight:600;">{{ $m->nama }}</td>
                            <td class="tnum">
                                <span class="gs-view">{{ $p->target_traffic ?? '—' }}</span>
                                <input type="number" min="0" step="1" class="gs-edit" data-field="target_traffic" value="{{ $p->target_traffic }}" hidden style="width:90px;">
                            </td>
                            <td class="tnum">
                                <span class="gs-view">{{ $p->target_leads ?? '—' }}</span>
                                <input type="number" min="0" step="1" class="gs-edit" data-field="target_leads" value="{{ $p->target_leads }}" hidden style="width:90px;">
                            </td>
                            <td class="gs-computed" data-computed="deadline_closing_pertama">{{ $deadlineClosing?->format('d/m/Y') ?? '—' }}</td>
                            <td class="gs-actions" style="white-space:nowrap;">
                                <button type="button" class="btn gs-edit-btn" style="width:auto; font-size:11.5px; padding:5px 9px;" onclick="gsEdit(this)">Edit</button>
                                <button type="button" class="btn gs-save-btn" style="width:auto; font-size:11.5px; padding:5px 9px;" onclick="gsSave(this, {{ $m->id }})" hidden>Simpan</button>
                                <button type="button" class="btn gs-cancel-btn" style="width:auto; font-size:11.5px; padding:5px 9px;" onclick="gsCancel(this)" hidden>Batal</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    {{-- 6. Integrasi LMS --}}
    <section class="card table-card reveal-on-scroll" style="padding:0; margin-bottom:20px;">
        <div class="card-head" style="padding:18px 20px 0; margin-bottom:12px;">
            <div class="card-title">6. Integrasi LMS</div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr><th>Nama Mitra</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($mitraList as $item)
                        @php $m = $item['mitra']; $p = $item['profil']; @endphp
                        <tr data-mitra-id="{{ $m->id }}">
                            <td style="font-weight:600;">{{ $m->nama }}</td>
                            <td>
                                <span class="gs-view">@if($p->lms_status)<span class="chip {{ $p->lms_status === 'Done' ? 'chip-good' : 'chip-warn' }}">{{ $p->lms_status }}</span>@else — @endif</span>
                                <select class="gs-edit" data-field="lms_status" hidden>
                                    <option value="">—</option>
                                    <option value="Done" @selected($p->lms_status === 'Done')>Done</option>
                                    <option value="On Progress" @selected($p->lms_status === 'On Progress')>On Progress</option>
                                </select>
                            </td>
                            <td class="gs-actions" style="white-space:nowrap;">
                                <button type="button" class="btn gs-edit-btn" style="width:auto; font-size:11.5px; padding:5px 9px;" onclick="gsEdit(this)">Edit</button>
                                <button type="button" class="btn gs-save-btn" style="width:auto; font-size:11.5px; padding:5px 9px;" onclick="gsSave(this, {{ $m->id }})" hidden>Simpan</button>
                                <button type="button" class="btn gs-cancel-btn" style="width:auto; font-size:11.5px; padding:5px 9px;" onclick="gsCancel(this)" hidden>Batal</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    {{-- 7. Status (Catatan) --}}
    <section class="card table-card reveal-on-scroll" style="padding:0; margin-bottom:20px;">
        <div class="card-head" style="padding:18px 20px 0; margin-bottom:12px;">
            <div class="card-title">7. Status (Catatan)</div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr><th>Nama Mitra</th><th>Catatan</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($mitraList as $item)
                        @php $m = $item['mitra']; $p = $item['profil']; @endphp
                        <tr data-mitra-id="{{ $m->id }}">
                            <td style="font-weight:600;">{{ $m->nama }}</td>
                            <td style="max-width:400px;">
                                <span class="gs-view">{{ $p->catatan ?? '—' }}</span>
                                <textarea class="gs-edit" data-field="catatan" rows="2" hidden style="width:100%;">{{ $p->catatan }}</textarea>
                            </td>
                            <td class="gs-actions" style="white-space:nowrap;">
                                <button type="button" class="btn gs-edit-btn" style="width:auto; font-size:11.5px; padding:5px 9px;" onclick="gsEdit(this)">Edit</button>
                                <button type="button" class="btn gs-save-btn" style="width:auto; font-size:11.5px; padding:5px 9px;" onclick="gsSave(this, {{ $m->id }})" hidden>Simpan</button>
                                <button type="button" class="btn gs-cancel-btn" style="width:auto; font-size:11.5px; padding:5px 9px;" onclick="gsCancel(this)" hidden>Batal</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <script>
        function gsEdit(btn) {
            const tr = btn.closest('tr');
            tr.querySelectorAll('.gs-view').forEach(el => el.hidden = true);
            tr.querySelectorAll('.gs-edit').forEach(el => el.hidden = false);
            tr.querySelector('.gs-edit-btn').hidden = true;
            tr.querySelector('.gs-save-btn').hidden = false;
            tr.querySelector('.gs-cancel-btn').hidden = false;
        }

        function gsCancel(btn) {
            const tr = btn.closest('tr');
            tr.querySelectorAll('.gs-view').forEach(el => el.hidden = false);
            tr.querySelectorAll('.gs-edit').forEach(el => el.hidden = true);
            tr.querySelector('.gs-edit-btn').hidden = false;
            tr.querySelector('.gs-save-btn').hidden = true;
            tr.querySelector('.gs-cancel-btn').hidden = true;
        }

        async function gsSave(btn, mitraId) {
            const tr = btn.closest('tr');
            const payload = {};

            tr.querySelectorAll('[data-field]').forEach(el => {
                const field = el.dataset.field;
                if (el.type === 'checkbox') {
                    if (!(field in payload)) payload[field] = [];
                    if (el.checked) payload[field].push(el.value);
                } else {
                    payload[field] = el.value === '' ? null : el.value;
                }
            });

            btn.disabled = true;
            btn.textContent = 'Menyimpan...';

            try {
                const res = await fetch(`/growth-specialist/kartu-profil-mitra/${mitraId}`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(payload),
                });

                if (!res.ok) {
                    const err = await res.json().catch(() => null);
                    alert('Gagal menyimpan: ' + (err?.message || res.status));
                    btn.disabled = false;
                    btn.textContent = 'Simpan';
                    return;
                }

                // Reload biar semua kolom hasil rumus (di section lain juga)
                // ikut ke-update konsisten, gak cuma section yang baru diedit.
                location.reload();
            } catch (e) {
                alert('Gagal menyimpan: ' + e.message);
                btn.disabled = false;
                btn.textContent = 'Simpan';
            }
        }
    </script>
@endsection
