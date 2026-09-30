@extends('layouts.app')

@php
    $rp = fn ($v) => $v === null ? '—' : 'Rp'.number_format((float) $v, 0, ',', '.');
    $statusChip = function (string $status) {
        return match ($status) {
            'kode' => '<span class="chip chip-good">Cocok (Kode)</span>',
            'nama' => '<span class="chip chip-good">Cocok (Nama)</span>',
            default => '<span class="chip chip-warn">Mitra Baru</span>',
        };
    };
@endphp

@section('content')
    <h1 class="display" style="font-size:24px;">Preview Import Target Bulanan</h1>
    <div style="color:var(--ink-muted); font-size:13px; margin-top:4px; margin-bottom:20px;">
        Periode <b>{{ $periodeLabel }}</b> — cek dulu hasil pencocokan mitranya sebelum disimpan. Belum ada yang masuk ke database.
    </div>

    <div class="card" style="margin-bottom:20px; display:flex; gap:24px; flex-wrap:wrap;">
        <div>
            <div class="info-label">Total Baris</div>
            <div class="tnum" style="font-size:20px; font-weight:700;">{{ count($rows) }}</div>
        </div>
        <div>
            <div class="info-label">Cocok Mitra Existing</div>
            <div class="tnum" style="font-size:20px; font-weight:700; color:var(--good);">{{ $jumlahCocok }}</div>
        </div>
        <div>
            <div class="info-label">Akan Jadi Mitra Baru</div>
            <div class="tnum" style="font-size:20px; font-weight:700; color:{{ $jumlahBaru > 0 ? 'var(--warn)' : 'var(--ink)' }};">{{ $jumlahBaru }}</div>
        </div>
    </div>

    @if ($jumlahBaru > 0)
        <div class="alert-error" style="background:var(--warn-soft); color:var(--warn);">
            {{ $jumlahBaru }} nama mitra gak ketemu persis di Data Mitra — kalau dilanjutkan, mereka tetap disimpan sebagai mitra baru TANPA kode mitra (targetnya gak hilang), tapi nanti perlu diisi manual kode mitra-nya di menu Data Mitra. Ada notifikasi buat ingetin.
        </div>
    @endif

    <section class="card table-card" style="padding:0; margin-top:16px;">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Baris</th><th>Nama Mitra (file)</th><th>Status</th><th>KAE</th><th>Segmen</th>
                        <th>Komit</th><th>Target</th><th>Stretch</th><th>Tier</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $r)
                        <tr>
                            <td class="tnum">{{ $r['_baris'] }}</td>
                            <td>
                                <div style="font-weight:600;">{{ $r['nama'] ?? '—' }}</div>
                                @if ($r['match_status'] !== 'baru')
                                    <div style="font-size:11px; color:var(--ink-muted);">&rarr; {{ $r['matched_nama'] }} ({{ $r['matched_kode'] ?? '—' }})</div>
                                @endif
                            </td>
                            <td>{!! $statusChip($r['match_status']) !!}</td>
                            <td>{{ $r['kae'] ?? '—' }}</td>
                            <td>{{ $r['segmen'] ?? '—' }}</td>
                            <td class="tnum">{{ $rp($r['komit'] ?? null) }}</td>
                            <td class="tnum">{{ $rp($r['target'] ?? null) }}</td>
                            <td class="tnum">{{ $rp($r['stretch'] ?? null) }}</td>
                            <td>{{ $r['tier_dipakai'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <div style="display:flex; gap:10px; margin-top:20px;">
        <a href="{{ route('import.index') }}" class="btn" style="width:auto;">Batal</a>
        <form method="POST" action="{{ route('import.store') }}">
            @csrf
            <input type="hidden" name="confirm_token" value="{{ $token }}">
            <button type="submit" class="btn btn-primary" style="width:auto;">Konfirmasi &amp; Simpan</button>
        </form>
    </div>
@endsection
