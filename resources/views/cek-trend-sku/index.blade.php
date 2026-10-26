@extends('layouts.app')

@section('content')
    <h1 class="display" style="font-size:24px;">Cek Trend SKU</h1>
    <div style="color:var(--ink-muted); font-size:13px; margin-top:4px; margin-bottom:20px;">
        Tren penjualan harian (Omset &amp; Qty) untuk SKU yang ditandai di menu Input NPD &rarr; tab Input Trend SKU, lengkap dengan titik mulai campaign di B2C. Default tampilan: Year To Date.
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

            function trendSkuReload() {
                var app = document.getElementById('trend-sku-app');
                var params = new URLSearchParams();
                ['trend_sku_id', 'dari_tanggal', 'sampai_tanggal', 'tahun', 'bulan', 'impact_produk_id'].forEach(function (name) {
                    var v = trendSkuFieldValue(name);
                    if (v) params.set(name, v);
                });
                var url = '{{ route('cek-trend-sku.index') }}?' + params.toString();
                app.style.opacity = '0.5';
                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (r) { return r.text(); })
                    .then(function (html) {
                        app.innerHTML = html;
                        app.style.opacity = '';
                        window.history.replaceState(null, '', url);
                    })
                    .catch(function () {
                        app.style.opacity = '';
                    });
            }

            function trendSkuClearDateInputs() {
                ['dari_tanggal', 'sampai_tanggal'].forEach(function (name) {
                    var el = document.querySelector('#trend-sku-app [name="' + name + '"]');
                    if (el) el.value = '';
                });
            }

            function trendSkuClearPeriodSelects() {
                ['tahun', 'bulan'].forEach(function (name) {
                    var el = document.querySelector('#trend-sku-app [name="' + name + '"]');
                    if (el) el.value = '';
                });
            }

            function trendSkuOnSkuChange() {
                var impact = document.querySelector('#trend-sku-app [name="impact_produk_id"]');
                if (impact) impact.value = '';
                trendSkuReload();
            }

            function trendSkuOnTahunChange() {
                trendSkuClearDateInputs();
                trendSkuReload();
            }

            function trendSkuOnBulanChange() {
                trendSkuClearDateInputs();
                trendSkuReload();
            }

            function trendSkuOnDateChange() {
                trendSkuClearPeriodSelects();
                trendSkuReload();
            }

            // Satu fungsi toggle metrik, dipakai buat mode single maupun
            // compare -- cek keberadaan tiap elemen dulu (beberapa id cuma
            // ada di salah satu mode) biar aman dipanggil dari mode manapun.
            function setTrendSkuMetric(metric) {
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
