<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 0; size: 85.6mm 53.98mm; }
    body { margin: 0; padding: 0; font-family: sans-serif; }
    .card { position: relative; width: 85.6mm; height: 53.98mm; }

    .header { position: absolute; top: 0; left: 0; width: 85.6mm; height: 11mm; background-color: #0A1226; }
    .logo-img { position: absolute; top: 2.3mm; left: 4mm; height: 6.4mm; }
    .badge { position: absolute; top: 4.2mm; right: 4mm; color: #FFFFFF; font-weight: bold; font-size: 7.5pt; letter-spacing: 1px; }

    .photo-box { position: absolute; top: 13mm; left: 4mm; width: 19mm; height: 21mm; border: 0.5pt solid #D0D0D0; background-color: #EDEDED; }
    .photo-box img { width: 19mm; height: 21mm; }

    .info { position: absolute; top: 13mm; left: 26mm; width: 56mm; }
    .info-label { color: #999999; font-weight: bold; font-size: 5.5pt; letter-spacing: 0.5px; margin: 0; }
    .info-nama { color: #1A1A1A; font-weight: bold; font-size: 11pt; margin: 0.5mm 0 2mm; }
    .info-id { color: #333333; font-size: 8pt; margin: 0 0 2.5mm; }
    .info-line { color: #333333; font-size: 7.5pt; margin: 0 0 1.5mm; }

    .brands { position: absolute; top: 38mm; left: 0; width: 85.6mm; height: 7mm; background-color: #FFFFFF; border-top: 0.5pt solid #EAEAEA; border-bottom: 0.5pt solid #EAEAEA; text-align: center; }
    .brands img { height: 4.2mm; margin: 1.4mm 3mm 0; }

    .footer { position: absolute; bottom: 0; left: 0; width: 85.6mm; height: 3.6mm; background-color: #0A1226; color: #8FB4F5; font-size: 5.2pt; text-align: center; padding-top: 0.8mm; }
</style>
</head>
<body>
    <div class="card">
        <div class="header"></div>
        <img class="logo-img" src="{{ public_path('images/srn-logo-full.png') }}">
        <div class="badge">MEMBER</div>

        <div class="photo-box">
            @if ($profil->fotoUrl())
                <img src="{{ public_path('storage/'.$profil->foto) }}">
            @endif
        </div>

        <div class="info">
            <p class="info-label">NAMA MITRA</p>
            <p class="info-nama">{{ $mitra->nama }}</p>
            <p class="info-label">ID MITRA</p>
            <p class="info-id">{{ $mitra->kode_mitra }}</p>
            <p class="info-line">{{ $profil->no_wa ?? '-' }}</p>
            <p class="info-line">{{ $profil->domisili_kota ? $profil->domisili_kota.($provinsi ? ', '.$provinsi : '') : '-' }}</p>
        </div>

        <div class="brands">
            <img src="{{ public_path('images/brands/reglow.png') }}">
            <img src="{{ public_path('images/brands/amura.png') }}">
            <img src="{{ public_path('images/brands/but.png') }}">
            <img src="{{ public_path('images/brands/purela.png') }}">
        </div>

        <div class="footer">Kartu ini milik SRN Partner Network &mdash; hubungi KAE jika ditemukan</div>
    </div>
</body>
</html>
