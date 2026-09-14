<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 0; size: 53.98mm 85.6mm; }
    * { box-sizing: border-box; }
    body { margin: 0; padding: 0; font-family: sans-serif; width: 53.98mm; height: 85.6mm; position: relative; }

    .bg { position: absolute; top: 0; left: 0; width: 53.98mm; height: 85.6mm; }

    .photo { position: absolute; top: 10.8mm; left: 10.79mm; width: 32.4mm; height: 41.4mm; }
    .photo img { width: 100%; height: 100%; }
    .photo-placeholder { width: 100%; height: 100%; border-radius: 2.9mm; background-color: #EDEDED; }

    .idblock { position: absolute; top: 57.2mm; left: 0; width: 53.98mm; text-align: center; }
    .nama { color: #FFFFFF; font-weight: bold; font-size: 12pt; margin: 0; }
    .id { color: #EF8F20; font-weight: bold; font-size: 7pt; letter-spacing: 0.5px; margin: 0.6mm 0 0; }

    table.lines { position: absolute; top: 67mm; left: 5mm; width: 43.98mm; border-collapse: collapse; }
    table.lines td { padding: 0 0 1.1mm 0; vertical-align: middle; }
    .icon-td { width: 3mm; }
    .icon-inline { width: 2.6mm; height: 2.6mm; }
    .line-text { color: #DCE3F0; font-size: 6.6pt; padding-left: 1.1mm; }

    table.brands { position: absolute; top: 80mm; left: 0; width: 53.98mm; border-collapse: collapse; }
    table.brands td { text-align: center; width: 25%; padding: 0; }
    table.brands img { height: 3.4mm; }
</style>
</head>
<body>
    <img class="bg" src="{{ public_path('images/kartu-member-bg.png') }}">

    <div class="photo">
        @if ($profil->fotoUrl())
            <img src="{{ public_path('storage/'.$profil->foto) }}">
        @else
            <div class="photo-placeholder"></div>
        @endif
    </div>

    <div class="idblock">
        <p class="nama">{{ $mitra->nama }}</p>
        <p class="id">{{ $mitra->kode_mitra }}</p>
    </div>

    <table class="lines">
        <tr>
            <td class="icon-td"><img class="icon-inline" src="{{ public_path('images/icons/icon-phone.png') }}"></td>
            <td class="line-text">{{ $profil->no_wa ?? '-' }}</td>
        </tr>
        <tr>
            <td class="icon-td"><img class="icon-inline" src="{{ public_path('images/icons/icon-map.png') }}"></td>
            <td class="line-text">{{ $profil->domisili_kota ? $profil->domisili_kota.($provinsi ? ', '.$provinsi : '') : '-' }}</td>
        </tr>
    </table>

    <table class="brands">
        <tr>
            <td><img src="{{ public_path('images/brands/reglow.png') }}"></td>
            <td><img src="{{ public_path('images/brands/amura.png') }}"></td>
            <td><img src="{{ public_path('images/brands/but.png') }}"></td>
            <td><img src="{{ public_path('images/brands/purela.png') }}"></td>
        </tr>
    </table>
</body>
</html>
