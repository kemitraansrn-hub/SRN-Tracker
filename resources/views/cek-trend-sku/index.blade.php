@extends('layouts.app')

@section('content')
    <h1 class="display" style="font-size:24px;">Cek Trend SKU</h1>
    <div style="color:var(--ink-muted); font-size:13px; margin-top:4px; margin-bottom:20px;">
        Tren penjualan harian (Omset &amp; Qty) untuk SKU yang ditandai di menu Input NPD &rarr; tab Input Trend SKU, lengkap dengan titik mulai campaign di B2C. Rentang chart otomatis simetris: sejumlah hari sebelum s/d sejumlah hari sesudah tanggal campaign, mengikuti "Hitung s/d Tanggal" (default hari ini).
    </div>

    @if ($trendSkuOptions->isEmpty())
        <section class="card" style="color:var(--ink-muted); font-size:13px;">
            Belum ada produk yang ditandai sebagai Trend SKU. Tandai dulu di menu
            <a href="{{ route('npd.index', ['tab' => 'trend-sku']) }}">Input NPD &rarr; tab Input Trend SKU</a>.
        </section>
    @else
        <div id="trend-sku-app">
            @include('cek-trend-sku._content')
        </div>

        <script>
            // Fungsi global, hidup di luar #trend-sku-app -- supaya tetap ada
            // setelah fragment di dalamnya diganti via AJAX (tag <script> yang
            // disuntik lewat innerHTML gak otomatis jalan di browser).
            function trendSkuFieldValue(name) {
                var el = document.querySelector('#trend-sku-app [name="' + name + '"]');
                return el ? el.value : '';
            }

            // Metrik yang lagi aktif (Omset/Qty) -- dipertahankan di sini, di
            // luar #trend-sku-app, supaya gak diam-diam balik ke "Omset"
            // tiap kali fragment-nya diganti via AJAX (server selalu render
            // omset yang keliatan duluan secara default).
            window.trendSkuActiveMetric = window.trendSkuActiveMetric || 'omset';

            function trendSkuReload() {
                var app = document.getElementById('trend-sku-app');
                var params = new URLSearchParams();
                ['trend_sku_id', 'sampai_tanggal', 'tahun', 'impact_produk_id'].forEach(function (name) {
                    var v = trendSkuFieldValue(name);
                    if (v) params.set(name, v);
                });
                var url = '{{ route('cek-trend-sku.index') }}?' + params.toString();
                app.style.opacity = '0.5';
                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store' })
                    .then(function (r) { return r.text(); })
                    .then(function (html) {
                        app.innerHTML = html;
                        app.style.opacity = '';
                        window.history.replaceState(null, '', url);
                        setTrendSkuMetric(window.trendSkuActiveMetric);
                    })
                    .catch(function () {
                        app.style.opacity = '';
                    });
            }

            function trendSkuOnSkuChange() {
                var impact = document.querySelector('#trend-sku-app [name="impact_produk_id"]');
                if (impact) impact.value = '';
                trendSkuReload();
            }

            function trendSkuOnTahunChange() {
                // Server yang hitung ulang tanggal "Hitung s/d"-nya (31 Des
                // tahun itu, atau hari ini kalau tahun berjalan) -- field
                // tanggalnya otomatis ke-update begitu fragment-nya diganti.
                trendSkuReload();
            }

            function trendSkuOnDateChange() {
                // Ganti tanggal manual -> lepas dulu dari Tahun yang lagi
                // kepilih, biar tanggal manualnya beneran dipakai (bukan
                // ketimpa lagi sama Tahun di request berikutnya).
                var tahun = document.querySelector('#trend-sku-app [name="tahun"]');
                if (tahun) tahun.value = '';
                trendSkuReload();
            }

            // Satu fungsi toggle metrik, dipakai buat mode single maupun
            // compare -- cek keberadaan tiap elemen dulu (beberapa id cuma
            // ada di salah satu mode) biar aman dipanggil dari mode manapun.
            function setTrendSkuMetric(metric) {
                window.trendSkuActiveMetric = metric;
                ['omset', 'qty'].forEach(function (m) {
                    var show = (m === metric);
                    [
                        'trend-sku-line-' + m,
                        'trend-sku-lineA-' + m,
                        'trend-sku-lineB-' + m,
                        'trend-sku-trend-' + m,
                        'trend-sku-trendA-' + m,
                        'trend-sku-trendB-' + m,
                        'trend-sku-gridlines-' + m,
                        'trend-badge-' + m,
                        'trend-badge-A-' + m,
                        'trend-badge-B-' + m,
                    ].forEach(function (id) {
                        var el = document.getElementById(id);
                        if (el) el.style.display = show ? '' : 'none';
                    });
                    var btn = document.getElementById('btn-metric-' + m);
                    if (btn) btn.classList.toggle('btn-primary', show);
                });
            }
        </script>
    @endif
@endsection
