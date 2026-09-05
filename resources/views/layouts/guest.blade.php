@php
    // Semua file gambar di public/images/mitra-wall otomatis kepakai di
    // wall foto login — tinggal taruh/hapus file, gak perlu ubah kode.
    $mitraWallPhotos = collect(glob(public_path('images/mitra-wall/*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}'), GLOB_BRACE))
        ->map(fn ($p) => asset('images/mitra-wall/'.rawurlencode(basename($p))))
        ->values();
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'SRN' }}</title>
    @include('layouts.partials.styles')
    <style>
        .guest-frame {
            position: relative; overflow: hidden;
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            padding: 28px;
            background: #0E1A2E;
        }
        #bg-canvas {
            position: absolute; inset: 0; width: 100%; height: 100%;
            z-index: 0; pointer-events: none;
        }
        .guest-card {
            position: relative; z-index: 1;
            width: 100%; max-width: 900px; min-height: 560px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 22px; overflow: hidden;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.45);
            display: grid; grid-template-columns: 1fr 1fr;
        }
        .guest-form-side {
            padding: 44px 46px; display: flex; flex-direction: column; justify-content: center;
            background: rgba(255, 255, 255, 0.78);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
        }
        .guest-brand { display: flex; align-items: center; gap: 11px; margin-bottom: 34px; flex-wrap: nowrap; }
        .guest-brand img { height: 24px; width: auto; max-width: 58px; object-fit: contain; flex-shrink: 1; min-width: 0; }
        .guest-brand-divider { width: 1px; height: 20px; background: var(--line); flex-shrink: 0; }
        .guest-title { font-size: 24px; margin-bottom: 6px; }
        .guest-sub { font-size: 13px; color: var(--ink-muted); margin-bottom: 28px; }
        .guest-field { margin-bottom: 16px; }
        .guest-field label {
            display: block; font-size: 12px; font-weight: 600; color: var(--ink-muted); margin-bottom: 6px;
        }
        .guest-input-wrap { position: relative; }
        .guest-input-wrap svg.leading {
            position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
            width: 17px; height: 17px; color: var(--ink-faint); pointer-events: none;
        }
        .guest-input-wrap input {
            width: 100%; padding: 12px 14px 12px 42px; font-size: 14px;
            border: 1px solid var(--line); border-radius: 12px; background: var(--surface-alt); color: var(--ink);
            font-family: inherit; transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
        }
        .guest-input-wrap input:focus {
            outline: none; border-color: var(--accent); background: var(--surface);
            box-shadow: 0 0 0 3px var(--accent-soft);
        }
        .guest-input-wrap .toggle-eye {
            position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
            width: 30px; height: 30px; border: none; background: none; cursor: pointer;
            color: var(--ink-faint); display: flex; align-items: center; justify-content: center; border-radius: 6px;
        }
        .guest-input-wrap .toggle-eye:hover { color: var(--ink-muted); }
        .guest-input-wrap .toggle-eye svg { width: 17px; height: 17px; }
        .guest-remember-row {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 22px; font-size: 12.5px;
        }
        .guest-remember-row label { display: flex; align-items: center; gap: 7px; color: var(--ink-muted); cursor: pointer; }
        .guest-submit {
            width: 100%; padding: 13px; border-radius: 100px; border: none;
            background: var(--accent); color: #fff; font-size: 14.5px; font-weight: 700;
            font-family: inherit; cursor: pointer; transition: background-color 0.15s ease, transform 0.08s ease;
        }
        .guest-submit:hover { background: var(--accent-ink); }
        .guest-submit:active { transform: translateY(1px); }
        .guest-footnote { text-align: center; font-size: 12px; color: var(--ink-faint); margin-top: 18px; }

        .guest-illust-side {
            position: relative; overflow: hidden;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
        }
        .illust-photo-fade {
            position: absolute; inset: 0; z-index: 0;
            overflow: hidden; pointer-events: none;
            perspective: 1400px;
        }
        .illust-photo-fade::after {
            content: ''; position: absolute; inset: 0; z-index: 1;
            background: linear-gradient(165deg, rgba(14, 26, 46, 0.68), rgba(14, 26, 46, 0.82));
        }
        .illust-photo-fade-img {
            position: absolute; inset: 0;
            background-size: cover; background-position: center;
            transform-origin: center center;
            backface-visibility: hidden;
            transition: opacity 1.1s ease, transform 1.1s cubic-bezier(.33, .08, .19, 1);
        }
        {{-- 3 kondisi: nempel (keliatan normal), keluar (berputar 3D +
             geser ke kiri, kesan "didorong" menjauh), masuk (mulai dari
             kondisi cermin di kanan, lalu dianimasikan ke kondisi nempel) —
             arah swipe konsisten dari kanan ke kiri. --}}
        .illust-photo-fade-img.is-active {
            opacity: 1; transform: rotateY(0deg) translateX(0%) scale(1);
        }
        .illust-photo-fade-img.is-exiting {
            opacity: 0; transform: rotateY(-26deg) translateX(-18%) scale(0.88);
        }
        .illust-photo-fade-img.is-entering {
            opacity: 0; transform: rotateY(26deg) translateX(18%) scale(0.88);
        }
        .hud-analytics-float {
            position: absolute; right: 32px; top: 50%; transform: translateY(-50%);
            z-index: 1; pointer-events: none;
        }
        @media (max-width: 1180px) {
            .hud-analytics-float { display: none; }
        }
        .hud-analytics { max-width: 240px; }
        .hud-analytics .hud-pct {
            font-family: monospace; font-size: 26px; font-weight: 700; color: #C8F5FA;
            text-shadow: 0 0 10px rgba(130, 230, 240, 0.55);
        }
        .hud-analytics .hud-label {
            font-family: monospace; font-size: 11px; font-weight: 600; letter-spacing: 0.06em;
            color: rgba(160, 235, 245, 0.75); margin: 2px 0 12px;
        }
        .hud-analytics .hud-bar {
            height: 6px; border-radius: 3px; background: rgba(120, 220, 235, 0.18);
            overflow: hidden; margin-bottom: 8px;
        }
        .hud-analytics .hud-bar > div {
            height: 100%; border-radius: 3px; background: var(--accent);
        }
        @media (max-width: 760px) {
            .guest-card { grid-template-columns: 1fr; max-width: 420px; }
            .guest-illust-side { display: none; }
            .guest-form-side { padding: 36px 28px; }
        }
    </style>
