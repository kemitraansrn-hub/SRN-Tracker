@extends('layouts.app')

@php
    $canEdit = auth()->user()->hasAdminAccess();
@endphp

@section('content')
    <div style="display:flex; flex-wrap:wrap; justify-content:space-between; align-items:flex-end; gap:12px; margin-bottom:20px;">
        <div>
            <h1 class="display" style="font-size:24px;">Performa Channel</h1>
            <div style="color:var(--ink-muted); font-size:13px; margin-top:4px;">Input manual Lead, Prospect, Closing, Biaya &amp; Investasi Mitra per channel per bulan &mdash; sumber data Dashboard &gt; Akuisisi.</div>
        </div>
        @include('partials.bulan-tahun-filter', ['action' => route('akuisisi-channel-performance.index'), 'bulan' => $bulan, 'tahun' => $tahun, 'isBulanIni' => $isBulanIni])
    </div>

    @if (session('status'))
        <div class="alert-success" style="margin-bottom:16px;">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert-error" style="margin-bottom:16px;">{{ $errors->first() }}</div>
    @endif

    <section class="card">
        <div class="card-head" style="margin-bottom:14px;">
            <div class="card-title">Performa per Channel</div>
            <div class="card-hint">{{ \Carbon\Carbon::create($tahun, $bulan, 1)->translatedFormat('F Y') }}</div>
        </div>

        <div class="field" style="max-width:360px;">
            <label>Pilih Channel</label>
            <select class="select-pill" id="cp-channel-select" onchange="bukaModalDariSelect(this)">
                <option value="">&mdash; Pilih channel &mdash;</option>
                @foreach ($rows as $row)
                    @php
                        $ch = $row['channel'];
                        $d = $row['data'];
                        $lead = (int) ($d->lead ?? 0);
                        $prospect = (int) ($d->prospect ?? 0);
                        $closing = (int) ($d->closing ?? 0);
                        $biaya = (float) ($d->biaya ?? 0);
                        $investasi = (float) ($d->investasi_mitra ?? 0);
                        $sudahDiisi = ($lead > 0 || $prospect > 0 || $closing > 0 || $biaya > 0 || $investasi > 0);
                    @endphp
                    <option value="{{ $ch->id }}"
                        data-nama="{{ $ch->name }}"
                        data-lead="{{ $lead }}" data-prospect="{{ $prospect }}" data-closing="{{ $closing }}"
                        data-biaya="{{ $biaya }}" data-investasi="{{ $investasi }}">
                        {{ $ch->name }} {{ $sudahDiisi ? '— Sudah diisi' : '— Belum diisi' }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="table-scroll" style="margin-top:18px;">
            <table>
                <thead>
                    <tr><th>Channel</th><th>Status</th><th>Lead</th><th>Prospect</th><th>Closing</th></tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php
                            $ch = $row['channel'];
                            $d = $row['data'];
                            $lead = (int) ($d->lead ?? 0);
                            $prospect = (int) ($d->prospect ?? 0);
                            $closing = (int) ($d->closing ?? 0);
                            $sudahDiisi = ($lead > 0 || $prospect > 0 || $closing > 0 || (float) ($d->biaya ?? 0) > 0 || (float) ($d->investasi_mitra ?? 0) > 0);
                        @endphp
                        <tr>
                            <td style="font-weight:600;">{{ $ch->name }}</td>
                            <td><span class="chip {{ $sudahDiisi ? 'chip-good' : 'chip-neutral' }}">&#9679; {{ $sudahDiisi ? 'Sudah diisi' : 'Belum diisi' }}</span></td>
                            <td class="tnum">{{ $lead }}</td>
                            <td class="tnum">{{ $prospect }}</td>
                            <td class="tnum">{{ $closing }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" style="color:var(--ink-muted);">Belum ada channel aktif.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="modal-overlay" id="modal-cp" style="display:none;">
        <div class="modal-box">
            <div class="modal-title" id="cp-modal-title">Input Data</div>
            <form method="POST" action="{{ route('akuisisi-channel-performance.store') }}" id="cp-form">
                @csrf
                <input type="hidden" name="bulan" value="{{ $bulan }}">
                <input type="hidden" name="tahun" value="{{ $tahun }}">
                <div class="field">
                    <label>Lead</label>
                    <input type="number" min="0" step="1" id="cp-f-lead" {{ $canEdit ? '' : 'disabled' }}>
                </div>
                <div class="field">
                    <label>Prospect</label>
                    <input type="number" min="0" step="1" id="cp-f-prospect" {{ $canEdit ? '' : 'disabled' }}>
                </div>
                <div class="field">
                    <label>Closing</label>
                    <input type="number" min="0" step="1" id="cp-f-closing" {{ $canEdit ? '' : 'disabled' }}>
                </div>
                <div class="field">
                    <label>Biaya (Rp)</label>
                    <input type="number" min="0" step="0.01" id="cp-f-biaya" {{ $canEdit ? '' : 'disabled' }}>
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label>Investasi Mitra (Rp)</label>
                    <input type="number" min="0" step="0.01" id="cp-f-investasi" {{ $canEdit ? '' : 'disabled' }}>
                </div>
                <div class="modal-actions" style="margin-top:16px;">
                    <button type="button" class="btn" style="width:auto;" onclick="tutupModalChannel()">Batal</button>
                    @if ($canEdit)
                        <button type="submit" class="btn btn-primary" style="width:auto;">Simpan</button>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <script>
        function bukaModalDariSelect(sel) {
            if (! sel.value) return;
            const opt = sel.options[sel.selectedIndex];
            bukaModalChannel(
                parseInt(sel.value, 10), opt.dataset.nama,
                opt.dataset.lead, opt.dataset.prospect, opt.dataset.closing,
                opt.dataset.biaya, opt.dataset.investasi
            );
        }

        function bukaModalChannel(id, nama, lead, prospect, closing, biaya, investasi) {
            document.getElementById('cp-modal-title').textContent = 'Input Data — ' + nama;

            const map = { lead: lead, prospect: prospect, closing: closing, biaya: biaya, investasi: investasi };
            Object.keys(map).forEach(function (key) {
                const input = document.getElementById('cp-f-' + key);
                input.value = map[key];
                input.name = 'data[' + id + '][' + (key === 'investasi' ? 'investasi_mitra' : key) + ']';
            });

            document.getElementById('modal-cp').style.display = '';
        }

        function tutupModalChannel() {
            document.getElementById('modal-cp').style.display = 'none';
            document.getElementById('cp-channel-select').value = '';
        }

        document.getElementById('modal-cp').addEventListener('mousedown', function (e) {
            if (e.target === this) tutupModalChannel();
        });
    </script>
@endsection
