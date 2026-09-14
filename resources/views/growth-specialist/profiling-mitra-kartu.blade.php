@extends('layouts.app')

@php
    $tipeMitra = $profil->tipeMitra();
    $pct = $profil->persenOperationalCost();
    $tol = $profil->toleransiCashflow();
    $deadlineClosing = $profil->deadlineClosingPertama();
    $skorTotal = ($profil->motivasi ?? 0) + ($profil->kemampuan ?? 0) + ($profil->keaktifan ?? 0);
    $adaSkorLengkap = $profil->motivasi !== null && $profil->kemampuan !== null && $profil->keaktifan !== null;

    $indikator = [
        'Motivasi' => 'Antusiasme di sesi, growth / fix mindset',
        'Kemampuan' => 'Modal + kapabilitas operasional',
        'Keaktifan' => 'Responsivitas, ketepatan selesaikan tugas',
    ];
    $skorPerDimensi = ['Motivasi' => $profil->motivasi, 'Kemampuan' => $profil->kemampuan, 'Keaktifan' => $profil->keaktifan];
@endphp

@section('content')
    <style>
        @media print {
            .sidebar, .mobile-topbar, .sidebar-backdrop, .kartu-no-print { display: none !important; }
            .shell { display: block !important; }
            .main { padding: 0 !important; height: auto !important; overflow: visible !important; }
            .main-inner { max-width: none !important; }
        }
        .kartu-wrap { max-width: 720px; margin: 0 auto; }
        .kartu-section-title {
            background: var(--accent); color: #fff; font-weight: 700; font-size: 12.5px;
            text-transform: uppercase; letter-spacing: 0.04em; padding: 8px 14px; border-radius: 6px;
            margin: 20px 0 10px;
        }
        .kartu-row { display: flex; border-bottom: 1px solid var(--line); }
        .kartu-row:last-child { border-bottom: none; }
        .kartu-label {
            flex: 0 0 220px; padding: 8px 12px; font-size: 12.5px; font-weight: 600;
            background: var(--surface-alt); color: var(--ink-muted);
        }
        .kartu-value { flex: 1; padding: 8px 12px; font-size: 13px; }
        .kartu-box { border: 1px solid var(--line); border-radius: 8px; overflow: hidden; }
        .kartu-klasifikasi-table { width: 100%; border-collapse: collapse; }
        .kartu-klasifikasi-table th, .kartu-klasifikasi-table td {
            border: 1px solid var(--line); padding: 8px 10px; font-size: 12.5px; text-align: left;
        }
        .kartu-klasifikasi-table th { background: var(--surface-alt); font-size: 11px; text-transform: uppercase; }
    </style>

    <div class="kartu-no-print" style="display:flex; justify-content:space-between; align-items:center; gap:16px; margin-bottom:16px;">
        <a href="{{ route('growth-specialist.profiling-mitra.edit', $mitra) }}" style="display:inline-flex; align-items:center; gap:6px; font-size:12.5px; color:var(--ink-muted); text-decoration:none;">
            &larr; Kembali ke Form
        </a>
        <button type="button" onclick="window.print()" class="btn btn-primary" style="width:auto; padding:8px 18px;">Cetak / Simpan PDF</button>
    </div>

    <div class="kartu-wrap">
        <div class="card" style="text-align:center; padding:20px; margin-bottom:0;">
            <div class="display" style="font-size:20px; font-weight:700;">KARTU PROFIL MITRA</div>
            <div style="font-size:12px; color:var(--ink-muted); margin-top:2px;">SRN Partner Management | KAE Pendampingan</div>
        </div>

        <div class="kartu-section-title">Identitas Mitra</div>
        <div style="display:flex; gap:14px; align-items:flex-start;">
            @if ($profil->fotoUrl())
                <img src="{{ $profil->fotoUrl() }}" alt="Foto {{ $mitra->nama }}" style="width:96px; height:96px; border-radius:12px; object-fit:cover; border:1px solid var(--line); flex:none;">
            @endif
            <div class="kartu-box" style="flex:1; min-width:0;">
                <div class="kartu-row"><div class="kartu-label">ID Mitra</div><div class="kartu-value">{{ $mitra->kode_mitra }}</div></div>
                <div class="kartu-row"><div class="kartu-label">Nama Mitra</div><div class="kartu-value">{{ $mitra->nama }}</div></div>
                <div class="kartu-row"><div class="kartu-label">KAE PJ</div><div class="kartu-value">{{ $kaeNama ?? '—' }}</div></div>
                <div class="kartu-row"><div class="kartu-label">No. WA</div><div class="kartu-value">{{ $profil->no_wa ?? '—' }}</div></div>
                <div class="kartu-row"><div class="kartu-label">Domisili / Kota</div><div class="kartu-value">{{ $profil->domisili_kota ? $profil->domisili_kota.($provinsi ? ', '.$provinsi : '') : '—' }}</div></div>
                <div class="kartu-row"><div class="kartu-label">Tanggal Onboarding</div><div class="kartu-value">{{ $profil->tanggal_onboarding?->format('d/m/Y') ?? '—' }}</div></div>
                <div class="kartu-row"><div class="kartu-label">Channel</div><div class="kartu-value">{{ $channel ?? '—' }}</div></div>
                <div class="kartu-row"><div class="kartu-label">Status</div><div class="kartu-value">{{ $profil->status ?? '—' }}</div></div>
            </div>
        </div>

        <div class="kartu-section-title">Analisis Kebutuhan Mitra</div>
        <div class="kartu-box">
            <div class="kartu-row"><div class="kartu-label">Modal Awal Siap Diputar</div><div class="kartu-value">{{ $profil->modal_bisnis !== null ? 'Rp'.number_format((float) $profil->modal_bisnis, 0, ',', '.') : '—' }}</div></div>
            <div class="kartu-row"><div class="kartu-label">Toleransi Cashflow</div><div class="kartu-value">{{ $tol ?? '—' }} <span style="color:var(--ink-faint);">(otomatis)</span></div></div>
            <div class="kartu-row"><div class="kartu-label">Struktur Tim</div><div class="kartu-value">{{ $profil->tim_sendiri ?? '—' }}</div></div>
            <div class="kartu-row"><div class="kartu-label">Platform Jualan Saat Ini</div><div class="kartu-value">{{ $profil->platform_jualan ? implode(', ', $profil->platform_jualan) : '—' }}</div></div>
            <div class="kartu-row"><div class="kartu-label">Jam Aktif per Hari</div><div class="kartu-value">{{ $profil->jam_aktif ?? '—' }}</div></div>
            <div class="kartu-row"><div class="kartu-label">Pengalaman Jualan Sebelumnya</div><div class="kartu-value">{{ $profil->pengalaman_jualan ?? '—' }}</div></div>
        </div>

        <div class="kartu-section-title">Channel Fokus (30 Hari Pertama)</div>
        <div class="kartu-box">
            <div class="kartu-row"><div class="kartu-label">Channel 1</div><div class="kartu-value">{{ $profil->channel_fokus_1 ? implode(', ', $profil->channel_fokus_1) : '—' }}</div></div>
            <div class="kartu-row"><div class="kartu-label">Channel 2</div><div class="kartu-value">{{ $profil->channel_fokus_2 ? implode(', ', $profil->channel_fokus_2) : '—' }}</div></div>
            <div class="kartu-row"><div class="kartu-label">Tipe Channel</div><div class="kartu-value">{{ $profil->tipe_channel ?? '—' }}</div></div>
        </div>

        <div class="kartu-section-title">Klasifikasi Mitra</div>
        <table class="kartu-klasifikasi-table">
            <thead>
                <tr><th>Dimensi</th><th>Indikator</th><th style="text-align:center;">Skor (1&ndash;5)</th><th>Catatan KAE</th></tr>
            </thead>
            <tbody>
                @foreach ($indikator as $dimensi => $teksIndikator)
                    <tr>
                        <td style="font-weight:600;">{{ $dimensi }}</td>
                        <td>{{ $teksIndikator }}</td>
                        <td style="text-align:center;">{{ $skorPerDimensi[$dimensi] ?? '—' }}</td>
                        @if ($loop->first)
                            <td rowspan="{{ count($indikator) }}" style="vertical-align:top; white-space:pre-line;">{{ $profil->catatan ?? '—' }}</td>
                        @endif
                    </tr>
                @endforeach
                <tr style="background:var(--surface-alt); font-weight:700;">
                    <td colspan="2">Total Skor Klasifikasi</td>
                    <td style="text-align:center;">{{ $adaSkorLengkap ? $skorTotal.'/15' : '—' }}</td>
                    <td>@if($tipeMitra)&rarr; MITRA {{ mb_strtoupper($tipeMitra) }}@else — @endif</td>
                </tr>
            </tbody>
        </table>

        <div class="kartu-section-title">Penetapan KPI Awal</div>
        <div class="kartu-box" style="margin-bottom:10px;">
            <div class="kartu-row">
                <div class="kartu-label">Tipe Mitra</div>
                <div class="kartu-value">
                    {{ $tipeMitra === 'Prioritas' ? '☑' : '☐' }} PRIORITAS&nbsp;&nbsp;|&nbsp;&nbsp;{{ $tipeMitra === 'Standar' ? '☑' : '☐' }} STANDAR
                    @if(! $tipeMitra) <span style="color:var(--ink-faint);">(data Kekuatan Finansial / Klasifikasi belum lengkap)</span> @endif
                </div>
            </div>
        </div>
        <table class="kartu-klasifikasi-table">
            <thead>
                <tr><th>KPI</th><th>Target</th><th>Deadline</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>Setup Channel (1&ndash;2 channel)</td>
                    <td>{{ collect([$profil->channel_fokus_1, $profil->channel_fokus_2])->flatten()->filter()->unique()->implode(' + ') ?: '—' }}</td>
                    <td>{{ $profil->deadline_setup_channel?->format('d/m/Y') ?? '—' }}</td>
                </tr>
                <tr>
                    <td>Target Traffic Harian</td>
                    <td>{{ $profil->target_traffic ?? '—' }}</td>
                    <td>—</td>
                </tr>
                <tr>
                    <td>Target Leads Harian</td>
                    <td>{{ $profil->target_leads ?? '—' }}</td>
                    <td>—</td>
                </tr>
                <tr>
                    <td>Closing Pertama</td>
                    <td>Min. 1 transaksi confirmed</td>
                    <td>{{ $deadlineClosing?->format('d/m/Y') ?? '—' }}</td>
                </tr>
            </tbody>
        </table>

        <div class="kartu-section-title">Catatan &amp; Tindak Lanjut KAE</div>
        <div class="kartu-box" style="padding:12px 14px; font-size:13px; min-height:60px; white-space:pre-line;">{{ $profil->catatan ?? '—' }}</div>
    </div>
@endsection
