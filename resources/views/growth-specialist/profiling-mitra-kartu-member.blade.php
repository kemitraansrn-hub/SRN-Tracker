@extends('layouts.app')

@section('content')
    <style>
        @media print {
            .sidebar, .mobile-topbar, .sidebar-backdrop, .kartu-no-print { display: none !important; }
            .shell { display: block !important; }
            .main { padding: 0 !important; height: auto !important; overflow: visible !important; }
            .main-inner { max-width: none !important; }
            @page { size: 85.6mm 53.98mm; margin: 0; }
        }
        .member-card {
            width: 85.6mm; height: 53.98mm; border-radius: 3mm; overflow: hidden;
            background: #FFFFFF; position: relative; font-family: Arial, Helvetica, sans-serif;
            box-shadow: 0 4px 16px rgba(0,0,0,0.18); margin: 0 auto;
        }
        .member-card-header {
            background: #0A1226; padding: 2.2mm 4mm; display: flex; justify-content: space-between; align-items: center;
        }
        .member-card-logo-img { height: 6.5mm; width: auto; display: block; }
        .member-card-badge { color: #FFFFFF; font-weight: 700; font-size: 2.3mm; letter-spacing: 0.5px; }
        .member-card-body { display: flex; gap: 3mm; padding: 2.2mm 4mm; }
        .member-card-photo {
            width: 19mm; height: 21mm; border-radius: 2mm; background: #EDEDED; border: 1px solid #D0D0D0;
            flex: none; overflow: hidden; display: flex; align-items: center; justify-content: center;
        }
        .member-card-photo img { width: 100%; height: 100%; object-fit: cover; }
        .member-card-info { min-width: 0; flex: 1; }
        .member-card-info-label { font-size: 1.7mm; font-weight: 700; color: #999999; letter-spacing: 0.3px; margin-bottom: 0.3mm; }
        .member-card-nama { font-size: 3.2mm; font-weight: 700; color: #1A1A1A; margin-bottom: 1.3mm; line-height: 1.15; }
        .member-card-id { font-size: 2.3mm; color: #333333; margin-bottom: 1.5mm; }
        .member-card-line { display: flex; align-items: flex-start; gap: 1.3mm; margin-bottom: 1mm; font-size: 2.1mm; color: #333333; line-height: 1.3; }
        .member-card-brands {
            display: flex; align-items: center; justify-content: space-evenly;
            padding: 1mm 4mm; border-top: 1px solid #EAEAEA; border-bottom: 1px solid #EAEAEA;
        }
        .member-card-brands img { height: 4.2mm; width: auto; max-width: 15mm; object-fit: contain; }
        .member-card-footer {
            position: absolute; left: 0; right: 0; bottom: 0; background: #0A1226;
            color: #8FB4F5; font-size: 1.6mm; text-align: center; padding: 0.8mm 2mm;
        }
    </style>

    <div class="kartu-no-print" style="display:flex; justify-content:space-between; align-items:center; gap:16px; margin-bottom:16px;">
        <a href="{{ route('growth-specialist.profiling-mitra.edit', $mitra) }}" style="display:inline-flex; align-items:center; gap:6px; font-size:12.5px; color:var(--ink-muted); text-decoration:none;">
            &larr; Kembali ke Form
        </a>
        <a href="{{ route('growth-specialist.profiling-mitra.kartu-member.pdf', $mitra) }}" class="btn btn-primary" style="width:auto; padding:8px 18px; text-decoration:none; display:inline-block;">Download PDF</a>
    </div>
    <div class="kartu-no-print card-hint" style="text-align:center; margin-bottom:16px;">Ini pratinjau di layar &mdash; buat cetak/kirim ke mitra, pakai "Download PDF" (ukurannya presisi kartu ID, gak tergantung setting printer).</div>

    <div class="card kartu-no-print" style="max-width:500px; margin:0 auto 24px; text-align:center; padding:24px; background:var(--surface-alt);">
        <div class="member-card">
            <div class="member-card-header">
                <img src="{{ asset('images/srn-logo-full-white-text.png') }}" alt="SRN Sinergi Retail Network" class="member-card-logo-img">
                <div class="member-card-badge">MEMBER</div>
            </div>
            <div class="member-card-body">
                <div class="member-card-photo">
                    @if ($profil->fotoUrl())
                        <img src="{{ $profil->fotoUrl() }}" alt="Foto {{ $mitra->nama }}">
                    @else
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#B8B8B8" stroke-width="1.6"><circle cx="12" cy="8" r="4"/><path d="M4 20c1-4 4-6 8-6s7 2 8 6" stroke-linecap="round"/></svg>
                    @endif
                </div>
                <div class="member-card-info">
                    <div class="member-card-info-label">NAMA MITRA</div>
                    <div class="member-card-nama">{{ $mitra->nama }}</div>
                    <div class="member-card-info-label">ID MITRA</div>
                    <div class="member-card-id">{{ $mitra->kode_mitra }}</div>
                    <div class="member-card-line">
                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#0F6E56" stroke-width="2.2" style="flex:none; margin-top:0.3mm;"><path d="M20 15.5c-1.2 0-2.4-.2-3.5-.6-.3-.1-.7 0-1 .2l-2.2 2.2c-2.8-1.4-5.1-3.7-6.6-6.6l2.2-2.2c.3-.3.4-.7.2-1-.4-1.1-.6-2.3-.6-3.5 0-.6-.4-1-1-1H4c-.6 0-1 .4-1 1 0 9.4 7.6 17 17 17 .6 0 1-.4 1-1v-3.5c0-.6-.4-1-1-1z"/></svg>
                        <span>{{ $profil->no_wa ?? '—' }}</span>
                    </div>
                    <div class="member-card-line">
                        <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="#185FA5" stroke-width="2.2" style="flex:none; margin-top:0.3mm;"><path d="M12 21s-7-6.2-7-11.5C5 5.9 8.1 3 12 3s7 2.9 7 6.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.3"/></svg>
                        <span>{{ $profil->domisili_kota ? $profil->domisili_kota.($provinsi ? ', '.$provinsi : '') : '—' }}</span>
                    </div>
                </div>
            </div>
            <div class="member-card-brands">
                <img src="{{ asset('images/brands/reglow.png') }}" alt="Reglow">
                <img src="{{ asset('images/brands/amura.png') }}" alt="Amura">
                <img src="{{ asset('images/brands/but.png') }}" alt="B.U.T">
                <img src="{{ asset('images/brands/purela.png') }}" alt="Purela">
            </div>
            <div class="member-card-footer">Kartu ini milik SRN Partner Network &mdash; hubungi KAE jika ditemukan</div>
        </div>
    </div>
@endsection
