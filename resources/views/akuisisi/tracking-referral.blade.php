@extends('layouts.app')

@php
    $rp = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');
    $statusChip = function ($status) {
        return match ($status) {
            'sudah_bayar' => ['Sudah Dibayar', 'chip-good'],
            'dibatalkan' => ['Dibatalkan', 'chip-critical'],
            'di_luar_window' => ['Di Luar Window', 'chip-critical'],
            default => ['Belum Dibayar', 'chip-warn'],
        };
    };
@endphp

@section('content')
    <div class="topbar" style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:22px; flex-wrap:wrap;">
        <div>
            <h1 class="display" style="font-size:24px;">Tracking Referral</h1>
            <div style="color:var(--ink-muted); font-size:13px; margin-top:4px;">
                Program Akuisisi Mitra Offline &mdash; bonus 3% / 2% / 1% dari transaksi bulan ke-1/2/3 mitra, berlaku 6 bulan sejak transaksi pertama
            </div>
        </div>
        <div class="field-row" style="margin-bottom:0;">
            <a href="{{ route('akuisisi-referral.download', request()->query()) }}" class="btn" style="width:auto;">Download Excel</a>
            @if (auth()->user()->role !== 'kae')
                <a href="{{ route('pengakuisisi.index') }}" class="btn" style="width:auto; text-decoration:none;">Kelola Pengakuisisi</a>
            @endif
            @if (auth()->user()->hasAdminAccess())
                <button type="button" class="btn btn-primary" style="width:auto;" onclick="document.getElementById('modal-tambah-referral').style.display='flex'">+ Tambah Mitra</button>
            @endif
        </div>
    </div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    <form method="GET" action="{{ route('akuisisi-referral.index') }}" class="field-row" style="align-items:flex-end; margin-bottom:16px;">
        <div class="field" style="margin-bottom:0;">
            <label>Cari Mitra</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Nama/kode mitra...">
        </div>
        @if (auth()->user()->role !== 'kae')
            <div class="field" style="margin-bottom:0;">
                <label>Pengakuisisi</label>
                <select class="select-pill" name="pengakuisisi_id" onchange="this.form.submit()">
                    <option value="">Semua</option>
                    @foreach ($pengakuisisiOptions as $p)
                        <option value="{{ $p->id }}" {{ (string) request('pengakuisisi_id') === (string) $p->id ? 'selected' : '' }}>{{ $p->nama }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <button type="submit" class="btn" style="width:auto;">Cari</button>
        @if (request('q') || request('pengakuisisi_id'))
            <a href="{{ route('akuisisi-referral.index') }}" class="btn" style="width:auto;">Reset</a>
        @endif
    </form>

    <section class="card table-card reveal-on-scroll" style="padding:0;">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Mitra</th>
                        <th>Pengakuisisi</th>
                        <th>Tgl Akuisisi</th>
                        <th>Transaksi 1 (3%)</th>
                        <th>Transaksi 2 (2%)</th>
                        <th>Transaksi 3 (1%)</th>
                        <th>Total Bonus Berhak</th>
                        @if (auth()->user()->hasAdminAccess())
                            <th></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($referrals as $ref)
                        @php $tahaps = $ref->hitungTahap()->keyBy('tahap'); @endphp
                        <tr>
                            <td style="white-space:nowrap;">
                                <a href="{{ route('mitra.show', $ref->mitra) }}" style="color:var(--ink); text-decoration:none; font-weight:600;">{{ $ref->mitra->nama }}</a>
                                <div style="font-size:11px; color:var(--ink-muted);">{{ $ref->mitra->kode_mitra }}</div>
                            </td>
                            <td>{{ $ref->nama_pengakuisisi }}</td>
                            <td class="tnum">{{ $ref->tanggal_akuisisi->format('d/m/Y') }}</td>
                            @foreach ([1, 2, 3] as $t)
                                @php $th = $tahaps->get($t); @endphp
                                <td style="min-width:170px;">
                                    @if (! $th)
                                        <span style="color:var(--ink-faint); font-size:12px;">Belum ada transaksi</span>
                                    @else
                                        @php [$label, $cls] = $statusChip($th->status); @endphp
                                        <div style="font-size:11.5px; color:var(--ink-muted);">{{ $th->bulan_label }}</div>
                                        <div class="tnum" style="font-size:12.5px;">{{ $rp($th->nominal) }}</div>
                                        <div class="tnum" style="font-size:12.5px; font-weight:700;">Bonus {{ $rp($th->nominal_bonus) }}</div>
                                        <span class="chip {{ $cls }}" style="margin-top:4px; display:inline-block;">{{ $label }}</span>
                                        @if (auth()->user()->hasAdminAccess() && $th->dalam_window)
                                            <div style="margin-top:4px; display:flex; gap:4px; flex-wrap:wrap;">
                                                <button type="button" class="btn" style="width:auto; font-size:10.5px; padding:3px 8px;" onclick="openTahapModal({{ $ref->id }}, {{ $t }}, '{{ addslashes($ref->mitra->nama) }}', '{{ $th->bulan_label }}', {{ $th->nominal_bonus }}, '{{ $th->status }}')">Ubah Status</button>
                                                <button type="button" class="btn" style="width:auto; font-size:10.5px; padding:3px 8px;" onclick="openPrintImModal({{ $ref->id }}, {{ $t }}, '{{ addslashes($ref->mitra->nama) }}')">Print IM</button>
                                            </div>
                                        @endif
                                    @endif
                                </td>
                            @endforeach
                            <td class="tnum" style="font-weight:700;">{{ $rp($ref->totalBonusBerhak()) }}</td>
                            @if (auth()->user()->hasAdminAccess())
                                <td>
                                    <form method="POST" action="{{ route('akuisisi-referral.destroy', $ref) }}" onsubmit="return confirm('Hapus tracking akuisisi {{ addslashes($ref->mitra->nama) }}? Status pembayaran yang sudah tercatat ikut terhapus.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger" style="width:auto; font-size:11px; padding:4px 9px;">Hapus</button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="8" style="color:var(--ink-muted);">Belum ada mitra yang di-tracking.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if (auth()->user()->hasAdminAccess())
        <div class="modal-overlay" id="modal-tambah-referral" style="display:none;">
            <div class="modal-box">
                <div class="modal-title">Tambah Mitra ke Tracking Referral</div>
                <form method="POST" action="{{ route('akuisisi-referral.store') }}">
                    @csrf
                    <div class="field">
                        <label>Mitra</label>
                        @include('partials.searchable-select', [
                            'name' => 'mitra_id',
                            'options' => $mitraOptions->map(fn ($m) => (object) ['id' => $m->id, 'label' => $m->nama.' ('.($m->kode_mitra ?: 'Belum ada kode').')']),
                            'selectedId' => old('mitra_id'),
                            'placeholder' => 'Ketik buat cari mitra...',
                        ])
                    </div>
                    <div class="field">
                        <label>Pengakuisisi</label>
                        @include('partials.searchable-select', [
                            'name' => 'pengakuisisi_id',
                            'options' => $pengakuisisiOptions->map(fn ($p) => (object) ['id' => $p->id, 'label' => $p->nama]),
                            'selectedId' => old('pengakuisisi_id'),
                            'placeholder' => 'Ketik buat cari pengakuisisi...',
                        ])
                        @if ($pengakuisisiOptions->isEmpty())
                            <div style="font-size:11px; color:var(--critical); margin-top:4px;">Belum ada Pengakuisisi. <a href="{{ route('pengakuisisi.index') }}">Tambahkan dulu di sini</a>.</div>
                        @endif
                    </div>
                    <div class="field">
                        <label>Tanggal Akuisisi</label>
                        <input type="date" name="tanggal_akuisisi" value="{{ old('tanggal_akuisisi', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
                        <div style="font-size:11px; color:var(--ink-muted); margin-top:4px;">Order mitra SEBELUM tanggal ini tidak dihitung sebagai hasil akuisisi.</div>
                    </div>
                    <div class="modal-actions" style="display:flex; gap:8px; justify-content:flex-end;">
                        <button type="button" class="btn" style="width:auto;" onclick="document.getElementById('modal-tambah-referral').style.display='none'">Batal</button>
                        <button type="submit" class="btn btn-primary" style="width:auto;">Simpan</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal-overlay" id="modal-tahap" style="display:none;">
            <div class="modal-box">
                <div class="modal-title" id="tahap-modal-title">Ubah Status Pembayaran</div>
                <form method="POST" id="tahap-form" action="">
                    @csrf
                    <input type="hidden" name="tahap" id="tahap-input">
                    <div id="tahap-info" style="font-size:12.5px; color:var(--ink-muted); margin-bottom:14px; line-height:1.6;"></div>
                    <div class="field">
                        <label>Status</label>
                        <select name="status" id="tahap-status" onchange="document.getElementById('tahap-konfirmasi-wrap').style.display = this.value === 'sudah_bayar' ? 'block' : 'none'">
                            <option value="sudah_bayar">Sudah Dibayar</option>
                            <option value="dibatalkan">Dibatalkan</option>
                            <option value="belum_bayar">Belum Dibayar</option>
                        </select>
                    </div>
                    <div class="field" id="tahap-konfirmasi-wrap">
                        <label style="display:flex; align-items:center; gap:8px; font-weight:400;">
                            <input type="checkbox" name="konfirmasi_tanpa_retur" value="1" style="width:auto;">
                            Saya konfirmasi transaksi ini sudah tervalidasi dan tidak ada retur/pembatalan
                        </label>
                    </div>
                    <div class="field">
                        <label>Catatan (opsional)</label>
                        <input type="text" name="catatan" maxlength="500" placeholder="Misal: alasan pembatalan">
                    </div>
                    <div class="modal-actions" style="display:flex; gap:8px; justify-content:flex-end;">
                        <button type="button" class="btn" style="width:auto;" onclick="document.getElementById('modal-tahap').style.display='none'">Batal</button>
                        <button type="submit" class="btn btn-primary" style="width:auto;">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
        <script>
            function openTahapModal(referralId, tahap, namaMitra, bulanLabel, nominalBonus, statusSekarang) {
                document.getElementById('tahap-form').action = '{{ url('/akuisisi/tracking-referral') }}/' + referralId + '/tahap';
                document.getElementById('tahap-modal-title').textContent = 'Ubah Status — ' + namaMitra + ' (Transaksi ' + tahap + ')';
                document.getElementById('tahap-input').value = tahap;
                document.getElementById('tahap-info').textContent = bulanLabel + ' — Bonus ' + nominalBonus.toLocaleString('id-ID', {style:'currency', currency:'IDR', minimumFractionDigits:0});
                document.getElementById('tahap-status').value = statusSekarang === 'belum_bayar' ? 'sudah_bayar' : statusSekarang;
                document.getElementById('tahap-konfirmasi-wrap').style.display = document.getElementById('tahap-status').value === 'sudah_bayar' ? 'block' : 'none';
                document.getElementById('modal-tahap').style.display = 'flex';
            }
        </script>

        <div class="modal-overlay" id="modal-print-im" style="display:none;">
            <div class="modal-box">
                <div class="modal-title" id="print-im-modal-title">Print Internal Memo</div>
                <form method="GET" id="print-im-form" action="">
                    <input type="hidden" name="tahap" id="print-im-tahap-input">
                    <div class="field">
                        <label>No. Internal Memo</label>
                        <input type="text" name="no_memo" id="print-im-no-memo" maxlength="100" placeholder="mis. 03.288/SRN/X/2026" required>
                        <div style="font-size:11px; color:var(--ink-muted); margin-top:4px;">Diisi manual (bukan dari sistem), sesuai nomor urut surat yang berlaku.</div>
                    </div>
                    <div class="modal-actions" style="display:flex; gap:8px; justify-content:flex-end;">
                        <button type="button" class="btn" style="width:auto;" onclick="document.getElementById('modal-print-im').style.display='none'">Batal</button>
                        <button type="submit" class="btn btn-primary" style="width:auto;">Download .docx</button>
                    </div>
                </form>
            </div>
        </div>
        <script>
            function openPrintImModal(referralId, tahap, namaMitra) {
                document.getElementById('print-im-form').action = '{{ url('/akuisisi/tracking-referral') }}/' + referralId + '/print-im';
                document.getElementById('print-im-modal-title').textContent = 'Print Internal Memo — ' + namaMitra + ' (Transaksi ' + tahap + ')';
                document.getElementById('print-im-tahap-input').value = tahap;
                document.getElementById('print-im-no-memo').value = '';
                document.getElementById('modal-print-im').style.display = 'flex';
            }
        </script>
    @endif

    @include('partials.reveal-on-scroll')
@endsection
