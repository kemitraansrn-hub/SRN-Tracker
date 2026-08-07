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
            <div class="field-row">
                <div class="field" style="margin-bottom:0;">
                    <label>Jenis Data</label>
                    <select class="select-pill" disabled>
                        <option>Order Harian</option>
                    </select>
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
                            <td class="tnum">{{ optional($batch->tanggal_data)->format('d/m/Y') ?? '—' }}</td>
                            <td>{{ $batch->jenis === 'order_harian' ? 'Order Harian' : $batch->jenis }}</td>
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
