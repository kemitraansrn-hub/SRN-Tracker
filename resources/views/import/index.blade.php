@extends('layouts.app')

@section('content')
    <h1 class="display" style="font-size:24px;">Import Data</h1>
    <div style="color:var(--ink-muted); font-size:13px; margin-top:4px; margin-bottom:24px;">
        Unggah data penjualan harian (sheet Master Transaksi + Master Detail Transaksi dalam satu file).
    </div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    <section class="card" style="margin-bottom:20px;">
        <form method="POST" action="{{ route('import.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="field-row" id="jenisFields">
                <div class="field" style="margin-bottom:0;">
                    <label>Jenis Data</label>
                    <select class="select-pill" name="jenis" id="jenisSelect" onchange="toggleTargetFields()">
                        <option value="order_harian">Order Harian</option>
                        <option value="target_bulanan">Target Bulanan</option>
                    </select>
                </div>
                <div class="field" style="margin-bottom:0; display:none;" id="bulanField">
                    <label>Bulan</label>
                    <select class="select-pill" name="bulan">
                        @foreach (['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'] as $i => $namaBulan)
                            <option value="{{ $i + 1 }}" {{ (old('bulan') ?: now()->month) == $i + 1 ? 'selected' : '' }}>{{ $namaBulan }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="margin-bottom:0; display:none;" id="tahunField">
                    <label>Tahun</label>
                    <select class="select-pill" name="tahun">
                        @for ($y = now()->year - 1; $y <= now()->year + 1; $y++)
                            <option value="{{ $y }}" {{ (old('tahun') ?: now()->year) == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                </div>
                <div class="field" style="margin-bottom:0; justify-content:flex-end;">
                    <label>&nbsp;</label>
                    <a href="{{ route('import.template', 'order_harian') }}" id="templateLink" class="btn" style="width:auto; display:inline-flex; align-items:center; gap:6px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v13.5"/><path d="M7 12l5 5 5-5" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 20h16" stroke-linecap="round"/></svg>
                        Download Template
                    </a>
                </div>
            </div>

            <label class="dropzone" style="display:block; margin-top:16px;">
                <input type="file" name="file" accept=".xlsx,.xls,.csv" required style="display:none;" onchange="this.closest('form').querySelector('.dropzone-title').textContent = this.files[0]?.name ?? 'Tarik file ke sini, atau klik untuk pilih'">
                <div class="dropzone-title">Tarik file ke sini, atau klik untuk pilih</div>
                <div class="dropzone-hint">Format .xlsx, .xls, atau .csv &middot; maksimal 10MB</div>
            </label>

            <button type="submit" class="btn btn-primary" style="margin-top:16px; width:auto;">Upload &amp; Proses</button>
        </form>
    </section>

    <script>
        const templateUrls = {
            order_harian: "{{ route('import.template', 'order_harian') }}",
            target_bulanan: "{{ route('import.template', 'target_bulanan') }}",
        };
        function toggleTargetFields() {
            const jenis = document.getElementById('jenisSelect').value;
            const isTarget = jenis === 'target_bulanan';
            document.getElementById('bulanField').style.display = isTarget ? '' : 'none';
            document.getElementById('tahunField').style.display = isTarget ? '' : 'none';
            document.getElementById('templateLink').href = templateUrls[jenis];
        }
        toggleTargetFields();
    </script>

    <section class="card table-card">
        <div class="card-head">
            <div class="card-title">Riwayat Import</div>
            <div class="card-hint">{{ $history->count() }} import terakhir</div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Tanggal Data</th><th>Jenis</th><th>Nama File</th><th>Diupload Oleh</th>
                        <th>Waktu</th><th>Baris</th><th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($history as $batch)
                        <tr>
                            <td class="tnum">
                                @if ($batch->tanggal_data)
                                    {{ $batch->tanggal_data->format('d/m/Y') }}
                                @elseif ($batch->bulan && $batch->tahun)
                                    {{ \Carbon\Carbon::create($batch->tahun, $batch->bulan)->translatedFormat('F Y') }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $batch->jenis === 'order_harian' ? 'Order Harian' : 'Target Bulanan' }}</td>
                            <td>{{ $batch->nama_file }}</td>
                            <td>{{ $batch->uploader->name ?? '—' }}</td>
                            <td class="tnum">{{ $batch->created_at->format('d/m/Y H:i') }}</td>
                            <td class="tnum">{{ $batch->jumlah_baris }}</td>
                            <td>
                                @if ($batch->status === 'berhasil')
                                    <span class="chip chip-good">Berhasil</span>
                                @elseif ($batch->status === 'ditimpa')
                                    <span class="chip chip-warn">Menimpa data lama</span>
                                @else
                                    <span class="chip chip-critical">Gagal</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" style="color:var(--ink-muted);">Belum ada riwayat import.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($pending)
        <div class="modal-overlay">
            <div class="modal-box">
                <div class="modal-title">Data tanggal ini sudah ada</div>
                <div class="modal-body">
                    Data untuk tanggal <b>{{ \Carbon\Carbon::parse($pending['tanggal_data'])->format('d/m/Y') }}</b> sudah pernah diimpor
                    @if ($pending['last_batch'])
                        ({{ $pending['last_batch']->jumlah_baris }} baris, oleh {{ $pending['last_batch']->uploader->name ?? '—' }} pukul {{ $pending['last_batch']->created_at->format('H:i') }}).
                    @else
                        .
                    @endif
                    File baru ini berisi {{ $pending['jumlah_baris_baru'] }} baris. Mengunggah akan
                    <b>menimpa seluruh data</b> tanggal tersebut. Tindakan ini tercatat di riwayat import.
                </div>
                <div class="modal-actions">
                    <a href="{{ route('import.index') }}" class="btn">Batal</a>
                    <form method="POST" action="{{ route('import.store') }}">
                        @csrf
                        <input type="hidden" name="confirm_token" value="{{ $pending['token'] }}">
                        <button type="submit" class="btn btn-danger">Ya, Timpa Data</button>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection
