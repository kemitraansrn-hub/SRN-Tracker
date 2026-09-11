@extends('layouts.app')

@php
    $rp = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');
    $statusChip = fn ($s) => match ($s) {
        'Progres' => 'chip-warn',
        'Pengajuan Takedown' => 'chip-critical',
        'Case Closed' => 'chip-good',
        default => 'chip-neutral',
    };
@endphp

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:22px; flex-wrap:wrap;">
        <div>
            <h1 class="display" style="font-size:24px;">Tracking CP</h1>
            <div style="color:var(--ink-muted); font-size:13px; margin-top:4px;">
                {{ $cases->total() }} kasus pelanggaran cutting price
            </div>
        </div>
        <a href="{{ route('tracking-cp.create') }}" class="btn btn-primary" style="width:auto;">+ Catat Kasus Baru</a>
    </div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif

    <form method="GET" action="{{ route('tracking-cp.index') }}" class="field-row" style="align-items:flex-end; margin-bottom:16px;">
        <div class="field" style="margin-bottom:0; flex:1; min-width:180px;">
            <label>Cari</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Kode, mitra, nama toko...">
        </div>
        <div class="field" style="margin-bottom:0;">
            <label>Status Kasus</label>
            <select class="select-pill" name="status_kasus" onchange="this.form.submit()">
                <option value="">Semua</option>
                @foreach ($statusOptions as $s)
                    <option value="{{ $s }}" {{ request('status_kasus') === $s ? 'selected' : '' }}>{{ $s }}</option>
                @endforeach
            </select>
        </div>
        <div class="field" style="margin-bottom:0;">
            <label>Platform</label>
            <select class="select-pill" name="platform" onchange="this.form.submit()">
                <option value="">Semua</option>
                @foreach ($platformOptions as $p)
                    <option value="{{ $p }}" {{ request('platform') === $p ? 'selected' : '' }}>{{ $p }}</option>
                @endforeach
            </select>
        </div>
        <div class="field" style="margin-bottom:0;">
            <label>Dari Tanggal</label>
            <input type="date" name="dari" value="{{ request('dari') }}">
        </div>
        <div class="field" style="margin-bottom:0;">
            <label>Sampai Tanggal</label>
            <input type="date" name="sampai" value="{{ request('sampai') }}">
        </div>
        <button type="submit" class="btn" style="width:auto;">Cari</button>
        @if (request('q') || request('status_kasus') || request('platform') || request('dari') || request('sampai'))
            <a href="{{ route('tracking-cp.index') }}" class="btn" style="width:auto;">Reset</a>
        @endif
    </form>

    <section class="card table-card" style="padding:0;">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Kode</th><th>Tanggal Temuan</th><th>Mitra</th><th>Toko / Platform</th>
                        <th>Terjual</th><th>Terlaris</th><th>Status Toko</th>
                        <th>Produk</th><th>Harga SOP</th><th>Harga Pelanggaran</th><th>Selisih</th>
                        <th>Status Kasus</th><th>Keterangan</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($cases as $c)
                        <tr>
                            <td class="tnum">{{ $c->kode }}</td>
                            <td class="tnum">{{ $c->tanggal_temuan->format('d/m/Y') }}</td>
                            <td>{{ $c->namaMitraTampil() }}</td>
                            <td>
                                <div style="font-weight:600;">{{ $c->nama_toko }}</div>
                                <div style="font-size:11px; color:var(--ink-muted);">{{ $c->platform }}{{ $c->kotaKabupaten ? ' · '.$c->kotaKabupaten->nama : '' }}</div>
                            </td>
                            <td class="tnum">{{ $c->terjual ?? '—' }}</td>
                            <td class="tnum">{{ $c->terlaris ?? '—' }}</td>
                            <td>{{ $c->statusToko() ?? '—' }}</td>
                            <td>{{ $c->produk->nama ?? '—' }}</td>
                            <td class="tnum">{{ $rp($c->harga_sop) }}</td>
                            <td class="tnum">{{ $rp($c->harga_pelanggaran) }}</td>
                            <td class="tnum" style="color:var(--critical);">{{ $c->persentaseSelisih() }}%</td>
                            <td>
                                @if ($c->takedownBanding)
                                    <span class="chip chip-critical">Take Down</span>
                                @else
                                    <span class="chip {{ $statusChip($c->status_kasus) }}">{{ $c->status_kasus }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($c->status_kasus === 'Case Closed')
                                    <span style="font-size:12.5px;">Mitra menaikan harga</span>
                                @elseif ($c->takedownBanding)
                                    <span style="font-size:12.5px;">Take Down</span>
                                    @if ($c->takedownBanding->status_banding)
                                        <div style="font-size:11px; color:var(--ink-muted); margin-top:3px;">Banding: {{ $c->takedownBanding->status_banding }}</div>
                                    @endif
                                @elseif ($c->status_takedown)
                                    <span style="font-size:12.5px;">{{ $c->status_takedown }}</span>
                                @else
                                    <span style="color:var(--ink-faint); font-size:12px;">—</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $fu1Done = (bool) $c->follow_up_1_tanggal;
                                    $fu2Done = (bool) $c->follow_up_2_tanggal;
                                    $fu3Done = (bool) $c->follow_up_3_tanggal;
                                    $belumDiputuskan = in_array($c->status_kasus, ['Baru Ditemukan', 'Progres'], true);
                                    $sedangTakedown = $c->status_kasus === 'Pengajuan Takedown';
                                    $bolehApprove = auth()->user()->isHead() || auth()->user()->isAdmin();
                                @endphp
                                <div style="display:flex; gap:6px; flex-wrap:nowrap; align-items:center;">
                                    <a href="{{ route('tracking-cp.edit', $c) }}" class="link-action" style="color:var(--accent-ink); font-size:12px; font-weight:600; text-decoration:none; border:1px solid var(--line); border-radius:7px; padding:5px 9px; white-space:nowrap;">Edit</a>

                                    @if (! $fu1Done)
                                        <button type="button" class="btn" style="width:auto; font-size:11.5px; padding:5px 10px;" onclick="openStageModal('modal-fu1-{{ $c->id }}')">FU 1</button>
                                    @elseif (! $fu2Done)
                                        <button type="button" class="btn" style="width:auto; font-size:11.5px; padding:5px 10px;" onclick="openStageModal('modal-fu2-{{ $c->id }}')">FU 2</button>
                                    @elseif (! $fu3Done)
                                        <button type="button" class="btn" style="width:auto; font-size:11.5px; padding:5px 10px;" onclick="openStageModal('modal-fu3-{{ $c->id }}')">FU 3</button>
                                    @elseif ($belumDiputuskan)
                                        <button type="button" class="btn" style="width:auto; font-size:11.5px; padding:5px 10px;" onclick="showStatusChoice('{{ $c->id }}'); openStageModal('modal-status-{{ $c->id }}')">Status Kasus</button>
                                    @elseif ($sedangTakedown && $c->status_takedown === 'Menunggu Approval')
                                        @if ($bolehApprove)
                                            <button type="button" class="btn" style="width:auto; font-size:11.5px; padding:5px 10px;" onclick="openStageModal('modal-approval-{{ $c->id }}')">Waiting Approval</button>
                                        @else
                                            <span style="font-size:11.5px; color:var(--ink-muted);">Menunggu Approval Head</span>
                                        @endif
                                    @elseif ($sedangTakedown && $c->status_takedown === 'Approved')
                                        <form method="POST" action="{{ route('tracking-cp.takedown.listed', $c) }}" onsubmit="return confirm('Tandai kasus {{ $c->kode }} sudah dilist ke Shopee?');">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn" style="width:auto; font-size:11.5px; padding:5px 10px;">List to Shopee</button>
                                        </form>
                                    @elseif ($sedangTakedown && $c->status_takedown === 'Listed ke Shopee')
                                        <button type="button" class="btn" style="width:auto; font-size:11.5px; padding:5px 10px;" onclick="openStageModal('modal-shopee-{{ $c->id }}')">Keputusan Shopee</button>
                                    @endif

                                    <form method="POST" action="{{ route('tracking-cp.destroy', $c) }}" onsubmit="return confirm('Hapus kasus {{ $c->kode }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger" style="width:auto; font-size:11.5px; padding:5px 10px;">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="14" style="color:var(--ink-muted);">Belum ada kasus tercatat.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div style="margin-top:16px;">{{ $cases->links() }}</div>

    @foreach ($cases as $c)
        @for ($i = 1; $i <= 3; $i++)
            <div class="modal-overlay" id="modal-fu{{ $i }}-{{ $c->id }}" style="display:none;">
                <div class="modal-box">
                    <div class="modal-title">Follow Up {{ $i }} — {{ $c->kode }}</div>
                    <form method="POST" action="{{ route('tracking-cp.follow-up.update', [$c, $i]) }}">
                        @csrf
                        @method('PATCH')
                        <div class="field">
                            <label>Tanggal Follow Up {{ $i }}</label>
                            <input type="date" name="tanggal" value="{{ $c->{"follow_up_{$i}_tanggal"}?->toDateString() }}" required>
                        </div>
                        <label style="display:flex; align-items:center; gap:7px; margin:10px 0 20px;">
                            <input type="checkbox" name="status" value="1" {{ $c->{"follow_up_{$i}_status"} ? 'checked' : '' }}>
                            Direspon mitra
                        </label>
                        <div class="modal-actions">
                            <button type="button" class="btn" style="width:auto;" onclick="closeStageModal('modal-fu{{ $i }}-{{ $c->id }}')">Batal</button>
                            <button type="submit" class="btn btn-primary" style="width:auto;">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        @endfor

        <div class="modal-overlay" id="modal-status-{{ $c->id }}" style="display:none;">
            <div class="modal-box">
                <div class="modal-title">Status Kasus — {{ $c->kode }}</div>

                <div id="status-choice-{{ $c->id }}">
                    <div class="modal-body">Follow up 3 ronde sudah selesai. Pilih status akhir kasus ini:</div>
                    <div style="display:flex; flex-direction:column; gap:8px;">
                        <button type="button" class="btn" style="width:auto; text-align:left;" onclick="showStatusSub('{{ $c->id }}', 'closed')">Case Closed — mitra sudah naikkan harga</button>
                        <button type="button" class="btn" style="width:auto; text-align:left;" onclick="showStatusSub('{{ $c->id }}', 'takedown')">Pengajuan Takedown</button>
                        <button type="button" class="btn" style="width:auto; text-align:left;" onclick="showStatusSub('{{ $c->id }}', 'progres')">Masih Progres</button>
                    </div>
                    <div class="modal-actions" style="margin-top:16px;">
                        <button type="button" class="btn" style="width:auto;" onclick="closeStageModal('modal-status-{{ $c->id }}')">Batal</button>
                    </div>
                </div>

                <div id="status-sub-closed-{{ $c->id }}" style="display:none;">
                    <form method="POST" action="{{ route('tracking-cp.case-close.update', $c) }}">
                        @csrf
                        @method('PATCH')
                        <div class="field">
                            <label>Tanggal Case Close</label>
                            <input type="date" name="tanggal_case_close" value="{{ now()->toDateString() }}" required>
                        </div>
                        <div class="field" style="margin-bottom:20px;">
                            <label>Bukti Case Close (link)</label>
                            <input type="text" name="bukti_case_close" placeholder="https://drive.google.com/...">
                        </div>
                        <div class="modal-actions">
                            <button type="button" class="btn" style="width:auto;" onclick="showStatusChoice('{{ $c->id }}')">Kembali</button>
                            <button type="submit" class="btn btn-primary" style="width:auto;">Tutup Kasus</button>
                        </div>
                    </form>
                </div>

                <div id="status-sub-takedown-{{ $c->id }}" style="display:none;">
                    <div class="modal-body">Kasus akan diajukan <b>Takedown</b> — notifikasi masuk ke Head of SRN buat di-approve/reject dulu.</div>
                    <form method="POST" action="{{ route('tracking-cp.takedown.update', $c) }}">
                        @csrf
                        @method('PATCH')
                        <div class="modal-actions">
                            <button type="button" class="btn" style="width:auto;" onclick="showStatusChoice('{{ $c->id }}')">Kembali</button>
                            <button type="submit" class="btn btn-primary" style="width:auto;">Ajukan Takedown</button>
                        </div>
                    </form>
                </div>

                <div id="status-sub-progres-{{ $c->id }}" style="display:none;">
                    <div class="modal-body">Kasus akan ditandai <b>Progres</b> — follow up sudah selesai, masih menunggu keputusan lebih lanjut.</div>
                    <form method="POST" action="{{ route('tracking-cp.status-kasus.progres', $c) }}">
                        @csrf
                        @method('PATCH')
                        <div class="modal-actions">
                            <button type="button" class="btn" style="width:auto;" onclick="showStatusChoice('{{ $c->id }}')">Kembali</button>
                            <button type="submit" class="btn btn-primary" style="width:auto;">Konfirmasi</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal-overlay" id="modal-approval-{{ $c->id }}" style="display:none;">
            <div class="modal-box">
                <div class="modal-title">Waiting Approval — {{ $c->kode }}</div>
                <div class="modal-body">Compliance mengajukan Takedown buat kasus ini. Setujui atau tolak?</div>
                <div style="display:flex; flex-direction:column; gap:8px;">
                    <form method="POST" action="{{ route('tracking-cp.takedown.decision', $c) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="keputusan" value="Approved">
                        <button type="submit" class="btn btn-primary" style="width:100%;">Approve</button>
                    </form>
                    <form method="POST" action="{{ route('tracking-cp.takedown.decision', $c) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="keputusan" value="Rejected">
                        <button type="submit" class="btn btn-danger" style="width:100%;">Reject</button>
                    </form>
                </div>
                <div class="modal-actions" style="margin-top:16px;">
                    <button type="button" class="btn" style="width:auto;" onclick="closeStageModal('modal-approval-{{ $c->id }}')">Batal</button>
                </div>
            </div>
        </div>

        <div class="modal-overlay" id="modal-shopee-{{ $c->id }}" style="display:none;">
            <div class="modal-box">
                <div class="modal-title">Keputusan Shopee — {{ $c->kode }}</div>
                <div class="modal-body">Listing takedown-nya udah dikirim ke Shopee. Disetujui atau ditolak?</div>
                <div style="display:flex; flex-direction:column; gap:8px;">
                    <form method="POST" action="{{ route('tracking-cp.takedown.selesai', $c) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="keputusan" value="Approved">
                        <button type="submit" class="btn btn-primary" style="width:100%;">Approved — Take Down</button>
                    </form>
                    <form method="POST" action="{{ route('tracking-cp.takedown.selesai', $c) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="keputusan" value="Rejected">
                        <button type="submit" class="btn btn-danger" style="width:100%;">Reject</button>
                    </form>
                </div>
                <div class="modal-actions" style="margin-top:16px;">
                    <button type="button" class="btn" style="width:auto;" onclick="closeStageModal('modal-shopee-{{ $c->id }}')">Batal</button>
                </div>
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
        function showStatusSub(caseId, key) {
            document.getElementById('status-choice-' + caseId).style.display = 'none';
            document.getElementById('status-sub-' + key + '-' + caseId).style.display = 'block';
        }
        function showStatusChoice(caseId) {
            ['closed', 'takedown', 'progres'].forEach(function (key) {
                var el = document.getElementById('status-sub-' + key + '-' + caseId);
                if (el) el.style.display = 'none';
            });
            document.getElementById('status-choice-' + caseId).style.display = 'block';
        }
    </script>
@endsection
