<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 0; size: 53.98mm 85.6mm; }
    * { box-sizing: border-box; }
    body { margin: 0; padding: 0; font-family: sans-serif; width: 53.98mm; height: 85.6mm; position: relative; }

    .bg { position: absolute; top: 0; left: 0; width: 53.98mm; height: 85.6mm; }

    .top { position: relative; padding-top: 2.6mm; text-align: center; }
    .logos { border-collapse: collapse; margin: 0 auto 1.6mm; }
    .logos td { width: 4mm; height: 4mm; border-radius: 50%; background-color: #FFFFFF; text-align: center; vertical-align: middle; padding: 0 0.5mm; }
    .logos img { width: 2.8mm; }
    .pill {
        display: inline-block; border: 0.25mm solid rgba(255,255,255,0.5); border-radius: 999px;
        padding: 0.9mm 3mm; font-size: 6.5pt; font-weight: bold; color: #FFFFFF;
    }

    .frame-wrap { position: relative; margin: 10mm auto 0; width: 68%; }
    .mc-card { background-color: #FFFFFF; border-radius: 1.6mm; }
    .photo-slot { width: 100%; height: 22mm; background-color: #EDEDED; }
    .info { padding: 2.2mm 2.6mm 1.8mm; }
    .nama { color: #1A1247; font-weight: bold; font-size: 12pt; margin: 0; line-height: 1.16; }
    .idpill { display: inline-block; background-color: #0A1226; color: #FFFFFF; border-radius: 999px; padding: 0.8mm 2.6mm; font-size: 6.5pt; font-weight: bold; margin-top: 1.3mm; }
    .sub { font-size: 6.2pt; color: #8A8A8A; margin-top: 0.8mm; }

    .photo-pop { position: absolute; top: 15.37mm; left: 16.05mm; width: 21.89mm; height: 30mm; z-index: 2; }

    .quote { position: relative; margin-top: 3.5mm; background-color: rgba(0,0,0,0.28); padding: 2mm 3mm; text-align: center; font-style: italic; color: #FFFFFF; font-size: 6.6pt; }
</style>
</head>
<body>
    <img class="bg" src="{{ public_path('images/kartu-member-bg.png') }}">

    <div class="top">
        <table class="logos" align="center">
            <tr>
                <td><img src="{{ public_path('images/srn-icon.png') }}"></td>
                <td><img src="{{ public_path('images/brands/reglow.png') }}"></td>
                <td><img src="{{ public_path('images/brands/amura.png') }}"></td>
                <td><img src="{{ public_path('images/brands/but.png') }}"></td>
                <td><img src="{{ public_path('images/brands/purela.png') }}"></td>
            </tr>
        </table>
        <div class="pill">Sinergi Retail Network</div>
    </div>

    <div class="frame-wrap">
        <div class="mc-card">
            <div class="photo-slot"></div>
            <div class="info">
                <p class="nama">{{ $mitra->nama }}</p>
                <div class="idpill">{{ $mitra->kode_mitra }}</div>
                <p class="sub">{{ $profil->no_wa ?? '-' }} &middot; {{ $profil->domisili_kota ?? '-' }}</p>
            </div>
        </div>
    </div>

    @if ($profil->fotoUrl())
        <img class="photo-pop" src="{{ public_path('storage/'.$profil->foto) }}">
    @endif

    <div class="quote">&ldquo;Tumbuh Bersama, Sukses Bersama&rdquo;</div>
</body>
</html>
