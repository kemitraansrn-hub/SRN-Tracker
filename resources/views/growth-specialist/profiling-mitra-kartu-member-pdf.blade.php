<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 0; size: 85.6mm 53.98mm; }
    * { box-sizing: border-box; }
    body { margin: 0; padding: 0; font-family: sans-serif; width: 85.6mm; }
    table { border-collapse: collapse; width: 100%; }
    td { padding: 0; vertical-align: top; }

    p { line-height: 1; }

    .header-table td { background-color: #0A1226; padding: 1.6mm 4mm; }
    .logo-img { height: 5mm; }
    .badge { color: #FFFFFF; font-weight: bold; font-size: 7pt; letter-spacing: 1px; text-align: right; }

    .body-table td { padding: 1.4mm 4mm; }
    .photo-cell { width: 22mm; }
    .photo-box { width: 18mm; height: 18mm; border: 0.5pt solid #D0D0D0; background-color: #EDEDED; }
    .info-label { color: #999999; font-weight: bold; font-size: 5pt; letter-spacing: 0.5px; margin: 0; }
    .info-nama { color: #1A1A1A; font-weight: bold; font-size: 9.5pt; margin: 0.5mm 0 1.3mm; }
    .info-id { color: #333333; font-size: 7pt; margin: 0 0 1.5mm; }
    .info-line { color: #333333; font-size: 6.5pt; margin: 0 0 0.8mm; }

    .brands-table td { background-color: #FFFFFF; border-top: 0.5pt solid #EAEAEA; border-bottom: 0.5pt solid #EAEAEA; padding: 0.8mm 2mm; text-align: center; width: 25%; }
    .brands-table img { height: 3.2mm; }

    .footer-table td { background-color: #0A1226; color: #8FB4F5; font-size: 4.6pt; text-align: center; padding: 0.6mm 2mm; }
</style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td style="width:60%;"><img class="logo-img" src="{{ public_path('images/srn-logo-full.png') }}"></td>
            <td class="badge" style="width:40%;">MEMBER</td>
        </tr>
    </table>

    <table class="body-table">
        <tr>
            <td class="photo-cell">
                <div class="photo-box">
                    @if ($profil->fotoUrl())
                        <img src="{{ public_path('storage/'.$profil->foto) }}" style="width:18mm; height:18mm;">
                    @endif
                </div>
            </td>
            <td>
                <p class="info-label">NAMA MITRA</p>
                <p class="info-nama">{{ $mitra->nama }}</p>
                <p class="info-label">ID MITRA</p>
                <p class="info-id">{{ $mitra->kode_mitra }}</p>
                <p class="info-line">{{ $profil->no_wa ?? '-' }}</p>
                <p class="info-line">{{ $profil->domisili_kota ? $profil->domisili_kota.($provinsi ? ', '.$provinsi : '') : '-' }}</p>
            </td>
        </tr>
    </table>

    <table class="brands-table">
        <tr>
            <td><img src="{{ public_path('images/brands/reglow.png') }}"></td>
            <td><img src="{{ public_path('images/brands/amura.png') }}"></td>
            <td><img src="{{ public_path('images/brands/but.png') }}"></td>
            <td><img src="{{ public_path('images/brands/purela.png') }}"></td>
        </tr>
    </table>

    <table class="footer-table">
        <tr>
            <td>Kartu ini milik SRN Partner Network &mdash; hubungi KAE jika ditemukan</td>
        </tr>
    </table>
</body>
</html>