</head>
<body>
    <div class="guest-frame">
        <canvas id="bg-canvas"></canvas>
        <div class="guest-card">
            <div class="guest-form-side">
                <div class="guest-brand">
                    <img src="{{ asset('images/srn-logo.png') }}" alt="SRN Partner Network">
                    <span class="guest-brand-divider"></span>
                    <img src="{{ asset('images/brands/reglow.png') }}" alt="Reglow">
                    <img src="{{ asset('images/brands/amura.png') }}" alt="Amura">
                    <img src="{{ asset('images/brands/but.png') }}" alt="B.U.T">
                    <img src="{{ asset('images/brands/purela.png') }}" alt="Purela">
                </div>
                @yield('content')
            </div>
            <div class="guest-illust-side">
                <div class="illust-photo-fade" id="illustPhotoFade"></div>
            </div>
        </div>
        <div class="hud-analytics-float">
            <div class="hud-analytics">
                <div class="hud-pct" id="hud-pct">10%</div>
                <div class="hud-label">REAL-TIME ANALYTICS</div>
                <div class="hud-bar"><div style="width:70%;"></div></div>
                <div class="hud-bar"><div style="width:45%;"></div></div>
                <div class="hud-bar"><div style="width:85%;"></div></div>
            </div>
        </div>
    </div>

    {{-- Foto di panel kanan (guest-illust-side): crossfade pelan gonta-ganti
         1 foto ke foto lain (beda dari mitra-wall yang geser+banyak sekaligus
         — ini cuma fade polos, 1 foto keliatan penuh di background panel). --}}
    <script>
        (function () {
            const el = document.getElementById('illustPhotoFade');
            if (! el) return;

            const PHOTOS = {!! $mitraWallPhotos->toJson() !!};
            if (! PHOTOS.length) return;

            const imgA = document.createElement('div');
            const imgB = document.createElement('div');
            imgA.className = 'illust-photo-fade-img';
            imgB.className = 'illust-photo-fade-img';
            el.appendChild(imgA);
            el.appendChild(imgB);

            let idx = 0;
            let showingA = true;
            imgA.style.backgroundImage = 'url(' + PHOTOS[0] + ')';
            imgA.classList.add('is-active');

            setInterval(() => {
                idx = (idx + 1) % PHOTOS.length;
                const next = showingA ? imgB : imgA;
                const cur = showingA ? imgA : imgB;

                // Siapin foto berikutnya di posisi "cermin" (kanan, gak
                // keliatan) dulu, paksa reflow biar itu ke-commit, baru
                // animasikan dua-duanya bersamaan: next masuk dari kanan,
                // cur keluar ke kiri.
                next.style.backgroundImage = 'url(' + PHOTOS[idx] + ')';
                next.classList.remove('is-active', 'is-exiting');
                next.classList.add('is-entering');
                void next.offsetWidth;

                next.classList.remove('is-entering');
                next.classList.add('is-active');
                cur.classList.remove('is-active');
                cur.classList.add('is-exiting');

                showingA = ! showingA;
            }, 4500);
        })();
    </script>

    {{-- Background HUD: canvas 2D biasa. Widget-widgetnya sengaja cuma
         digambar di margin kiri/kanan card login (dihitung dari posisi
         card asli tiap frame resize), jadi gak pernah ketutupan. --}}
    <script>
        (function () {
            const canvas = document.getElementById('bg-canvas');
            const frame = document.querySelector('.guest-frame');
            const card = document.querySelector('.guest-card');
            if (! canvas || ! canvas.getContext || ! frame || ! card) return;

            const ctx = canvas.getContext('2d');
            let width = 0;
            let height = 0;

            // Posisi card (dalam koordinat canvas) — dihitung presisi dari
            // getBoundingClientRect() tiap resize, dipakai buat naruh widget
            // yang diem di tengah gutter kosong sebelah kiri card (antara
            // tepi layar/laptop dengan tepi kiri card).
            let cardRect = { x: 0, y: 0, w: 0, h: 0 };
            function updateCardRect() {
                const cr = card.getBoundingClientRect();
                const fr = frame.getBoundingClientRect();
                cardRect = { x: cr.left - fr.left, y: cr.top - fr.top, w: cr.width, h: cr.height };
            }

            function resize() {
                const dpr = Math.min(window.devicePixelRatio || 1, 2);
                width = frame.clientWidth;
                height = frame.clientHeight;
                canvas.width = width * dpr;
                canvas.height = height * dpr;
                ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
                updateCardRect();
            }
            resize();
            window.addEventListener('resize', resize);

            const clamp = (v, a, b) => Math.max(a, Math.min(b, v));

            // Widget beneran jalan nyeberang dari kiri layar sampai keluar
            // di kanan layar (boleh lewat di belakang kartu login, wajar
            // karena kartunya transparan/blur), lalu balik lagi dari kiri —
            // fase & durasi beda-beda per widget biar gak bareng semua.
            function travelX(phase, periodFrames, widgetWidth) {
                const span = width + widgetWidth * 2;
                const t = (((frameCount + phase) % periodFrames) + periodFrames) % periodFrames / periodFrames;
                return t * span - widgetWidth;
            }

            const dotTracks = [0.3, 0.55, 0.42, 0.7, 0.25];

            let gaugeAngle = -Math.PI / 2;
            let gaugePct = 68;
            let frameCount = 0;

            function drawGrid() {
                const gridSize = 15;
                ctx.strokeStyle = 'rgba(110,205,225,0.09)';
                ctx.lineWidth = 1;
                const offset = (frameCount * 0.35) % gridSize;
                for (let x = -gridSize + offset; x <= width; x += gridSize) {
                    ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, height); ctx.stroke();
                }
                for (let y = 0; y <= height; y += gridSize) {
                    ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(width, y); ctx.stroke();
                }
            }

            // Gauge diem presisi di tengah panel kanan (bukan jalan
            // nyeberang lagi kayak widget lain). Dikasih efek "materialize":
            // muncul pelan-pelan dari blur+kecil ke tajam+ukuran penuh,
            // nahan sebentar, memudar lagi jadi blur+kecil, diem, lalu
            // ulang dari awal (loop) — pakai easing biar smooth, bukan
            // linear.
            const GAUGE_R = 50;
            const GAUGE_T_IN = 75;
            const GAUGE_T_HOLD = 260;
            const GAUGE_T_OUT = 75;
            const GAUGE_T_GAP = 110;
            const GAUGE_PERIOD = GAUGE_T_IN + GAUGE_T_HOLD + GAUGE_T_OUT + GAUGE_T_GAP;

            function drawGauge() {
                gaugeAngle += 0.012;
                gaugePct = clamp(gaugePct + Math.sin(frameCount * 0.02) * 0.3, 40, 92);

                const lt = frameCount % GAUGE_PERIOD;
                let t;
                if (lt < GAUGE_T_IN) {
                    t = lt / GAUGE_T_IN;
                } else if (lt < GAUGE_T_IN + GAUGE_T_HOLD) {
                    t = 1;
                } else if (lt < GAUGE_T_IN + GAUGE_T_HOLD + GAUGE_T_OUT) {
                    t = 1 - (lt - GAUGE_T_IN - GAUGE_T_HOLD) / GAUGE_T_OUT;
                } else {
                    t = 0;
                }
                if (t <= 0) return;

                // Tengah gutter kosong: antara tepi kiri layar (x=0) sampai
                // tepi kiri card (cardRect.x) — bukan di dalam panel.
                const gutterW = cardRect.x;
                if (gutterW < GAUGE_R * 2 + 24) return;

                const ease = t * t * (3 - 2 * t);
                const scale = 0.55 + ease * 0.45;
                const blurPx = (1 - ease) * 12;

                const gx = gutterW / 2;
                const gy = cardRect.y + cardRect.h / 2;

                ctx.save();
                ctx.globalAlpha = ease;
                ctx.filter = blurPx > 0.05 ? `blur(${blurPx.toFixed(1)}px)` : 'none';
                ctx.translate(gx, gy);
                ctx.scale(scale, scale);
                ctx.translate(-gx, -gy);

                for (let ring = 0; ring < 3; ring++) {
                    ctx.beginPath();
                    ctx.arc(gx, gy, Math.max(1, GAUGE_R - ring * 13), 0, Math.PI * 2);
                    ctx.strokeStyle = `rgba(120,220,235,${0.28 - ring * 0.07})`;
                    ctx.lineWidth = 2.5;
                    ctx.stroke();
                }
                ctx.beginPath();
                ctx.arc(gx, gy, GAUGE_R, -Math.PI / 2, gaugeAngle);
                ctx.strokeStyle = 'rgba(170,245,255,0.95)';
                ctx.lineWidth = 3;
                ctx.stroke();

                ctx.fillStyle = 'rgba(170,245,255,0.9)';
                ctx.font = '600 19px monospace';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(Math.round(gaugePct) + '%', gx, gy);
                ctx.restore();
            }

            const DOT_TRACK_W = 210;
            function drawDotTracks(x0base) {
                const x0 = x0base + 6;
                const w = DOT_TRACK_W - 40;
                const y0 = height * 0.68;

                ctx.fillStyle = 'rgba(160,235,245,0.7)';
                ctx.font = '600 8px monospace';
                ctx.textAlign = 'left';
                ctx.fillText('SUSTAINABILITY', x0, y0 - 8);

                dotTracks.forEach((base, i) => {
                    const prog = clamp(base + Math.sin(frameCount * 0.015 + i * 1.7) * 0.22, 0.05, 0.95);
                    const y = y0 + i * 11;
                    ctx.strokeStyle = 'rgba(120,220,235,0.2)';
                    ctx.lineWidth = 1;
                    ctx.beginPath(); ctx.moveTo(x0, y); ctx.lineTo(x0 + w, y); ctx.stroke();
                    const dx = x0 + w * prog;
                    ctx.beginPath();
                    ctx.arc(dx, y, 2.5, 0, Math.PI * 2);
                    ctx.fillStyle = 'rgba(200,250,255,0.95)';
                    ctx.fill();
                });
            }

            function draw() {
              try {
                frameCount++;
                ctx.clearRect(0, 0, width, height);

                ctx.fillStyle = '#0E1A2E';
                ctx.fillRect(0, 0, width, height);

                const wash = ctx.createLinearGradient(0, 0, width, height * 0.3);
                wash.addColorStop(0, 'rgba(20,60,100,0)');
                wash.addColorStop(0.55, 'rgba(20,60,100,0.05)');
                wash.addColorStop(0.82, 'rgba(150,60,50,0.22)');
                wash.addColorStop(1, 'rgba(190,100,40,0.3)');
                ctx.fillStyle = wash;
                ctx.fillRect(0, 0, width, height);

                drawGrid();

                const topGrad = ctx.createLinearGradient(0, 0, width, 0);
                topGrad.addColorStop(0, 'rgba(110,230,210,0.85)');
                topGrad.addColorStop(1, 'rgba(210,100,90,0.55)');
                ctx.fillStyle = topGrad;
                ctx.fillRect(0, 0, width, 3);

                // Semua widget yang tersisa beneran jalan nyeberang dari kiri layar sampai
                // keluar di kanan layar lalu balik lagi (loop) — durasi &
                // fase beda-beda per widget biar staggered, gak bareng.
                drawGauge();
                drawDotTracks(travelX(400, 3000, DOT_TRACK_W));
              } catch (e) {}

                requestAnimationFrame(draw);
            }
            draw();
        })();
    </script>

    {{-- Angka % di panel "REAL-TIME ANALYTICS" (HTML statis di panel kanan)
         tetap ngitung naik 10% -> 100% lalu loop, posisinya aja yang gak
         ikut geser kiri-kanan lagi kayak dulu. --}}
    <script>
        (function () {
            const el = document.getElementById('hud-pct');
            if (! el) return;
            let pct = 10;
            setInterval(function () {
                pct += 1;
                if (pct > 100) pct = 10;
                el.textContent = pct + '%';
            }, 90);
        })();
    </script>
</body>
</html>
