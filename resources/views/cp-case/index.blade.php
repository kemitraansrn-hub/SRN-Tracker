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
                        <th>Status Kasus</th><th>Takedown / Banding</th><th></th>
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
                            <td><span class="chip {{ $statusChip($c->status_kasus) }}">{{ $c->status_kasus }}</span></td>
                            <td>
                                @if ($c->takedownBanding)
                                    <span class="chip chip-warn">Banding: {{ $c->takedownBanding->status_banding ?? '—' }}</span>
                                @elseif ($c->approval_takedown)
                                    <span class="chip chip-good">Takedown Disetujui</span>
                                @else
                                    <span style="color:var(--ink-faint); font-size:12px;">—</span>
                                @endif
                            </td>
                            <td>
                                <div style="display:flex; gap:6px;">
                                    <a href="{{ route('tracking-cp.edit', $c) }}" class="link-action" style="color:var(--accent-ink); font-size:12px; font-weight:600; text-decoration:none; border:1px solid var(--line); border-radius:7px; padding:5px 9px;">Edit</a>
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
@endsection
