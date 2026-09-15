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

    .frame-wrap { position: relative; margin: 2mm auto 0; width: 68%; }
    .mc-card { background-color: #FFFFFF; border-radius: 1.6mm; }
    .photo-box-wrap { text-align: center; }
    .photo-box { display: inline-block; background-color: #EDEDED; padding: 1.6mm; }
    .info { padding: 1.2mm 2.4mm 1.4mm; }
    .nama { color: #1A1247; font-weight: bold; font-size: 9.5pt; margin: 0; line-height: 1.14; }
    .idpill { display: inline-block; background-color: #0A1226; color: #FFFFFF; border-radius: 999px; padding: 0.5mm 2mm; font-size: 5.6pt; font-weight: bold; margin-top: 0.8mm; }
    .line-table { border-collapse: collapse; margin-top: 0.5mm; }
    .line-table td { padding: 0; vertical-align: middle; }
    .line-icon-td { width: 2.3mm; padding-right: 0.8mm; }
    .line-icon-td img { width: 2.1mm; height: 2.1mm; display: block; }
    .line-text-td { font-size: 5.4pt; color: #8A8A8A; }

    .quote { position: relative; margin-top: 1.2mm; background-color: rgba(0,0,0,0.28); padding: 1.4mm 3mm; text-align: center; font-style: italic; color: #FFFFFF; font-size: 6pt; }
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
            @php
                $mcCardWidthMm = 53.98 * 0.68;
                $photoBoxHeightMm = 32;
                $photoPaddingMm = 1.6;
                $photoHeightMm = $photoBoxHeightMm - 2 * $photoPaddingMm;
                $photoWidthMm = $photoHeightMm;
                if ($profil->fotoUrl()) {
                    $photoAbsPath = public_path('storage/'.$profil->foto);
                    $photoDims = @getimagesize($photoAbsPath);
                    if ($photoDims && $photoDims[0] > 0 && $photoDims[1] > 0) {
                        $photoRatio = $photoDims[0] / $photoDims[1];
                        $photoWidthMm = round($photoHeightMm * $photoRatio, 2);
                        $maxPhotoBoxWidthMm = 32;
                        if ($photoWidthMm + 2 * $photoPaddingMm > $maxPhotoBoxWidthMm) {
                            $photoWidthMm = $maxPhotoBoxWidthMm - 2 * $photoPaddingMm;
                            $photoHeightMm = round($photoWidthMm / $photoRatio, 2);
                        }
                    }
                }
                $photoBoxWidthMm = $photoWidthMm + 2 * $photoPaddingMm;
                $photoSideGapMm = round(($mcCardWidthMm - $photoBoxWidthMm) / 2, 2);
            @endphp
            <div class="photo-box-wrap" style="padding-top: {{ $photoSideGapMm }}mm;">
                <div class="photo-box">
                    @if ($profil->fotoUrl())
                        <img style="width: {{ $photoWidthMm }}mm; height: {{ $photoHeightMm }}mm;" src="{{ $photoAbsPath }}">
                    @endif
                </div>
            </div>
            <div class="info">
                <p class="nama">{{ $mitra->nama }}</p>
                <div class="idpill">{{ $mitra->kode_mitra }}</div>
                <table class="line-table">
                    <tr>
                        <td class="line-icon-td"><img src="{{ public_path('images/icons/icon-phone.png') }}"></td>
                        <td class="line-text-td">{{ $profil->no_wa ?? '-' }}</td>
                    </tr>
                </table>
                <table class="line-table">
                    <tr>
                        <td class="line-icon-td"><img src="{{ public_path('images/icons/icon-map.png') }}"></td>
                        <td class="line-text-td">{{ $profil->domisili_kota ?? '-' }}</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <div class="quote">&ldquo;Tumbuh Bersama, Sukses Bersama&rdquo;</div>
</body>
</html>
