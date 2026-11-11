@extends('layouts.app')

@section('content')
    <div style="display:flex; flex-wrap:wrap; justify-content:space-between; align-items:flex-end; gap:12px; margin-bottom:20px;">
        <div>
            <h1 class="display" style="font-size:24px;">Tracking Webinar</h1>
            <div style="color:var(--ink-muted); font-size:13px; margin-top:4px;">Log sesi webinar &amp; rekap jumlah peserta per bulan.</div>
        </div>
        <div style="display:flex; gap:8px; align-items:center;">
            <form method="GET" action="{{ route('tracking-webinar.index') }}">
                <select name="tahun" class="select-pill" onchange="this.form.submit()">
                    @foreach ($tahunOptions as $opt)
                        <option value="{{ $opt }}" {{ $tahun === (int) $opt ? 'selected' : '' }}>{{ $opt }}</option>
                    @endforeach
                </select>
            </form>
            <button type="button" class="btn btn-primary" style="width:auto;" onclick="document.getElementById('webinar-add-modal').style.display='flex'">+ Tambah Webinar</button>
        </div>
    </div>

    @if (session('status'))
        <div class="alert-success" style="margin-bottom:16px;">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert-error" style="margin-bottom:16px;">{{ $errors->first() }}</div>
    @endif

    <section class="card table-card" style="padding:0; margin-bottom:20px;">
        <div class="card-head" style="padding:18px 20px 0; margin-bottom:12px;">
            <div class="card-title">Rekap Jumlah Peserta per Bulan</div>
            <div class="card-hint">Tahun {{ $tahun }} &middot; dijumlah dari semua sesi webinar nama itu di bulan tsb</div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Webinar</th>
                        @foreach ($months as $m)
                            <th>{{ $monthLabels[$m] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td style="font-weight:600;">{{ $row['nama'] }}</td>
                            @foreach ($months as $m)
                                <td class="tnum" style="{{ $m === now()->month && $tahun === now()->year ? 'background:var(--accent-soft);' : '' }}">{{ number_format($row['counts'][$m], 0, ',', '.') }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                    <tr style="font-weight:700; background:var(--surface-alt);">
                        <td>Total</td>
                        @foreach ($months as $m)
                            <td class="tnum" style="{{ $m === now()->month && $tahun === now()->year ? 'background:var(--accent-soft);' : '' }}">{{ number_format($totalPerMonth[$m], 0, ',', '.') }}</td>
                        @endforeach
                    </tr>
                    <tr style="font-size:12px; color:var(--ink-muted);">
                        <td>% Growth vs bulan lalu</td>
                        @foreach ($months as $m)
                            @php $g = $growthPerMonth[$m]; @endphp
                            <td class="tnum" style="{{ $m === now()->month && $tahun === now()->year ? 'background:var(--accent-soft);' : '' }}">
                                @if ($g === null)
                                    &mdash;
                                @else
                                    <span style="color:{{ $g >= 0 ? 'var(--good)' : 'var(--critical)' }}; font-weight:600;">{{ $g >= 0 ? '▲' : '▼' }} {{ number_format(abs($g), 1, ',', '.') }}%</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card table-card" style="padding:0;">
        <div class="card-head" style="padding:18px 20px;">
            <div class="card-title">Riwayat Sesi Webinar ({{ $items->count() }})</div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th><th>Webinar</th><th>Jumlah Peserta</th><th>Bukti Zoom</th><th>Diinput Oleh</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            <td class="tnum">{{ $item->tanggal_webinar->translatedFormat('d M Y') }}</td>
                            <td>{{ $item->nama_webinar }}</td>
                            <td class="tnum">{{ number_format($item->jumlah_peserta, 0, ',', '.') }}</td>
                            <td><a href="{{ $item->bukti_zoom_url }}" target="_blank" rel="noopener" style="color:var(--accent-ink);">Lihat bukti</a></td>
                            <td style="color:var(--ink-muted);">{{ $item->creator->name ?? '—' }}</td>
                            <td>
                                <div style="display:flex; gap:6px;">
                                    <a href="{{ route('tracking-webinar.edit', $item) }}" class="btn" style="width:auto; font-size:11.5px; padding:5px 10px;">Edit</a>
                                    <form method="POST" action="{{ route('tracking-webinar.destroy', $item) }}" onsubmit="return confirm('Hapus data webinar {{ $item->nama_webinar }} tanggal {{ $item->tanggal_webinar->format('d/m/Y') }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger" style="width:auto; font-size:11.5px; padding:5px 10px;">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" style="color:var(--ink-muted);">Belum ada data webinar di tahun {{ $tahun }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div id="webinar-add-modal" style="display:none; position:fixed; inset:0; background:rgba(27,34,44,0.45); z-index:100; align-items:center; justify-content:center; padding:20px;">
        <div class="card" style="max-width:420px; width:100%;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h2 style="font-size:17px; font-weight:700; margin:0;">Tambah Webinar</h2>
                <button type="button" onclick="document.getElementById('webinar-add-modal').style.display='none'" style="background:none; border:none; font-size:20px; cursor:pointer; color:var(--ink-muted); line-height:1;">&times;</button>
            </div>
            <form method="POST" action="{{ route('tracking-webinar.store') }}">
                @csrf
                <div class="field">
                    <label>Nama Webinar</label>
                    <select name="nama_webinar" required>
                        <option value="">Pilih webinar...</option>
                        @foreach ($namaOptions as $opt)
                            <option value="{{ $opt }}" {{ old('nama_webinar') === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label>Tanggal Webinar</label>
                    <input type="date" name="tanggal_webinar" value="{{ old('tanggal_webinar', now()->toDateString()) }}" required>
                </div>
                <div class="field">
                    <label>Jumlah Peserta</label>
                    <input type="number" name="jumlah_peserta" min="0" value="{{ old('jumlah_peserta') }}" required>
                </div>
                <div class="field">
                    <label>Bukti Zoom (link Google Drive)</label>
                    <input type="url" name="bukti_zoom_url" placeholder="https://drive.google.com/..." value="{{ old('bukti_zoom_url') }}" required>
                </div>
                <div style="display:flex; gap:10px; margin-top:4px;">
                    <button type="submit" class="btn btn-primary" style="width:auto;">Simpan</button>
                    <button type="button" class="btn" style="width:auto;" onclick="document.getElementById('webinar-add-modal').style.display='none'">Batal</button>
                </div>
            </form>
        </div>
    </div>

    @if ($errors->any() && old('nama_webinar') !== null)
        <script>document.getElementById('webinar-add-modal').style.display = 'flex';</script>
    @endif
@endsection
