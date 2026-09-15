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
        .mc-frame-wrap { position: relative; margin: 2mm auto 0; width: 68%; }
        .mc-card { background: #FFFFFF; border-radius: 1.6mm; overflow: hidden; box-shadow: 0 3mm 6mm rgba(0,0,0,0.35); }
        .mc-photo-box-wrap { padding-top: 1mm; text-align: center; }
        .mc-photo-box { display: inline-block; background: #EDEDED; padding: 2mm; box-sizing: border-box; border-radius: 1mm; }
        .mc-photo-box img { display: block; }
        .mc-info { padding: 1.2mm 2.4mm 1.4mm; }
        .mc-nama { color: #1A1247; font-weight: 800; font-size: 8.5pt; line-height: 1.14; }
        .mc-id {
            display: inline-flex; align-items: center; background: #0A1226; color: #FFFFFF;
            border-radius: 999px; padding: 0.5mm 2mm; font-size: 5.6pt; font-weight: 700; margin-top: 0.8mm;
        }
        .mc-sub { font-size: 5.4pt; color: #8A8A8A; margin-top: 0.6mm; }
        .mc-quote {
            margin-top: 2mm; background: rgba(0,0,0,0.28); padding: 1.8mm 3mm;
            text-align: center; font-style: italic; color: #FFFFFF; font-size: 6.2pt;
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
                    <div class="mc-photo-box-wrap">
                        <div class="mc-photo-box">
                            @if ($profil->fotoUrl())
                                @php
                                    $photoBoxHeightMm = 38;
                                    $photoPaddingMm = 2;
                                    $photoHeightMm = $photoBoxHeightMm - 2 * $photoPaddingMm;
                                    $photoWidthMm = $photoHeightMm;
                                    $photoAbsPath = storage_path('app/public/'.$profil->foto);
                                    $photoDims = @getimagesize($photoAbsPath);
                                    if ($photoDims && $photoDims[0] > 0 && $photoDims[1] > 0) {
                                        $photoRatio = $photoDims[0] / $photoDims[1];
                                        $photoWidthMm = round($photoHeightMm * $photoRatio, 2);
                                        $maxPhotoBoxWidthMm = 40;
                                        if ($photoWidthMm + 2 * $photoPaddingMm > $maxPhotoBoxWidthMm) {
                                            $photoWidthMm = $maxPhotoBoxWidthMm - 2 * $photoPaddingMm;
                                            $photoHeightMm = round($photoWidthMm / $photoRatio, 2);
                                        }
                                    }
                                @endphp
                                <img src="{{ $profil->fotoUrl() }}" style="width: {{ $photoWidthMm }}mm; height: {{ $photoHeightMm }}mm;" alt="Foto {{ $mitra->nama }}">
                            @else
                                <div style="width: 26mm; height: 26mm; display: flex; align-items: center; justify-content: center;">
                                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#B8B8B8" stroke-width="1.6"><circle cx="12" cy="8" r="4"/><path d="M4 20c1-4 4-6 8-6s7 2 8 6" stroke-linecap="round"/></svg>
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="mc-info">
                        <div class="mc-nama">{{ $mitra->nama }}</div>
                        <div class="mc-id">{{ $mitra->kode_mitra }}</div>
                        <div class="mc-sub">{{ $profil->no_wa ?? '—' }} &middot; {{ $profil->domisili_kota ?? '—' }}</div>
                    </div>
                </div>
            </div>

            <div class="mc-quote">&ldquo;Tumbuh Bersama, Sukses Bersama&rdquo;</div>
        </div>
    </div>
@endsection
