<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'SRN' }}</title>
    @include('layouts.partials.styles')
    <style>
        .shell { display: grid; grid-template-columns: 244px 1fr; min-height: 100vh; }
        .sidebar {
            background: var(--surface-alt); border-right: 1px solid var(--line);
            padding: 22px 16px; display: flex; flex-direction: column; gap: 26px;
        }
        .brand { display: flex; align-items: center; gap: 10px; padding: 0 8px; }
        .brand-mark {
            width: 30px; height: 30px; border-radius: 8px; background: var(--accent); color: var(--surface);
            display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px;
        }
        .brand-name { font-size: 17px; font-weight: 700; letter-spacing: -0.01em; }
        .brand-sub { font-size: 11px; color: var(--ink-muted); margin-top: -2px; }
        .nav-item {
            display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 8px;
            color: var(--ink-muted); text-decoration: none; font-size: 13.5px;
        }
        .nav-item:hover { background: var(--accent-soft); color: var(--ink); }
        .nav-item.active { background: var(--accent-soft); color: var(--accent-ink); font-weight: 600; }
        .nav-item-toggle { justify-content: space-between; cursor: pointer; }
        .chevron { width: 13px; height: 13px; flex: none; opacity: 0.6; transition: transform 0.16s ease; }
        .nav-sub {
            display: flex; flex-direction: column; gap: 1px; padding-left: 14px; margin: 2px 0 0 21px;
            border-left: 1px solid var(--line);
        }
        .nav-subitem {
            display: block; padding: 7px 10px 7px 14px; border-radius: 7px;
            font-size: 13px; color: var(--ink-muted); text-decoration: none;
        }
        .nav-subitem:hover { background: var(--accent-soft); color: var(--ink); }
        .nav-subitem.active { background: var(--accent-soft); color: var(--accent-ink); font-weight: 600; }
        .sidebar-foot {
            margin-top: auto; border-top: 1px solid var(--line); padding-top: 14px;
            display: flex; align-items: center; gap: 10px;
        }
        .avatar {
            width: 32px; height: 32px; border-radius: 50%; background: var(--accent-soft); color: var(--accent-ink);
            display: flex; align-items: center; justify-content: center; font-size: 12.5px; font-weight: 700;
        }
        .who-name { font-size: 13px; font-weight: 600; }
        .who-role { font-size: 11px; color: var(--ink-muted); }
        .logout-btn {
            background: none; border: none; color: var(--ink-faint); cursor: pointer;
            font-size: 11.5px; text-decoration: underline; padding: 0; margin-top: 2px;
        }
        .main { padding: 26px 34px 60px; max-width: 1240px; }
        @media (max-width: 980px) {
            .shell { grid-template-columns: 1fr; }
            .sidebar { display: none; }
        }
    </style>
</head>
<body>
    <div class="shell">
        <aside class="sidebar">
            <div class="brand">
                <div class="brand-mark">S</div>
                <div>
                    <div class="brand-name">SRN</div>
                    <div class="brand-sub">Monitoring Mitra</div>
                </div>
            </div>

            <nav style="display:flex; flex-direction:column; gap:2px;">
                <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    Dashboard
                </a>
                <a href="{{ route('mitra.index') }}" class="nav-item {{ request()->routeIs('mitra.*') ? 'active' : '' }}">
                    Data Mitra
                </a>

                @php
                    $segmenList = \App\Models\TargetBulanan::currentMonthSegments();
                    if ($segmenList->isEmpty()) {
                        $segmenList = collect(['PARETO', 'RTP (ROAD TO PARETO)', 'REGULER', 'SPECIAL REGULER']);
                    }
                @endphp
                <a class="nav-item nav-item-toggle" tabindex="0" onclick="toggleSegmentasi()">
                        <span>Segmentasi Mitra</span>
                        <svg class="chevron" id="segChevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="{{ request()->routeIs('segmentasi.*') ? 'transform:rotate(90deg);' : '' }}"><path d="M9 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </a>
                    <div class="nav-sub" id="segSubmenu" style="display:{{ request()->routeIs('segmentasi.*') ? 'flex' : 'none' }};">
                        @foreach ($segmenList as $s)
                            <a href="{{ route('segmentasi.show', $s) }}" class="nav-subitem {{ request()->routeIs('segmentasi.*') && request()->route('segmen') === $s ? 'active' : '' }}">{{ $s }}</a>
                        @endforeach
                    </div>

                <a href="{{ route('followup.index') }}" class="nav-item {{ request()->routeIs('followup.*') ? 'active' : '' }}">
                    Follow-up Log
                </a>
                <a href="{{ route('special-deal.index') }}" class="nav-item {{ request()->routeIs('special-deal.*') ? 'active' : '' }}">
                    Special Deal
                </a>
                <a href="{{ route('trend.index') }}" class="nav-item {{ request()->routeIs('trend.*') ? 'active' : '' }}">
                    Trend Mitra
                </a>
                <a href="{{ route('weekly-plan.index') }}" class="nav-item {{ request()->routeIs('weekly-plan.*') ? 'active' : '' }}">
                    Weekly Plan
                </a>
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('import.index') }}" class="nav-item {{ request()->routeIs('import.*') ? 'active' : '' }}">
                        Import Data
                    </a>
                    <a href="{{ route('pengaturan.minggu') }}" class="nav-item {{ request()->routeIs('pengaturan.*') ? 'active' : '' }}">
                        Periode Mingguan
                    </a>
                    <a href="{{ route('users.index') }}" class="nav-item {{ request()->routeIs('users.*') ? 'active' : '' }}">
                        User Management
                    </a>
                @endif
            </nav>

            <div class="sidebar-foot">
                <div class="avatar">{{ collect(explode(' ', auth()->user()->name))->map(fn($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}</div>
                <div>
                    <div class="who-name">{{ auth()->user()->name }}</div>
                    <div class="who-role">{{ auth()->user()->role === 'admin' ? 'Admin' : 'KAE' }}</div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="logout-btn">Keluar</button>
                    </form>
                </div>
            </div>
        </aside>

        <main class="main">
            @yield('content')
        </main>
    </div>

    <script>
        function toggleSegmentasi() {
            const sub = document.getElementById('segSubmenu');
            const chevron = document.getElementById('segChevron');
            const isOpen = sub.style.display === 'flex';
            sub.style.display = isOpen ? 'none' : 'flex';
            chevron.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(90deg)';
        }
    </script>
</body>
</html>
