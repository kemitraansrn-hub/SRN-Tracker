<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'SRN' }}</title>
    @include('layouts.partials.styles')
    <style>
        .guest-shell {
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            padding: 24px;
        }
        .guest-card { width: 100%; max-width: 380px; padding: 32px 30px; }
        .guest-brand { display: flex; align-items: center; gap: 10px; margin-bottom: 26px; }
        .guest-brand-mark {
            width: 34px; height: 34px; border-radius: 9px; background: var(--accent); color: var(--surface);
            display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 15px;
        }
        .guest-brand-name { font-size: 18px; font-weight: 700; letter-spacing: -0.01em; }
        .guest-brand-sub { font-size: 11.5px; color: var(--ink-muted); margin-top: -2px; }
        .guest-title { font-size: 20px; margin-bottom: 6px; }
        .guest-sub { font-size: 13px; color: var(--ink-muted); margin-bottom: 24px; }
    </style>
</head>
<body>
    <div class="guest-shell">
        <div class="card guest-card">
            <div class="guest-brand">
                <div class="guest-brand-mark">S</div>
                <div>
                    <div class="guest-brand-name">SRN</div>
                    <div class="guest-brand-sub">Monitoring Mitra</div>
                </div>
            </div>
            @yield('content')
        </div>
    </div>
</body>
</html>
