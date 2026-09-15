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

    .frame-wrap { position: relative; margin: 5.5mm auto 0; width: 68%; }
    .mc-card { background-color: #FFFFFF; border-radius: 1.6mm; }
    .photo-box-wrap { padding-top: 1.5mm; text-align: center; }
    .photo-box { display: inline-block; background-color: #EDEDED; padding: 1.6mm; }
    .info { padding: 2.2mm 2.6mm 1.8mm; }
    .nama { color: #1A1247; font-weight: bold; font-size: 12pt; margin: 0; line-height: 1.16; }
    .idpill { display: inline-block; background-color: #0A1226; color: #FFFFFF; border-radius: 999px; padding: 0.8mm 2.6mm; font-size: 6.5pt; font-weight: bold; margin-top: 1.3mm; }
    .sub { font-size: 6.2pt; color: #8A8A8A; margin-top: 0.8mm; }

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
            <div class="photo-box-wrap">
                <div class="photo-box">
                    @if ($profil->fotoUrl())
                        @php
                            $photoBoxHeightMm = 26;
                            $photoPaddingMm = 1.6;
                            $photoHeightMm = $photoBoxHeightMm - 2 * $photoPaddingMm;
                            $photoWidthMm = $photoHeightMm;
                            $photoAbsPath = public_path('storage/'.$profil->foto);
                            $photoDims = @getimagesize($photoAbsPath);
                            if ($photoDims && $photoDims[0] > 0 && $photoDims[1] > 0) {
                                $photoRatio = $photoDims[0] / $photoDims[1];
                                $photoWidthMm = round($photoHeightMm * $photoRatio, 2);
                                $maxPhotoBoxWidthMm = 34;
                                if ($photoWidthMm + 2 * $photoPaddingMm > $maxPhotoBoxWidthMm) {
                                    $photoWidthMm = $maxPhotoBoxWidthMm - 2 * $photoPaddingMm;
                                    $photoHeightMm = round($photoWidthMm / $photoRatio, 2);
                                }
                            }
                        @endphp
                        <img style="width: {{ $photoWidthMm }}mm; height: {{ $photoHeightMm }}mm;" src="{{ $photoAbsPath }}">
                    @endif
                </div>
            </div>
            <div class="info">
                <p class="nama">{{ $mitra->nama }}</p>
                <div class="idpill">{{ $mitra->kode_mitra }}</div>
                <p class="sub">{{ $profil->no_wa ?? '-' }} &middot; {{ $profil->domisili_kota ?? '-' }}</p>
            </div>
        </div>
    </div>

    <div class="quote">&ldquo;Tumbuh Bersama, Sukses Bersama&rdquo;</div>
</body>
</html>
