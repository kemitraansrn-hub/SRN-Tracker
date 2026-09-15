@extends('layouts.app')

@section('content')
    <style>
        .member-card {
            width: 53.98mm; height: 85.6mm; border-radius: 4.3mm; overflow: hidden;
            position: relative; font-family: Arial, Helvetica, sans-serif;
            background:
                radial-gradient(rgba(255,255,255,0.10) 1px, transparent 1.4px) 0 0 / 3.2mm 3.2mm,
                linear-gradient(165deg, #16264f 0%, #0A1226 42%, #3a1b52 100%);
            box-shadow: 0 8px 24px rgba(0,0,0,0.28); margin: 0 auto;
        }
        .mc-top { padding: 2.6mm 3mm 0; text-align: center; }
        .mc-logos { display: flex; justify-content: center; gap: 1mm; margin-bottom: 1.6mm; }
        .mc-logo-badge {
            width: 4mm; height: 4mm; border-radius: 50%; background: rgba(255,255,255,0.92);
            display: flex; align-items: center; justify-content: center; flex: none;
        }
        .mc-logo-badge img { width: 2.9mm; height: 2.9mm; object-fit: contain; display: block; }
        .mc-pill {
            display: inline-block; border: 0.25mm solid rgba(255,255,255,0.45); border-radius: 999px;
            padding: 0.9mm 3mm; font-size: 6.5pt; font-weight: 700; color: #FFFFFF; letter-spacing: 0.2px;
        }
        .mc-frame-wrap { position: relative; margin: 15mm auto 0; width: 68%; }
        .mc-card { background: #FFFFFF; border-radius: 1.6mm; overflow: hidden; box-shadow: 0 3mm 6mm rgba(0,0,0,0.35); }
        .mc-photo-slot { width: 100%; height: 31.2mm; background: #EDEDED; }
        .mc-info { padding: 2.2mm 2.6mm 2.6mm; }
        .mc-nama { color: #1A1247; font-weight: 800; font-size: 11pt; line-height: 1.16; }
        .mc-id {
            display: inline-flex; align-items: center; background: #0A1226; color: #FFFFFF;
            border-radius: 999px; padding: 0.8mm 2.6mm; font-size: 6.5pt; font-weight: 700; margin-top: 1.3mm;
        }
        .mc-sub { font-size: 6.2pt; color: #8A8A8A; margin-top: 1.1mm; }
        .mc-photo-pop {
            position: absolute; top: -13mm; left: 50%; transform: translateX(-50%);
            width: 88%; z-index: 2; filter: drop-shadow(0 2mm 3mm rgba(0,0,0,0.4));
        }
        .mc-quote {
            margin-top: 3.5mm; background: rgba(0,0,0,0.28); padding: 2.4mm 3mm;
            text-align: center; font-style: italic; color: #FFFFFF; font-size: 6.6pt;
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
            <div class="mc-top">
                <div class="mc-logos">
                    <div class="mc-logo-badge"><img src="{{ asset('images/srn-icon.png') }}" alt="SRN"></div>
                    <div class="mc-logo-badge"><img src="{{ asset('images/brands/reglow.png') }}" alt="Reglow"></div>
                    <div class="mc-logo-badge"><img src="{{ asset('images/brands/amura.png') }}" alt="Amura"></div>
                    <div class="mc-logo-badge"><img src="{{ asset('images/brands/but.png') }}" alt="B.U.T"></div>
                    <div class="mc-logo-badge"><img src="{{ asset('images/brands/purela.png') }}" alt="Purela"></div>
                </div>
                <div class="mc-pill">Sinergi Retail Network</div>
            </div>

            <div class="mc-frame-wrap">
                <div class="mc-card">
                    <div class="mc-photo-slot"></div>
                    <div class="mc-info">
                        <div class="mc-nama">{{ $mitra->nama }}</div>
                        <div class="mc-id">{{ $mitra->kode_mitra }}</div>
                        <div class="mc-sub">{{ $profil->no_wa ?? '—' }} &middot; {{ $profil->domisili_kota ?? '—' }}</div>
                    </div>
                </div>
                @if ($profil->fotoUrl())
                    <img class="mc-photo-pop" src="{{ $profil->fotoUrl() }}" alt="Foto {{ $mitra->nama }}">
                @endif
            </div>

            <div class="mc-quote">&ldquo;Tumbuh Bersama, Sukses Bersama&rdquo;</div>
        </div>
    </div>
@endsection
