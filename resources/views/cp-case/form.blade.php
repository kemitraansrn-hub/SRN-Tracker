@extends('layouts.app')

@php
    $isEdit = isset($cpCase);
@endphp

@section('content')
    <a href="{{ route('tracking-cp.index') }}" style="display:inline-flex; align-items:center; gap:6px; font-size:12.5px; color:var(--ink-muted); text-decoration:none; margin-bottom:16px;">
        &larr; Kembali
    </a>

    <h1 class="display" style="font-size:22px; margin-bottom:20px;">{{ $isEdit ? 'Koreksi Kasus '.$cpCase->kode : 'Catat Kasus Baru' }}</h1>

    @if ($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ $isEdit ? route('tracking-cp.update', $cpCase) : route('tracking-cp.store') }}" class="card" style="max-width:760px;">
        @csrf
        @if ($isEdit) @method('PUT') @endif

        <div class="field-row">
            <div class="field" style="flex:1;">
                <label>Tanggal Temuan</label>
                <input type="date" name="tanggal_temuan" value="{{ old('tanggal_temuan', $isEdit ? $cpCase->tanggal_temuan->toDateString() : '') }}" required>
            </div>
            <div class="field" style="flex:1;">
                <label>Platform</label>
                <select name="platform" required>
                    <option value="">— pilih —</option>
                    @foreach ($platformOptions as $p)
                        <option value="{{ $p }}" {{ old('platform', $isEdit ? $cpCase->platform : '') === $p ? 'selected' : '' }}>{{ $p }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="field-row">
            <div class="field" style="flex:1;">
                <label>Mitra (kosongkan kalau gak ketemu di database)</label>
                <select name="mitra_id">
                    <option value="">— manual / gak ketemu —</option>
                    @foreach ($mitraOptions as $m)
                        <option value="{{ $m->id }}" {{ (string) old('mitra_id', $isEdit ? $cpCase->mitra_id : '') === (string) $m->id ? 'selected' : '' }}>{{ $m->nama }} ({{ $m->kode_mitra }})</option>
                    @endforeach
                </select>
            </div>
            <div class="field" style="flex:1;">
                <label>Nama Mitra Manual</label>
                <input type="text" name="nama_mitra_manual" value="{{ old('nama_mitra_manual', $isEdit ? $cpCase->nama_mitra_manual : '') }}" placeholder="Isi kalau mitra di atas gak dipilih">
            </div>
        </div>

        <div class="field-row">
            <div class="field" style="flex:1;">
                <label>Nama Toko</label>
                <input type="text" name="nama_toko" value="{{ old('nama_toko', $isEdit ? $cpCase->nama_toko : '') }}" required>
            </div>
            <div class="field" style="flex:1;">
                <label>Terjual (unit)</label>
                <input type="number" step="1" min="0" name="terjual" value="{{ old('terjual', $isEdit ? $cpCase->terjual : '') }}">
            </div>
            <div class="field" style="flex:1;">
                <label>Terlaris (unit) — menentukan Status Toko</label>
                <input type="number" step="1" min="0" name="terlaris" value="{{ old('terlaris', $isEdit ? $cpCase->terlaris : '') }}">
                @if ($isEdit && $cpCase->statusToko())
                    <div style="font-size:11.5px; color:var(--ink-muted); margin-top:4px;">Status Toko: <strong>{{ $cpCase->statusToko() }}</strong></div>
                @endif
            </div>
        </div>

        <div class="field-row">
            <div class="field" style="flex:1;">
                <label>Kota Toko</label>
                <select name="kota_kabupaten_id">
                    <option value="">— pilih —</option>
                    @foreach ($kotaOptions as $k)
                        <option value="{{ $k->id }}" {{ (string) old('kota_kabupaten_id', $isEdit ? $cpCase->kota_kabupaten_id : '') === (string) $k->id ? 'selected' : '' }}>{{ $k->nama }} ({{ $k->provinsi }})</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="field">
            <label>Link Etalase / Produk</label>
            <input type="text" name="link_etalase" value="{{ old('link_etalase', $isEdit ? $cpCase->link_etalase : '') }}" placeholder="https://...">
        </div>

        <div class="field-row">
            <div class="field" style="flex:1;">
                <label>Produk (dari Master Produk)</label>
                <select name="produk_id" id="produkSelect" onchange="isiHargaHet()" required>
                    <option value="">— pilih —</option>
                    @foreach ($produkOptions as $p)
                        <option value="{{ $p->id }}" data-het="{{ $p->harga_het }}" {{ (string) old('produk_id', $isEdit ? $cpCase->produk_id : '') === (string) $p->id ? 'selected' : '' }}>{{ $p->nama }} ({{ $p->brand }})</option>
                    @endforeach
                </select>
            </div>
            <div class="field" style="flex:1;">
                <label>Kode Barcode</label>
                <input type="text" name="kode_barcode" value="{{ old('kode_barcode', $isEdit ? $cpCase->kode_barcode : '') }}">
            </div>
        </div>

        <div class="field-row">
            <div class="field" style="flex:1;">
                <label>Harga SOP (Rp) — otomatis dari Harga HET produk, bisa diubah</label>
                <input type="number" step="1" min="0" name="harga_sop" id="hargaSopInput" value="{{ old('harga_sop', $isEdit ? $cpCase->harga_sop : '') }}" required>
            </div>
            <div class="field" style="flex:1;">
                <label>Harga Pelanggaran (Rp)</label>
                <input type="number" step="1" min="0" name="harga_pelanggaran" value="{{ old('harga_pelanggaran', $isEdit ? $cpCase->harga_pelanggaran : '') }}" required>
            </div>
        </div>

        <div class="field">
            <label>Status Kasus</label>
            <select name="status_kasus" required>
                @foreach ($statusOptions as $s)
                    <option value="{{ $s }}" {{ old('status_kasus', $isEdit ? $cpCase->status_kasus : 'Baru Ditemukan') === $s ? 'selected' : '' }}>{{ $s }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label>Bukti Temuan (link)</label>
            <input type="text" name="bukti_temuan" value="{{ old('bukti_temuan', $isEdit ? $cpCase->bukti_temuan : '') }}" placeholder="https://drive.google.com/...">
        </div>

        <button type="submit" class="btn btn-primary" style="width:auto; margin-top:8px;">{{ $isEdit ? 'Simpan Perubahan' : 'Simpan Kasus' }}</button>
    </form>

    @if ($isEdit)
        @php
            $fu1Done = (bool) $cpCase->follow_up_1_tanggal;
            $fu2Done = (bool) $cpCase->follow_up_2_tanggal;
            $fu3Done = (bool) $cpCase->follow_up_3_tanggal;
            $caseCloseDone = (bool) $cpCase->tanggal_case_close;
            $takedownDone = (bool) $cpCase->status_takedown;

            $stages = [
                ['key' => 'fu1', 'label' => 'Follow Up 1', 'unlocked' => true, 'done' => $fu1Done, 'info' => $fu1Done ? $cpCase->follow_up_1_tanggal->format('d/m/Y').' — '.($cpCase->follow_up_1_status ? 'Direspon mitra' : 'Belum direspon') : null],
                ['key' => 'fu2', 'label' => 'Follow Up 2', 'unlocked' => $fu1Done, 'done' => $fu2Done, 'info' => $fu2Done ? $cpCase->follow_up_2_tanggal->format('d/m/Y').' — '.($cpCase->follow_up_2_status ? 'Direspon mitra' : 'Belum direspon') : null, 'lockedMsg' => 'Selesaikan Follow Up 1 dulu'],
                ['key' => 'fu3', 'label' => 'Follow Up 3', 'unlocked' => $fu2Done, 'done' => $fu3Done, 'info' => $fu3Done ? $cpCase->follow_up_3_tanggal->format('d/m/Y').' — '.($cpCase->follow_up_3_status ? 'Direspon mitra' : 'Belum direspon') : null, 'lockedMsg' => 'Selesaikan Follow Up 2 dulu'],
                ['key' => 'caseClose', 'label' => 'Case Close', 'unlocked' => $fu3Done, 'done' => $caseCloseDone, 'info' => $caseCloseDone ? $cpCase->tanggal_case_close->format('d/m/Y') : null, 'lockedMsg' => 'Selesaikan Follow Up 3 dulu'],
                ['key' => 'takedown', 'label' => 'Takedown', 'unlocked' => $caseCloseDone, 'done' => $takedownDone, 'info' => $takedownDone ? 'Status: '.$cpCase->status_takedown : null, 'lockedMsg' => 'Selesaikan Case Close dulu'],
            ];
        @endphp

        <div class="card" style="max-width:760px; margin-top:20px;">
            <div style="font-weight:700; margin-bottom:14px;">Progres Kasus</div>
            <div style="display:flex; flex-direction:column; gap:10px;">
                @foreach ($stages as $s)
                    <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; padding:12px 14px; border:1px solid var(--line); border-radius:8px; background:{{ $s['done'] ? 'var(--accent-soft)' : 'var(--surface-alt)' }};">
                        <div>
                            <div style="font-weight:600; font-size:13px;">{{ $s['label'] }}</div>
                            <div style="font-size:12px; color:var(--ink-muted); margin-top:2px;">
                                @if ($s['info'])
                                    {{ $s['info'] }}
                                @elseif (! $s['unlocked'])
                                    {{ $s['lockedMsg'] }}
                                @else
                                    Belum diisi
                                @endif
                            </div>
                        </div>
                        @if ($s['unlocked'])
                            <button type="button" class="btn" style="width:auto; font-size:12px; padding:6px 12px;" onclick="openStageModal('modal-{{ $s['key'] }}')">{{ $s['done'] ? 'Ubah' : 'Isi' }}</button>
                        @endif
                    </div>
                @endforeach
            </div>

            @if ($cpCase->takedownBanding)
                <div class="alert-success" style="margin-top:14px;">
                    Kasus ini sudah masuk data Take Down &amp; Banding (status banding: {{ $cpCase->takedownBanding->status_banding ?? '—' }}).
                </div>
            @endif
        </div>

        @for ($i = 1; $i <= 3; $i++)
            <div class="modal-overlay" id="modal-fu{{ $i }}" style="display:none;">
                <div class="modal-box">
                    <div class="modal-title">Follow Up {{ $i }}</div>
                    <form method="POST" action="{{ route('tracking-cp.follow-up.update', [$cpCase, $i]) }}">
                        @csrf
                        @method('PATCH')
                        <div class="field">
                            <label>Tanggal Follow Up {{ $i }}</label>
                            <input type="date" name="tanggal" value="{{ $cpCase->{"follow_up_{$i}_tanggal"}?->toDateString() }}" required>
                        </div>
                        <label style="display:flex; align-items:center; gap:7px; margin:10px 0 20px;">
                            <input type="checkbox" name="status" value="1" {{ $cpCase->{"follow_up_{$i}_status"} ? 'checked' : '' }}>
                            Direspon mitra
                        </label>
                        <div class="modal-actions">
                            <button type="button" class="btn" style="width:auto;" onclick="closeStageModal('modal-fu{{ $i }}')">Batal</button>
                            <button type="submit" class="btn btn-primary" style="width:auto;">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        @endfor

        <div class="modal-overlay" id="modal-caseClose" style="display:none;">
            <div class="modal-box">
                <div class="modal-title">Case Close</div>
                <form method="POST" action="{{ route('tracking-cp.case-close.update', $cpCase) }}">
                    @csrf
                    @method('PATCH')
                    <div class="field">
                        <label>Tanggal Case Close</label>
                        <input type="date" name="tanggal_case_close" value="{{ $cpCase->tanggal_case_close?->toDateString() }}" required>
                    </div>
                    <div class="field" style="margin-bottom:20px;">
                        <label>Bukti Case Close (link)</label>
                        <input type="text" name="bukti_case_close" value="{{ $cpCase->bukti_case_close }}" placeholder="https://drive.google.com/...">
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn" style="width:auto;" onclick="closeStageModal('modal-caseClose')">Batal</button>
                        <button type="submit" class="btn btn-primary" style="width:auto;">Simpan</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal-overlay" id="modal-takedown" style="display:none;">
            <div class="modal-box">
                <div class="modal-title">Takedown</div>
                <form method="POST" action="{{ route('tracking-cp.takedown.update', $cpCase) }}">
                    @csrf
                    @method('PATCH')
                    <div class="field">
                        <label>Status Take Down</label>
                        <select name="status_takedown" required>
                            <option value="">— pilih —</option>
                            <option value="Pending" {{ $cpCase->status_takedown === 'Pending' ? 'selected' : '' }}>Pending</option>
                            <option value="Approved" {{ $cpCase->status_takedown === 'Approved' ? 'selected' : '' }}>Approved</option>
                            <option value="Rejected" {{ $cpCase->status_takedown === 'Rejected' ? 'selected' : '' }}>Rejected</option>
                        </select>
                    </div>
                    <label style="display:flex; align-items:center; gap:7px; margin:12px 0;">
                        <input type="checkbox" name="approval_takedown" value="1" {{ $cpCase->approval_takedown ? 'checked' : '' }}>
                        Approval Takedown disetujui
                    </label>
                    <label style="display:flex; align-items:center; gap:7px; margin-bottom:20px;">
                        <input type="checkbox" name="banding" value="1" {{ $cpCase->banding ? 'checked' : '' }}>
                        Mitra mengajukan banding
                    </label>
                    <div class="modal-actions">
                        <button type="button" class="btn" style="width:auto;" onclick="closeStageModal('modal-takedown')">Batal</button>
                        <button type="submit" class="btn btn-primary" style="width:auto;">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <script>
        function openStageModal(id) {
            document.getElementById(id).style.display = 'flex';
        }
        function closeStageModal(id) {
            document.getElementById(id).style.display = 'none';
        }

        function isiHargaHet() {
            const select = document.getElementById('produkSelect');
            const opt = select.options[select.selectedIndex];
            const het = opt ? opt.getAttribute('data-het') : null;
            if (het && het !== '' && het !== 'null') {
                document.getElementById('hargaSopInput').value = Math.round(parseFloat(het));
            }
        }
    </script>
@endsection
