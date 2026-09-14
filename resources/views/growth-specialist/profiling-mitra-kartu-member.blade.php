@extends('layouts.app')

@section('content')
    <style>
        .member-card {
            width: 53.98mm; height: 85.6mm; border-radius: 4.3mm; overflow: hidden;
            background: #0A1226; position: relative; font-family: Arial, Helvetica, sans-serif;
            box-shadow: 0 8px 24px rgba(0,0,0,0.28); margin: 0 auto;
        }
        .member-card-blob { position: absolute; top: 0; left: 0; }
        .member-card-brand {
            position: absolute; top: 2.9mm; left: 3.2mm; color: #FFFFFF; font-weight: 800;
            font-size: 15px; letter-spacing: 0.3px;
        }
        .member-card-brand-sub {
            position: absolute; top: 6.8mm; left: 3.2mm; color: #C7D6EC; font-size: 7px; letter-spacing: 0.3px;
        }
        .member-card-dots {
            position: absolute; display: grid; gap: 0.7mm;
            grid-template-columns: repeat(3, 0.9mm); grid-template-rows: repeat(3, 0.9mm);
        }
        .member-card-dots span { width: 0.55mm; height: 0.55mm; border-radius: 50%; background: rgba(255,255,255,0.35); display: block; }
        .member-card-dots-v { position: absolute; display: flex; flex-direction: column; justify-content: space-between; }
        .member-card-dots-v span { width: 0.7mm; height: 0.7mm; border-radius: 50%; background: rgba(255,255,255,0.3); display: block; }
        .member-card-photo {
            position: absolute; top: 10.8mm; left: 50%; transform: translateX(-50%);
            width: 32.4mm; height: 41.4mm;
        }
        .member-card-photo img {
            width: 100%; height: 100%; object-fit: contain; display: block;
            filter: drop-shadow(0 5mm 6mm rgba(0,0,0,0.45));
        }
        .member-card-photo-empty {
            width: 100%; height: 100%; border-radius: 2.9mm; overflow: hidden;
            box-shadow: 0 5mm 8mm rgba(0,0,0,0.45), 0 0 0 1mm rgba(255,255,255,0.06);
        }
        .member-card-idblock { position: absolute; top: 54mm; left: 0; right: 0; text-align: center; }
        .member-card-dashline { border-top: 0.3mm dashed #EF8F20; width: 70%; margin: 0 auto 2.5mm; }
        .member-card-nama { color: #FFFFFF; font-weight: 800; font-size: 15px; line-height: 1.2; }
        .member-card-id { color: #EF8F20; font-weight: 600; font-size: 9px; letter-spacing: 0.4px; margin-top: 0.4mm; }
        .member-card-lines { position: absolute; top: 66.9mm; left: 0; right: 0; padding: 0 5mm; }
        .member-card-line { display: flex; align-items: center; gap: 1.1mm; margin-bottom: 1.1mm; }
        .member-card-line-icon {
            width: 2.5mm; height: 2.5mm; border-radius: 50%; background: rgba(239,143,32,0.18);
            display: flex; align-items: center; justify-content: center; flex: none;
        }
        .member-card-line span.txt { color: #DCE3F0; font-size: 9px; }
        .member-card-footer {
            position: absolute; bottom: 0; left: 0; right: 0; height: 7.9mm; background: #FFFFFF;
            display: flex; align-items: center; justify-content: space-evenly; padding: 0 2.5mm;
        }
        .member-card-footer img { max-height: 4mm; max-width: 10mm; object-fit: contain; }
        .member-card-accentbar { position: absolute; bottom: 7.9mm; left: 0; right: 0; height: 0.9mm; background: #EF8F20; }
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
            <svg class="member-card-blob" width="150" height="72" viewBox="0 0 300 145">
                <path d="M0,40 C40,10 60,70 100,60 C150,45 140,-10 200,10 C250,25 260,90 300,70 L300,0 L0,0 Z" fill="#EF8F20"/>
            </svg>
            <svg class="member-card-blob" style="top:50mm;" width="150" height="36" viewBox="0 0 300 72">
                <path d="M0,20 C50,-5 40,45 90,40 C160,32 170,0 230,15 C270,25 280,50 300,40 L300,72 L0,72 Z" fill="#EF8F20" opacity="0.9"/>
            </svg>

            <div class="member-card-brand">SRN</div>
            <div class="member-card-brand-sub">Sinergi Retail Network</div>

            <div class="member-card-dots" style="top:2.5mm; right:2.9mm;">
                <span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span>
            </div>

            <div class="member-card-photo">
                @if ($profil->fotoUrl())
                    <img src="{{ $profil->fotoUrl() }}" alt="Foto {{ $mitra->nama }}">
                @else
                    <div class="member-card-photo-empty" style="background:#EDEDED; display:flex; align-items:center; justify-content:center;">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#B8B8B8" stroke-width="1.6"><circle cx="12" cy="8" r="4"/><path d="M4 20c1-4 4-6 8-6s7 2 8 6" stroke-linecap="round"/></svg>
                    </div>
                @endif
            </div>

            <div class="member-card-dots-v" style="top:56.7mm; left:3.6mm; width:2.5mm; height:6.8mm;">
                <span></span><span></span><span></span><span></span>
            </div>

            <div class="member-card-idblock">
                <div class="member-card-dashline"></div>
                <div class="member-card-nama">{{ $mitra->nama }}</div>
                <div class="member-card-id">{{ $mitra->kode_mitra }}</div>
            </div>

            <div class="member-card-lines">
                <div class="member-card-line">
                    <span class="member-card-line-icon">
                        <svg width="7" height="7" viewBox="0 0 24 24" fill="none" stroke="#EF8F20" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                    </span>
                    <span class="txt">{{ $profil->no_wa ?? '—' }}</span>
                </div>
                <div class="member-card-line">
                    <span class="member-card-line-icon">
                        <svg width="7" height="7" viewBox="0 0 24 24" fill="none" stroke="#EF8F20" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    </span>
                    <span class="txt">{{ $profil->domisili_kota ? $profil->domisili_kota.($provinsi ? ', '.$provinsi : '') : '—' }}</span>
                </div>
            </div>

            <div class="member-card-accentbar"></div>
            <div class="member-card-footer">
                <img src="{{ asset('images/brands/reglow.png') }}" alt="Reglow">
                <img src="{{ asset('images/brands/amura.png') }}" alt="Amura">
                <img src="{{ asset('images/brands/but.png') }}" alt="B.U.T">
                <img src="{{ asset('images/brands/purela.png') }}" alt="Purela">
            </div>
        </div>
    </div>
@endsection
