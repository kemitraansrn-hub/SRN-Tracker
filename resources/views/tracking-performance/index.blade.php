@extends('layouts.app')

@php
    $rp = fn ($v) => 'Rp'.number_format((float) $v, 0, ',', '.');
    $pct = fn ($v) => $v === null ? '—' : number_format($v, 2, ',', '.').'%';
    $bulanNama = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $adaFilter = request('q') || request('bulan') || request('tahun') || request('week');
@endphp

@section('content')
    <h1 class="display" style="font-size:24px; margin-bottom:4px;">Tracking Performance</h1>
    <div class="card-hint" style="margin-bottom:16px;">Upload file performa toko mitra (per mitra, per periode). CTR, CVR, dan ROAS dihitung otomatis: CTR = Produk Diklik / Total Pengunjung, CVR = Total Pesanan / Produk Diklik, ROAS = GMV / Ads Spend.</div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert-error">{{ $errors->first() }}</div>
    @endif
    @if (session('import_skipped'))
        <div class="card" style="margin-bottom:20px; border-color:var(--warn);">
            <div class="card-title" style="color:var(--warn); margin-bottom:10px;">{{ count(session('import_skipped')) }} baris dilewati saat upload</div>
            <div style="max-height:220px; overflow-y:auto; font-size:12px; color:var(--ink-muted); line-height:1.7;">
                @foreach (session('import_skipped') as $reason)
                    <div>{{ $reason }}</div>
                @endforeach
            </div>
        </div>
    @endif

    <section class="card" style="max-width:720px; margin-bottom:20px;">
        <div class="card-title" style="margin-bottom:12px;">Upload File Performance</div>
        <form method="POST" action="{{ route('growth-specialist.tracking-performance.upload') }}" enctype="multipart/form-data" class="field-row" style="align-items:flex-end; margin-bottom:0;">
            @csrf
            <div class="field" style="margin-bottom:0; flex:1; min-width:240px;">
                <label>File Excel (.xlsx), sheet "Pesanan Dibuat"</label>
                <input type="file" name="file" accept=".xlsx,.xls" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width:auto;">Upload</button>
            <a href="{{ route('growth-specialist.tracking-performance.template') }}" class="btn" style="width:auto;">Download Template</a>
        </form>
        <div class="card-hint" style="margin-top:10px;">Kolom: Kode Mitra, Nama Mitra, Week, Kuartal, Tanggal, Total Penjualan (IDR), Total Pesanan, Produk Diklik, Total Pengunjung, Ads Spend (IDR). Upload ulang mitra + periode yang sama memperbarui baris lama.</div>
    </section>

    <section class="card table-card" style="padding:0;">
        <div class="card-head" style="padding:18px 20px 0; margin-bottom:12px; flex-wrap:wrap; gap:12px;">
            <div>
                <div class="card-title">Hasil Upload</div>
                <div class="card-hint">{{ $rowsPage->total() }} baris{{ $adaFilter ? ' (hasil filter)' : '' }}</div>
            </div>
            <form method="GET" action="{{ route('growth-specialist.tracking-performance') }}" class="field-row" style="margin-bottom:0; align-items:flex-end;">
                <div class="field" style="margin-bottom:0;">
                    <label>Cari Mitra</label>
                    <input type="text" name="q" value="{{ $q }}" placeholder="Nama atau kode mitra...">
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label>Bulan</label>
                    <select name="bulan" class="select-pill" onchange="this.form.submit()">
                        <option value="">Semua Bulan</option>
                        @foreach ($bulanNama as $i => $nama)
                            <option value="{{ $i + 1 }}" {{ (int) request('bulan') === $i + 1 ? 'selected' : '' }}>{{ $nama }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label>Tahun</label>
                    <select name="tahun" class="select-pill" onchange="this.form.submit()">
                        <option value="">Semua Tahun</option>
                        @foreach ($tahunOptions as $t)
                            <option value="{{ $t }}" {{ (int) request('tahun') === (int) $t ? 'selected' : '' }}>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label>Week</label>
                    <select name="week" class="select-pill" onchange="this.form.submit()">
                        <option value="">Semua Week</option>
                        @foreach ($weekOptions as $w)
                            <option value="{{ $w }}" {{ request('week') === (string) $w ? 'selected' : '' }}>{{ $w }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn" style="width:auto;">Cari</button>
                @if ($adaFilter)
                    <a href="{{ route('growth-specialist.tracking-performance') }}" class="btn" style="width:auto;">Reset</a>
                @endif
            </form>
        </div>
        <div class="card-hint" style="padding:0 20px 12px;">Filter bulan/tahun mengikuti tanggal akhir periode.</div>

        <div class="table-scroll" style="max-height:none; overflow-y:visible;">
            <table>
                <thead>
                    <tr>
                        <th>Mitra</th><th>KAE</th><th>Week</th><th>Kuartal</th><th>Periode</th>
                        <th>GMV</th><th>Pesanan</th><th>Produk Diklik</th><th>Traffic</th>
                        <th>CTR</th><th>CVR</th><th>Ads Spend</th><th>ROAS</th>
                        <th>Catatan KAE</th><th>&Delta; GMV</th><th>&Delta; Traffic</th><th>&Delta; CTR</th><th>&Delta; CVR</th><th>Growth</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rowsPage as $r)
                        <tr>
                            <td>
                                <div style="font-weight:600;">{{ $r->mitra->nama ?? '—' }}</div>
                                <div style="font-size:11.5px; color:var(--ink-muted);">{{ $r->mitra->kode_mitra ?? '' }}</div>
                            </td>
                            <td>{{ ($r->mitra?->kae_code ? ($kaeMap[$r->mitra->kae_code] ?? $r->mitra->kae_code) : null) ?? '—' }}</td>
                            <td>{{ $r->week ?? '—' }}</td>
                            <td>{{ $r->kuartal ?? '—' }}</td>
                            <td style="white-space:nowrap;">{{ $r->periodeLabel() }}</td>
                            <td class="tnum">{{ $rp($r->gmv) }}</td>
                            <td class="tnum">{{ number_format($r->total_pesanan, 0, ',', '.') }}</td>
                            <td class="tnum">{{ number_format($r->produk_diklik, 0, ',', '.') }}</td>
                            <td class="tnum">{{ number_format($r->total_pengunjung, 0, ',', '.') }}</td>
                            <td class="tnum">{{ $pct($r->ctr()) }}</td>
                            <td class="tnum">{{ $pct($r->cvr()) }}</td>
                            <td class="tnum">{{ $r->ads_spend > 0 ? $rp($r->ads_spend) : '—' }}</td>
                            <td class="tnum">{{ number_format($r->roas(), 2, ',', '.') }}</td>
                            <td style="color:var(--ink-faint);">—</td>
                            <td style="color:var(--ink-faint);">—</td>
                            <td style="color:var(--ink-faint);">—</td>
                            <td style="color:var(--ink-faint);">—</td>
                            <td style="color:var(--ink-faint);">—</td>
                            <td style="color:var(--ink-faint);">—</td>
                            <td>
                                <form method="POST" action="{{ route('growth-specialist.tracking-performance.destroy', $r) }}" onsubmit="return confirm('Hapus data performa {{ addslashes($r->mitra->nama ?? 'mitra') }} periode {{ $r->periodeLabel() }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger" style="width:auto; font-size:11.5px; padding:5px 10px;">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="20" style="color:var(--ink-muted);">{{ $adaFilter ? 'Tidak ada data yang cocok dengan filter.' : 'Belum ada data. Upload file performance di atas.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div style="margin-top:16px;">{{ $rowsPage->links() }}</div>
@endsection
