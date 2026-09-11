<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'SRN' }}</title>
    <script>
        if (localStorage.getItem('srn-sidebar-collapsed') === '1') {
            document.documentElement.classList.add('sidebar-collapsed');
        }
    </script>
    @include('layouts.partials.styles')
    <style>
        html, body { height: 100%; margin: 0; overflow: hidden; }
        .shell { display: grid; grid-template-columns: 244px 1fr; height: 100vh; transition: grid-template-columns 0.18s ease; }
        html.sidebar-collapsed .shell { grid-template-columns: 72px 1fr; }
        .sidebar {
            background: var(--surface-alt); border-right: 1px solid var(--line);
            padding: 22px 16px; display: flex; flex-direction: column; gap: 26px;
            height: 100vh; overflow-y: auto; flex: none; position: relative;
            transition: padding 0.18s ease;
        }
        html.sidebar-collapsed .sidebar { padding: 22px 10px; overflow-x: hidden; }
        .sidebar-collapse-btn {
            position: absolute; top: 26px; right: -12px; width: 24px; height: 24px; border-radius: 50%;
            background: var(--surface); border: 1px solid var(--line); box-shadow: var(--shadow);
            display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--ink-muted);
            z-index: 10; transition: color 0.14s ease, border-color 0.14s ease;
        }
        .sidebar-collapse-btn:hover { color: var(--accent-ink); border-color: var(--accent); }
        .collapse-chevron { transition: transform 0.18s ease; flex: none; }
        html.sidebar-collapsed .collapse-chevron { transform: rotate(180deg); }
        .brand { display: flex; align-items: center; padding: 0 8px; }
        .brand img { width: 100%; height: auto; }
        html.sidebar-collapsed .brand { justify-content: center; padding: 0; }
        html.sidebar-collapsed .brand-full { display: none; }
        .brand-mini {
            display: none; width: 34px; height: 34px; border-radius: 9px; flex: none;
            background: var(--accent-soft); color: var(--accent-ink);
            align-items: center; justify-content: center; font-weight: 800; font-size: 14.5px;
        }
        html.sidebar-collapsed .brand-mini { display: flex; }
        .brand-name { font-size: 17px; font-weight: 700; letter-spacing: -0.01em; }
        html.sidebar-collapsed .nav-label,
        html.sidebar-collapsed .nav-group-label,
        html.sidebar-collapsed .nav-sub,
        html.sidebar-collapsed .chevron,
        html.sidebar-collapsed .who-info { display: none; }
        html.sidebar-collapsed .nav-item,
        html.sidebar-collapsed .nav-item-toggle { justify-content: center; }
        html.sidebar-collapsed .sidebar-foot { flex-direction: column; gap: 10px; }
        .nav-item {
            display: flex; align-items: center; gap: 10px; padding: 9px 10px; border-radius: 9px;
            color: var(--ink-muted); text-decoration: none; font-size: 13.5px;
            box-shadow: inset 3px 0 0 transparent;
            transition: background-color 0.14s ease, color 0.14s ease, box-shadow 0.14s ease;
        }
        .nav-item:hover { background: var(--accent-soft); color: var(--ink); }
        .nav-item.active {
            background: var(--accent-soft); color: var(--accent-ink); font-weight: 600;
            box-shadow: inset 3px 0 0 var(--accent);
        }
        .nav-item-toggle { justify-content: space-between; cursor: pointer; }
        .nav-icon { width: 16px; height: 16px; flex: none; opacity: 0.7; }
        .nav-item-toggle-label { display: flex; align-items: center; gap: 10px; }
        .chevron { width: 13px; height: 13px; flex: none; opacity: 0.6; transition: transform 0.16s ease; }
        .nav-sub {
            display: flex; flex-direction: column; gap: 1px; padding-left: 14px; margin: 2px 0 0 21px;
            border-left: 1px solid var(--line);
        }
        .nav-subitem {
            display: block; padding: 7px 10px 7px 14px; border-radius: 7px;
            font-size: 13px; color: var(--ink-muted); text-decoration: none;
            box-shadow: inset 2px 0 0 transparent;
            transition: background-color 0.14s ease, color 0.14s ease, box-shadow 0.14s ease;
        }
        .nav-subitem:hover { background: var(--accent-soft); color: var(--ink); }
        .nav-subitem.active {
            background: var(--accent-soft); color: var(--accent-ink); font-weight: 600;
            box-shadow: inset 2px 0 0 var(--accent);
        }
        .nav-group-label {
            font-size: 10.5px; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700;
            color: var(--ink-faint); padding: 12px 10px 3px;
        }
        .sidebar-foot {
            margin-top: auto; border-top: 1px solid var(--line); padding-top: 14px;
            display: flex; align-items: center; gap: 10px;
        }
        .avatar {
            width: 32px; height: 32px; border-radius: 50%; background: var(--accent-soft); color: var(--accent-ink);
            display: flex; align-items: center; justify-content: center; font-size: 12.5px; font-weight: 700;
            flex: none;
        }
        .who-name { font-size: 13px; font-weight: 600; }
        .who-role { font-size: 11px; color: var(--ink-muted); }
        .logout-btn {
            width: 30px; height: 30px; border-radius: 8px; flex: none;
            background: none; border: 1px solid transparent; color: var(--ink-faint); cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: background-color 0.14s ease, color 0.14s ease, border-color 0.14s ease;
        }
        .logout-btn:hover { background: var(--critical-soft); color: var(--critical); border-color: var(--critical-soft); }
        .main {
            padding: 26px 34px 60px;
            height: 100vh; overflow-y: auto; overflow-x: auto; min-width: 0;
        }
        .main-inner { max-width: 1240px; margin: 0 auto; transition: max-width 0.18s ease; }

        .mobile-topbar { display: none; }
        .sidebar-backdrop { display: none; }

        @media (max-width: 980px) {
            .shell { grid-template-columns: 1fr; }
            .sidebar {
                position: fixed; top: 0; left: 0; bottom: 0;
                width: 244px; height: 100vh;
                transform: translateX(-100%);
                transition: transform 0.2s ease;
                z-index: 60;
            }
            .sidebar.open { transform: translateX(0); box-shadow: var(--shadow); }
            .sidebar-collapse-btn { display: none; }
            html.sidebar-collapsed .shell { grid-template-columns: 1fr; }
            .mobile-topbar {
                display: flex; align-items: center; gap: 12px;
                padding: 14px 20px; border-bottom: 1px solid var(--line);
                background: var(--surface-alt); flex: none;
            }
            .menu-btn {
                width: 34px; height: 34px; border-radius: 8px;
                border: 1px solid var(--line); background: var(--surface);
                display: flex; align-items: center; justify-content: center; cursor: pointer;
                flex: none;
            }
            .menu-btn:hover { border-color: var(--ink-faint); }
            .sidebar-backdrop.open {
                display: block;
                position: fixed; inset: 0; background: rgba(20, 18, 15, 0.4); z-index: 55;
            }
            .main { height: calc(100vh - 63px); padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="shell">
        <aside class="sidebar">
            <button type="button" class="sidebar-collapse-btn" onclick="toggleSidebarCollapse()" title="Ciutkan/lebarkan menu" aria-label="Ciutkan atau lebarkan menu">
                <svg class="collapse-chevron" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M15 6l-6 6 6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <div class="brand">
                <img class="brand-full" src="{{ asset('images/srn-logo.png') }}" alt="SRN Partner Network">
                <div class="brand-mini">S</div>
            </div>

            <nav style="display:flex; flex-direction:column; gap:2px;">
                @if (auth()->user()->isFinance())
                    <a href="{{ route('poin.index') }}" class="nav-item {{ request()->routeIs('poin.*') ? 'active' : '' }}" title="Poin Mitra">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                        <span class="nav-label">Poin Mitra</span>
                    </a>
                    <a href="{{ route('poin-redemption.index') }}" class="nav-item {{ request()->routeIs('poin-redemption.*') ? 'active' : '' }}" title="Penukaran Poin">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 1l4 4-4 4" stroke-linecap="round" stroke-linejoin="round"/><path d="M3 11V9a4 4 0 0 1 4-4h14" stroke-linecap="round" stroke-linejoin="round"/><path d="M7 23l-4-4 4-4" stroke-linecap="round" stroke-linejoin="round"/><path d="M21 13v2a4 4 0 0 1-4 4H3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <span class="nav-label">Penukaran Poin</span>
                    </a>
                    <a href="{{ route('buyback.index') }}" class="nav-item {{ request()->routeIs('buyback.*') ? 'active' : '' }}" title="Pengajuan Buy Back">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" stroke-linecap="round" stroke-linejoin="round"/><path d="M3 3v5h5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <span class="nav-label">Pengajuan Buy Back</span>
                    </a>
                @else
                <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}" title="Dashboard">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                    <span class="nav-label">Dashboard</span>
                </a>

                @php
                    $segmenList = \App\Models\TargetBulanan::currentMonthSegments();
                    if ($segmenList->isEmpty()) {
                        $segmenList = collect(['PARETO', 'RTP (ROAD TO PARETO)', 'REGULER', 'SPECIAL REGULER']);
                    }

                    $salesRoutes = ['sales-overview.*', 'segmentasi.*', 'trend.*', 'omset-bulanan.*', 'weekly-plan.*', 'forecast.*', 'action-plan.*', 'mitra.*', 'order.*', 'followup.*', 'special-deal.*', 'data-development.*', 'ar.*', 'sales-draft.*', 'buyback.*', 'poin.*', 'poin-redemption.*'];
                    $adminRoutes = ['reward.*', 'produk.*', 'import.*', 'data-health.*', 'pengaturan.*', 'run-rate-target.*', 'buyback-setting.*', 'npd.*', 'users.*', 'backup.*'];
                    $salesActive = request()->routeIs(...$salesRoutes);
                    $developmentActive = request()->routeIs('development.*');
                    $adminActive = request()->routeIs(...$adminRoutes);
                @endphp

                <a class="nav-item nav-item-toggle" tabindex="0" onclick="toggleNavGroup('salesSubmenu', 'salesChevron')" title="Sales">
                    <span class="nav-item-toggle-label">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 14l3-3 3 3 4-5" stroke-linecap="round" stroke-linejoin="round"/><path d="M7 17h10" stroke-linecap="round"/></svg>
                        <span class="nav-label">Sales</span>
                    </span>
                    <svg class="chevron" id="salesChevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="{{ $salesActive ? 'transform:rotate(90deg);' : '' }}"><path d="M9 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </a>
                <div class="nav-sub" id="salesSubmenu" style="display:{{ $salesActive ? 'flex' : 'none' }}; flex-direction:column; gap:2px;">
                    <div class="nav-group-label">Analisa &amp; Planning</div>
                    <a href="{{ route('sales-overview.index') }}" class="nav-item {{ request()->routeIs('sales-overview.*') ? 'active' : '' }}" title="Sales Overview">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 14l3-3 3 3 4-5" stroke-linecap="round" stroke-linejoin="round"/><path d="M7 17h10" stroke-linecap="round"/></svg>
                        <span class="nav-label">Sales Overview</span>
                    </a>
                    <a class="nav-item nav-item-toggle" tabindex="0" onclick="toggleNavGroup('segSubmenu', 'segChevron')" title="Segmentasi Mitra">
                            <span class="nav-item-toggle-label">
                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>
                                <span class="nav-label">Segmentasi Mitra</span>
                            </span>
                            <svg class="chevron" id="segChevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="{{ request()->routeIs('segmentasi.*') ? 'transform:rotate(90deg);' : '' }}"><path d="M9 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </a>
                        <div class="nav-sub" id="segSubmenu" style="display:{{ request()->routeIs('segmentasi.*') ? 'flex' : 'none' }};">
                            @foreach ($segmenList as $s)
                                <a href="{{ route('segmentasi.show', $s) }}" class="nav-subitem {{ request()->routeIs('segmentasi.*') && request()->route('segmen') === $s ? 'active' : '' }}">{{ $s }}</a>
                            @endforeach
                        </div>

                    <a href="{{ route('trend.index') }}" class="nav-item {{ request()->routeIs('trend.*') ? 'active' : '' }}" title="Trend Mitra">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
                        <span class="nav-label">Trend Mitra</span>
                    </a>
                    <a href="{{ route('omset-bulanan.index') }}" class="nav-item {{ request()->routeIs('omset-bulanan.*') ? 'active' : '' }}" title="Omset Bulanan">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/></svg>
                        <span class="nav-label">Omset Bulanan</span>
                    </a>
                    <a href="{{ route('weekly-plan.index') }}" class="nav-item {{ request()->routeIs('weekly-plan.*') ? 'active' : '' }}" title="Weekly Plan">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                        <span class="nav-label">Weekly Plan</span>
                    </a>
                    <a href="{{ route('forecast.index') }}" class="nav-item {{ request()->routeIs('forecast.*') ? 'active' : '' }}" title="Forecast">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 14l4-4 3 3 5-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <span class="nav-label">Forecast</span>
                    </a>
                    <a href="{{ route('action-plan.index') }}" class="nav-item {{ request()->routeIs('action-plan.*') ? 'active' : '' }}" title="Action Plan">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="7" y1="8" x2="17" y2="8"/><line x1="7" y1="12" x2="17" y2="12"/><line x1="7" y1="16" x2="12" y2="16"/></svg>
                        <span class="nav-label">Action Plan</span>
                    </a>

                    <div class="nav-group-label">Operasional</div>
                    <a href="{{ route('mitra.index') }}" class="nav-item {{ request()->routeIs('mitra.*') ? 'active' : '' }}" title="Data Mitra">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        <span class="nav-label">Data Mitra</span>
                    </a>
                    <a href="{{ route('order.index') }}" class="nav-item {{ request()->routeIs('order.*') ? 'active' : '' }}" title="Order / Transaksi">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                        <span class="nav-label">Order / Transaksi</span>
                    </a>
                    <a href="{{ route('followup.index') }}" class="nav-item {{ request()->routeIs('followup.*') ? 'active' : '' }}" title="Follow-up Log">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        <span class="nav-label">Follow-up Log</span>
                    </a>
                    <a href="{{ route('special-deal.index') }}" class="nav-item {{ request()->routeIs('special-deal.*') ? 'active' : '' }}" title="Special Deal">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82Z"/><circle cx="7" cy="7" r="1"/></svg>
                        <span class="nav-label">Special Deal</span>
                    </a>
                    <a href="{{ route('data-development.index') }}" class="nav-item {{ request()->routeIs('data-development.*') ? 'active' : '' }}" title="Data Development">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                        <span class="nav-label">Data Development</span>
                    </a>
                    <a href="{{ route('ar.index') }}" class="nav-item {{ request()->routeIs('ar.*') ? 'active' : '' }}" title="Data Piutang / AR">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                        <span class="nav-label">Data Piutang / AR</span>
                    </a>
                    <a href="{{ route('sales-draft.index') }}" class="nav-item {{ request()->routeIs('sales-draft.*') ? 'active' : '' }}" title="Input Penjualan">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <span class="nav-label">Input Penjualan</span>
                    </a>
                    <a href="{{ route('buyback.index') }}" class="nav-item {{ request()->routeIs('buyback.*') ? 'active' : '' }}" title="Pengajuan Buy Back">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" stroke-linecap="round" stroke-linejoin="round"/><path d="M3 3v5h5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <span class="nav-label">Pengajuan Buy Back</span>
                    </a>
                    <a href="{{ route('poin.index') }}" class="nav-item {{ request()->routeIs('poin.*') ? 'active' : '' }}" title="Poin Mitra">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                        <span class="nav-label">Poin Mitra</span>
                    </a>
                    <a href="{{ route('poin-redemption.index') }}" class="nav-item {{ request()->routeIs('poin-redemption.*') ? 'active' : '' }}" title="Penukaran Poin">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 1l4 4-4 4" stroke-linecap="round" stroke-linejoin="round"/><path d="M3 11V9a4 4 0 0 1 4-4h14" stroke-linecap="round" stroke-linejoin="round"/><path d="M7 23l-4-4 4-4" stroke-linecap="round" stroke-linejoin="round"/><path d="M21 13v2a4 4 0 0 1-4 4H3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <span class="nav-label">Penukaran Poin</span>
                    </a>
                </div>

                <a class="nav-item nav-item-toggle" tabindex="0" onclick="toggleNavGroup('devSubmenu', 'devChevron')" title="Development">
                    <span class="nav-item-toggle-label">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                        <span class="nav-label">Development</span>
                    </span>
                    <svg class="chevron" id="devChevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="{{ $developmentActive ? 'transform:rotate(90deg);' : '' }}"><path d="M9 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </a>
                <div class="nav-sub" id="devSubmenu" style="display:{{ $developmentActive ? 'flex' : 'none' }}; flex-direction:column; gap:2px;">
                    <a href="{{ route('development.show', 'tracking-cp') }}" class="nav-item {{ request()->routeIs('development.*') && request()->route('page') === 'tracking-cp' ? 'active' : '' }}" title="Tracking CP">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65" stroke-linecap="round"/></svg>
                        <span class="nav-label">Tracking CP</span>
                    </a>
                    <a href="{{ route('development.show', 'take-down-banding') }}" class="nav-item {{ request()->routeIs('development.*') && request()->route('page') === 'take-down-banding' ? 'active' : '' }}" title="Take Down & Banding">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z" stroke-linecap="round" stroke-linejoin="round"/><line x1="4" y1="22" x2="4" y2="15" stroke-linecap="round"/></svg>
                        <span class="nav-label">Take Down &amp; Banding</span>
                    </a>
                    <a href="{{ route('development.show', 'price-adjustment-monitoring') }}" class="nav-item {{ request()->routeIs('development.*') && request()->route('page') === 'price-adjustment-monitoring' ? 'active' : '' }}" title="Price Adjustment Monitoring">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/></svg>
                        <span class="nav-label">Price Adjustment Monitoring</span>
                    </a>
                    <a href="{{ route('development.show', 'kpi-partnership-compliance') }}" class="nav-item {{ request()->routeIs('development.*') && request()->route('page') === 'kpi-partnership-compliance' ? 'active' : '' }}" title="KPI Partnership Compliance">
                        <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4" stroke-linecap="round" stroke-linejoin="round"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        <span class="nav-label">KPI Partnership Compliance</span>
                    </a>
                </div>

                @if (auth()->user()->isAdmin())
                    <a class="nav-item nav-item-toggle" tabindex="0" onclick="toggleNavGroup('adminSubmenu', 'adminChevron')" title="Admin">
                        <span class="nav-item-toggle-label">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3 6 6 1-4.5 4.5L18 20l-6-3-6 3 1.5-6.5L3 9l6-1z"/></svg>
                            <span class="nav-label">Admin</span>
                        </span>
                        <svg class="chevron" id="adminChevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="{{ $adminActive ? 'transform:rotate(90deg);' : '' }}"><path d="M9 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </a>
                    <div class="nav-sub" id="adminSubmenu" style="display:{{ $adminActive ? 'flex' : 'none' }}; flex-direction:column; gap:2px;">
                        <a href="{{ route('reward.index') }}" class="nav-item {{ request()->routeIs('reward.*') ? 'active' : '' }}" title="Input Reward">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 12v10H4V12" stroke-linecap="round" stroke-linejoin="round"/><path d="M2 7h20v5H2z" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 22V7" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z" stroke-linecap="round" stroke-linejoin="round"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <span class="nav-label">Input Reward</span>
                        </a>
                        <a href="{{ route('produk.index') }}" class="nav-item {{ request()->routeIs('produk.*') ? 'active' : '' }}" title="Master Produk">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.59 13.41L13.42 20.58a2 2 0 0 1-2.83 0L2 12.01V2h10.01l8.58 8.58a2 2 0 0 1 0 2.83z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                            <span class="nav-label">Master Produk</span>
                        </a>
                        <a href="{{ route('import.index') }}" class="nav-item {{ request()->routeIs('import.*') ? 'active' : '' }}" title="Import Data">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                            <span class="nav-label">Import Data</span>
                        </a>
                        <a href="{{ route('data-health.index') }}" class="nav-item {{ request()->routeIs('data-health.*') ? 'active' : '' }}" title="Cek Kesehatan Data">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                            <span class="nav-label">Cek Kesehatan Data</span>
                        </a>
                        <a href="{{ route('pengaturan.minggu') }}" class="nav-item {{ request()->routeIs('pengaturan.*') ? 'active' : '' }}" title="Periode Mingguan">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/></svg>
                            <span class="nav-label">Periode Mingguan</span>
                        </a>
                        <a href="{{ route('run-rate-target.edit') }}" class="nav-item {{ request()->routeIs('run-rate-target.*') ? 'active' : '' }}" title="Target Perusahaan & KAE">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
                            <span class="nav-label">Target Perusahaan &amp; KAE</span>
                        </a>
                        <a href="{{ route('buyback-setting.edit') }}" class="nav-item {{ request()->routeIs('buyback-setting.*') ? 'active' : '' }}" title="Tingkat Penyusutan Buy Back">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" stroke-linecap="round" stroke-linejoin="round"/><path d="M3 3v5h5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <span class="nav-label">Tingkat Penyusutan Buy Back</span>
                        </a>
                        <a href="{{ route('npd.index') }}" class="nav-item {{ request()->routeIs('npd.*') ? 'active' : '' }}" title="Input NPD">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/><circle cx="12" cy="12" r="4"/></svg>
                            <span class="nav-label">Input NPD</span>
                        </a>
                        <a href="{{ route('users.index') }}" class="nav-item {{ request()->routeIs('users.*') ? 'active' : '' }}" title="User Management">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/></svg>
                            <span class="nav-label">User Management</span>
                        </a>
                        <a href="{{ route('backup.index') }}" class="nav-item {{ request()->routeIs('backup.*') ? 'active' : '' }}" title="Backup Data">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.66 4.03 3 9 3s9-1.34 9-3V5"/><path d="M3 12c0 1.66 4.03 3 9 3s9-1.34 9-3"/></svg>
                            <span class="nav-label">Backup Data</span>
                        </a>
                    </div>
                @endif
                @endif
            </nav>

            <div class="sidebar-foot">
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('produk.notifications') }}" title="Notifikasi produk baru" aria-label="Notifikasi produk baru" style="position:relative; display:flex; align-items:center; justify-content:center; width:34px; height:34px; border-radius:10px; color:var(--ink-muted); flex-shrink:0;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9" stroke-linecap="round" stroke-linejoin="round"/><path d="M13.73 21a2 2 0 0 1-3.46 0" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        @if ($produkBaruCount > 0)
                            <span style="position:absolute; top:2px; right:2px; min-width:16px; height:16px; padding:0 3px; border-radius:8px; background:#E0483C; color:#fff; font-size:10px; font-weight:700; line-height:16px; text-align:center;">{{ $produkBaruCount > 99 ? '99+' : $produkBaruCount }}</span>
                        @endif
                    </a>
                @endif
                <div class="avatar" title="{{ auth()->user()->name }}">{{ collect(explode(' ', auth()->user()->name))->map(fn($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}</div>
                <div class="who-info" style="flex:1; min-width:0; overflow:hidden;">
                    <div class="who-name" style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ auth()->user()->name }}</div>
                    <div class="who-role">{{ auth()->user()->role === 'admin' ? 'Admin' : 'KAE' }}</div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="logout-btn" title="Keluar" aria-label="Keluar">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" stroke-linecap="round" stroke-linejoin="round"/><polyline points="16 17 21 12 16 7" stroke-linecap="round" stroke-linejoin="round"/><line x1="21" y1="12" x2="9" y2="12" stroke-linecap="round"/></svg>
                    </button>
                </form>
            </div>
        </aside>

        <div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleSidebar()"></div>

        <main class="main">
            <div class="mobile-topbar">
                <button class="menu-btn" onclick="toggleSidebar()" aria-label="Buka menu">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3.5 6h17M3.5 12h17M3.5 18h17" stroke-linecap="round"/></svg>
                </button>
                <img src="{{ asset('images/srn-logo.png') }}" alt="SRN Partner Network" style="height:26px; width:auto;">
            </div>
            <div class="main-inner">
                @yield('content')
            </div>
        </main>
    </div>

    <script>
        function toggleNavGroup(subId, chevronId) {
            const sub = document.getElementById(subId);
            const chevron = document.getElementById(chevronId);
            const isOpen = sub.style.display === 'flex';
            sub.style.display = isOpen ? 'none' : 'flex';
            chevron.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(90deg)';
        }

        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('open');
            document.getElementById('sidebarBackdrop').classList.toggle('open');
        }

        function toggleSidebarCollapse() {
            const collapsed = document.documentElement.classList.toggle('sidebar-collapsed');
            localStorage.setItem('srn-sidebar-collapsed', collapsed ? '1' : '0');
        }
    </script>
</body>
</html>
