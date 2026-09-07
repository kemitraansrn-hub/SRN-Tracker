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
            background: #0A1226;
        }
        #bg-canvas {
            position: absolute; inset: 0; width: 100%; height: 100%;
            z-index: 0; pointer-events: none;
        }
        .guest-brand-hero {
            position: absolute; left: 24px; top: 50%; transform: translateY(-50%);
            z-index: 1; max-width: 180px; pointer-events: none;
        }
        .guest-brand-hero .hero-title {
            font-size: 21px; font-weight: 700; color: #fff; line-height: 1.25;
        }
        .guest-brand-hero .hero-title span { display: block; color: #8FB4F5; }
        .guest-brand-hero .hero-rule {
            width: 34px; height: 3px; border-radius: 2px; background: var(--accent);
            margin: 14px 0;
        }
        .guest-brand-hero .hero-sub {
            font-size: 12px; color: rgba(210, 222, 245, 0.7); line-height: 1.5;
        }
        {{-- Butuh gutter kiri yang beneran cukup lebar (card 900px + padding
             frame + margin sendiri) baru ditampilin — bukan breakpoint asal,
             dihitung dari lebar riil yang dibutuhkan biar gak numpuk ke card.
             Ukuran hero sengaja dikecilin biar tetep muncul di lebar laptop
             umum (1366/1440px), bukan cuma di monitor lebar. --}}
        @media (max-width: 1360px) {
            .guest-brand-hero { display: none; }
        }
        .guest-card {
            position: relative; z-index: 1;
            width: 100%; max-width: 900px; min-height: 560px;
            border-radius: 22px; overflow: hidden;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.45);
            display: grid; grid-template-columns: 1fr 1fr;
        }
        .guest-form-side {
            padding: 44px 46px; display: flex; flex-direction: column; justify-content: center;
            background: #FFFFFF;
        }
        .guest-brand { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 34px; flex-wrap: nowrap; }
        .guest-brand img { height: 30px; width: auto; max-width: 76px; object-fit: contain; flex-shrink: 1; min-width: 0; }
        .guest-brand-divider { width: 1px; height: 24px; background: var(--line); flex-shrink: 0; }
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
        .illust-content {
            position: absolute; left: 0; right: 0; bottom: 0; z-index: 2;
            padding: 28px 36px 32px; color: #fff;
            {{-- Gradient gelap tambahan KHUSUS di area ini — foto mitra
                 kadang terang/rame di bagian bawah, jadi teks/ikon butuh
                 kontras lebih kuat di sini daripada tint keseluruhan foto. --}}
            background: linear-gradient(180deg, rgba(10, 18, 38, 0) 0%, rgba(10, 18, 38, 0.55) 40%, rgba(10, 18, 38, 0.88) 100%);
        }
        .illust-content .illust-icon {
            width: 52px; height: 52px; border-radius: 50%;
            border: 1.5px solid rgba(255, 255, 255, 0.45);
            background: rgba(255, 255, 255, 0.1);
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 16px;
        }
        .illust-content .illust-icon svg { width: 24px; height: 24px; }
        .illust-content h2 {
            font-size: 22px; line-height: 1.3; font-weight: 700; margin: 0 0 18px;
        }
        .illust-features {
            display: flex; gap: 18px; padding-top: 16px;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }
        .illust-features .feature {
            flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 6px;
        }
        .illust-features .feature svg {
            width: 18px; height: 18px; color: #C7D9FA; flex-shrink: 0;
        }
        .illust-features .feature span {
            font-size: 11.5px; line-height: 1.35; color: rgba(230, 236, 250, 0.85);
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
        <div class="guest-brand-hero">
            <div class="hero-title">SRN<span>Partner Network</span></div>
            <div class="hero-rule"></div>
            <div class="hero-sub">Connecting partners, growing together.</div>
        </div>
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
                <div class="illust-content">
                    <div class="illust-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="7" r="4"/><path d="M17 11a4 4 0 1 0 0-8"/><path d="M1 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/><path d="M17 21v-2a4 4 0 0 0-3-3.87"/></svg>
                    </div>
                    <h2>Bersama Mitra,<br>Tumbuh Tanpa Batas</h2>
                    <div class="illust-features">
                        <div class="feature">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 17H7A5 5 0 0 1 7 7h2"/><path d="M15 7h2a5 5 0 1 1 0 10h-2"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                            <span>Kolaborasi yang kuat</span>
                        </div>
                        <div class="feature">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                            <span>Pertumbuhan berkelanjutan</span>
                        </div>
                        <div class="feature">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15 9 22 9 16.5 13.5 18.5 21 12 17 5.5 21 7.5 13.5 2 9 9 9"/></svg>
                            <span>Masa depan yang lebih baik</span>
                        </div>
                    </div>
                </div>
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

    {{-- Background "network/plexus": titik-titik nyala pelan bergerak,
         garis penghubung muncul kalau 2 titik cukup deket — efek jaringan
         partner yang saling terhubung, sesuai konsep mockup. --}}
    <script>
        (function () {
            const canvas = document.getElementById('bg-canvas');
            const frame = document.querySelector('.guest-frame');
            if (! canvas || ! canvas.getContext || ! frame) return;

            const ctx = canvas.getContext('2d');
            let width = 0;
            let height = 0;

            function resize() {
                const dpr = Math.min(window.devicePixelRatio || 1, 2);
                width = frame.clientWidth;
                height = frame.clientHeight;
                canvas.width = width * dpr;
                canvas.height = height * dpr;
                ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            }
            resize();
            window.addEventListener('resize', resize);

            const rand = (a, b) => a + Math.random() * (b - a);
            const NODE_COUNT = 55;
            const LINK_DIST = 150;

            const nodes = Array.from({ length: NODE_COUNT }, () => ({
                x: Math.random(),
                y: Math.random(),
                vx: rand(-0.2, 0.2),
                vy: rand(-0.2, 0.2),
                r: rand(1.4, 3.2),
                glow: Math.random() < 0.16,
            }));

            function draw() {
              try {
                ctx.clearRect(0, 0, width, height);

                // Dasar navy gelap + glow radial lembut di kanan-atas,
                // biar gak flat satu warna doang.
                ctx.fillStyle = '#0A1226';
                ctx.fillRect(0, 0, width, height);
                const glow = ctx.createRadialGradient(width * 0.82, height * 0.08, 0, width * 0.82, height * 0.08, Math.max(width, height) * 0.7);
                glow.addColorStop(0, 'rgba(40,80,160,0.35)');
                glow.addColorStop(1, 'rgba(40,80,160,0)');
                ctx.fillStyle = glow;
                ctx.fillRect(0, 0, width, height);

                nodes.forEach((n) => {
                    n.x += n.vx / width;
                    n.y += n.vy / height;
                    if (n.x < 0 || n.x > 1) n.vx *= -1;
                    if (n.y < 0 || n.y > 1) n.vy *= -1;
                    n.x = clamp01(n.x);
                    n.y = clamp01(n.y);
                });

                for (let i = 0; i < nodes.length; i++) {
                    const a = nodes[i];
                    const ax = a.x * width;
                    const ay = a.y * height;
                    for (let j = i + 1; j < nodes.length; j++) {
                        const b = nodes[j];
                        const bx = b.x * width;
                        const by = b.y * height;
                        const d = Math.hypot(ax - bx, ay - by);
                        if (d < LINK_DIST) {
                            ctx.strokeStyle = `rgba(120,170,235,${0.16 * (1 - d / LINK_DIST)})`;
                            ctx.lineWidth = 1;
                            ctx.beginPath();
                            ctx.moveTo(ax, ay);
                            ctx.lineTo(bx, by);
                            ctx.stroke();
                        }
                    }
                }

                nodes.forEach((n) => {
                    const x = n.x * width;
                    const y = n.y * height;
                    if (n.glow) {
                        ctx.beginPath();
                        ctx.arc(x, y, n.r * 4, 0, Math.PI * 2);
                        ctx.fillStyle = 'rgba(140,190,250,0.12)';
                        ctx.fill();
                    }
                    ctx.beginPath();
                    ctx.arc(x, y, n.r, 0, Math.PI * 2);
                    ctx.fillStyle = n.glow ? 'rgba(210,230,255,0.95)' : 'rgba(150,190,240,0.75)';
                    ctx.fill();
                });
              } catch (e) {}

                requestAnimationFrame(draw);
            }

            function clamp01(v) { return v < 0 ? 0 : v > 1 ? 1 : v; }

            draw();
        })();
    </script>
</body>
</html>
