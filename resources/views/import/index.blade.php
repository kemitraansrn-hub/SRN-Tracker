@extends('layouts.app')

@section('content')
    <h1 class="display" style="font-size:24px;">Import Data</h1>
    <div style="color:var(--ink-muted); font-size:13px; margin-top:4px; margin-bottom:24px;">
        Unggah data penjualan (sheet Master Transaksi + Master Detail Transaksi dalam satu file).
    </div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif

    @if (session('import_skipped'))
        <div class="card" style="margin-bottom:20px; border-color:var(--warn);">
            <div class="card-title" style="color:var(--warn); margin-bottom:10px;">{{ count(session('import_skipped')) }} baris dilewati saat import</div>
            <div style="max-height:220px; overflow-y:auto; font-size:12px; color:var(--ink-muted); line-height:1.7;">
                @foreach (session('import_skipped') as $reason)
                    <div>{{ $reason }}</div>
                @endforeach
            </div>
        </div>
    @endif

    <section class="card" style="margin-bottom:20px;">
        <form method="POST" action="{{ route('import.store') }}" enctype="multipart/form-data" id="importForm">
            @csrf
            <div class="field-row" id="jenisFields">
                <div class="field" style="margin-bottom:0;">
                    <label>Jenis Data</label>
                    <select class="select-pill" name="jenis" id="jenisSelect" onchange="toggleTargetFields()">
                        <option value="order_harian">Order Harian</option>
                        <option value="order_historis">Order Historis (banyak tanggal)</option>
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

            <label class="dropzone" id="dropzone" style="display:block; margin-top:16px;">
                <input type="file" name="file" id="fileInput" accept=".xlsx,.xls,.csv" style="display:none;">
                <div class="dropzone-title" id="dropzoneTitle">Tarik file ke sini, atau klik untuk pilih</div>
                <div class="dropzone-hint" id="dropzoneHint">Format .xlsx, .xls, atau .csv &middot; maksimal 10MB</div>
            </label>
            <div class="field-error" id="fileError" style="display:none;">Pilih file terlebih dahulu.</div>

            <button type="submit" class="btn btn-primary" style="margin-top:16px; width:auto;" id="submitBtn">Upload &amp; Proses</button>
        </form>
    </section>

    <script>
        const templateUrls = {
            order_harian: "{{ route('import.template', 'order_harian') }}",
            order_historis: "{{ route('import.template', 'order_harian') }}",
            target_bulanan: "{{ route('import.template', 'target_bulanan') }}",
        };
        const dropzoneHints = {
            order_harian: 'Format .xlsx, .xls, atau .csv · 1 file = 1 tanggal · maksimal 10MB',
            order_historis: 'Format .xlsx, .xls, atau .csv · boleh banyak tanggal sekaligus · maksimal 50MB',
            target_bulanan: 'Format .xlsx, .xls, atau .csv · maksimal 10MB',
        };
        function toggleTargetFields() {
            const jenis = document.getElementById('jenisSelect').value;
            const isTarget = jenis === 'target_bulanan';
            document.getElementById('bulanField').style.display = isTarget ? '' : 'none';
            document.getElementById('tahunField').style.display = isTarget ? '' : 'none';
            document.getElementById('templateLink').href = templateUrls[jenis];
            document.getElementById('dropzoneHint').textContent = dropzoneHints[jenis];
        }
        toggleTargetFields();

        const fileInput = document.getElementById('fileInput');
        const dropzone = document.getElementById('dropzone');
        const dropzoneTitle = document.getElementById('dropzoneTitle');
        const fileError = document.getElementById('fileError');

        function showChosenFile() {
            dropzoneTitle.textContent = fileInput.files[0]?.name ?? 'Tarik file ke sini, atau klik untuk pilih';
            if (fileInput.files[0]) {
                fileError.style.display = 'none';
            }
        }

        fileInput.addEventListener('change', showChosenFile);

        ['dragover', 'dragenter'].forEach(evt => dropzone.addEventListener(evt, (e) => {
            e.preventDefault();
            dropzone.style.borderColor = 'var(--accent)';
        }));
        ['dragleave', 'dragend'].forEach(evt => dropzone.addEventListener(evt, (e) => {
            e.preventDefault();
            dropzone.style.borderColor = '';
        }));
        dropzone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropzone.style.borderColor = '';
            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                showChosenFile();
            }
        });

        document.getElementById('importForm').addEventListener('submit', (e) => {
            if (! fileInput.files.length) {
                e.preventDefault();
                fileError.style.display = '';
                return;
            }
            fileError.style.display = 'none';
            document.getElementById('submitBtn').textContent = 'Memproses...';
            document.getElementById('submitBtn').disabled = true;
        });
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
                                @elseif ($batch->catatan)
                                    {{ $batch->catatan }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @switch($batch->jenis)
                                    @case('order_harian') Order Harian @break
                                    @case('order_historis') Order Historis @break
                                    @default Target Bulanan
                                @endswitch
                            </td>
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

    @if ($pending && ($pending['jenis'] ?? 'order_harian') === 'order_historis')
        <div class="modal-overlay">
            <div class="modal-box">
                <div class="modal-title">Sudah ada data di periode ini</div>
                <div class="modal-body">
                    Sudah ada <b>{{ $pending['existing_count'] }} order</b> tercatat antara
                    <b>{{ \Carbon\Carbon::parse($pending['tanggal_mulai'])->format('d/m/Y') }}</b> s/d
                    <b>{{ \Carbon\Carbon::parse($pending['tanggal_selesai'])->format('d/m/Y') }}</b>.
                    File baru ini berisi {{ $pending['jumlah_baris_baru'] }} baris. Mengunggah akan
                    <b>menimpa seluruh data order</b> di rentang tanggal tersebut. Tindakan ini tercatat di riwayat import.
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
    @elseif ($pending)
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
