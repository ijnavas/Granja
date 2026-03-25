<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BALTAE – <?= e($pageTitle ?? 'Panel') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --sidebar-w: 240px;
            --sidebar-collapsed: 64px;
            --topbar-h: 56px;
            --bg: #f0f2f5;
            --sidebar-bg: #111827;
            --sidebar-text: #9ca3af;
            --sidebar-text-active: #ffffff;
            --sidebar-hover: rgba(255,255,255,.06);
            --sidebar-active: rgba(255,255,255,.10);
            --sidebar-accent: #3b82f6;
            --card-bg: #ffffff;
            --text: #111827;
            --text-2: #6b7280;
            --border: #e5e7eb;
            --radius: 10px;
            --transition: .22s cubic-bezier(.4,0,.2,1);
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
        }

        /* ── SIDEBAR ──────────────────────────────────────────── */
        .sidebar {
            width: var(--sidebar-w);
            min-height: 100vh;
            background: var(--sidebar-bg);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            position: fixed;
            top: 0; left: 0; bottom: 0;
            z-index: 100;
            transition: width var(--transition);
            overflow: hidden;
        }

        .sidebar.collapsed { width: var(--sidebar-collapsed); }

        .sidebar-logo {
            height: var(--topbar-h);
            display: flex;
            align-items: center;
            padding: 0 1.1rem;
            border-bottom: 1px solid rgba(255,255,255,.07);
            flex-shrink: 0;
            gap: .75rem;
            overflow: hidden;
        }

        .logo-icon {
            width: 32px;
            height: 32px;
            background: var(--sidebar-accent);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-weight: 700;
            font-size: .85rem;
            color: #fff;
        }

        .logo-text {
            font-size: 1rem;
            font-weight: 600;
            color: #fff;
            white-space: nowrap;
            opacity: 1;
            transition: opacity var(--transition);
        }

        .sidebar.collapsed .logo-text { opacity: 0; pointer-events: none; }

        .sidebar-nav {
            flex: 1;
            padding: .75rem 0;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .nav-section-label {
            font-size: .65rem;
            font-weight: 600;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: rgba(255,255,255,.25);
            padding: .9rem 1.25rem .3rem;
            white-space: nowrap;
            transition: opacity var(--transition);
        }

        .sidebar.collapsed .nav-section-label { opacity: 0; }

        .nav-item {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .6rem 1.25rem;
            color: var(--sidebar-text);
            text-decoration: none;
            font-size: .875rem;
            font-weight: 500;
            transition: background var(--transition), color var(--transition);
            white-space: nowrap;
            overflow: hidden;
            position: relative;
        }

        .nav-item:hover { background: var(--sidebar-hover); color: #e5e7eb; }

        .nav-item.active {
            background: var(--sidebar-active);
            color: var(--sidebar-text-active);
        }

        .nav-item.active::before {
            content: '';
            position: absolute;
            left: 0; top: 5px; bottom: 5px;
            width: 3px;
            background: var(--sidebar-accent);
            border-radius: 0 3px 3px 0;
        }

        .nav-icon {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .nav-icon svg { width: 18px; height: 18px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }

        .nav-label { opacity: 1; transition: opacity var(--transition); }
        .sidebar.collapsed .nav-label { opacity: 0; pointer-events: none; }

        .nav-tooltip {
            display: none;
            position: absolute;
            left: calc(var(--sidebar-collapsed) + 10px);
            top: 50%;
            transform: translateY(-50%);
            background: #1f2937;
            color: #f9fafb;
            font-size: .8rem;
            padding: .3rem .75rem;
            border-radius: 6px;
            white-space: nowrap;
            z-index: 200;
            box-shadow: 0 4px 12px rgba(0,0,0,.3);
        }

        .sidebar.collapsed .nav-item:hover .nav-tooltip { display: block; }

        .sidebar-footer {
            border-top: 1px solid rgba(255,255,255,.07);
            padding: .6rem .75rem;
            flex-shrink: 0;
        }

        .btn-collapse {
            display: flex;
            align-items: center;
            gap: .75rem;
            width: 100%;
            padding: .55rem .65rem;
            background: none;
            border: none;
            color: var(--sidebar-text);
            cursor: pointer;
            border-radius: 7px;
            font-size: .875rem;
            font-family: inherit;
            transition: background var(--transition), color var(--transition);
            white-space: nowrap;
            overflow: hidden;
        }

        .btn-collapse:hover { background: var(--sidebar-hover); color: #e5e7eb; }

        .collapse-arrow { width: 18px; height: 18px; flex-shrink: 0; transition: transform var(--transition); }
        .sidebar.collapsed .collapse-arrow { transform: rotate(180deg); }

        .collapse-label { opacity: 1; transition: opacity var(--transition); }
        .sidebar.collapsed .collapse-label { opacity: 0; }

        /* ── MAIN ─────────────────────────────────────────────── */
        .main-wrap {
            margin-left: var(--sidebar-w);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            transition: margin-left var(--transition);
        }

        .main-wrap.collapsed { margin-left: var(--sidebar-collapsed); }

        .topbar {
            height: var(--topbar-h);
            background: var(--card-bg);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.75rem;
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .topbar-title { font-size: 1rem; font-weight: 600; color: var(--text); }

        .topbar-right { display: flex; align-items: center; gap: .75rem; }

        .user-badge { display: flex; align-items: center; gap: .55rem; font-size: .875rem; color: var(--text-2); }

        .user-avatar {
            width: 32px; height: 32px;
            background: var(--sidebar-accent);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            color: #fff; font-weight: 600; font-size: .8rem; flex-shrink: 0;
        }

        .btn-logout {
            font-size: .8rem;
            color: var(--text-2);
            text-decoration: none;
            padding: .3rem .65rem;
            border-radius: 6px;
            border: 1px solid var(--border);
            transition: all .15s;
        }

        .btn-logout:hover { background: #fef2f2; color: #dc2626; border-color: #fecaca; }

        .main-content { padding: 1.75rem; flex: 1; }

        /* ── MOBILE ───────────────────────────────────────────── */
        .mobile-overlay {
            display: none;
            position: fixed; inset: 0;
            background: rgba(0,0,0,.45);
            z-index: 99;
        }

        .btn-mobile-menu {
            display: none;
            align-items: center; justify-content: center;
            width: 34px; height: 34px;
            background: none;
            border: 1px solid var(--border);
            border-radius: 7px;
            cursor: pointer;
            color: var(--text);
            margin-right: .5rem;
        }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); transition: transform var(--transition), width var(--transition); }
            .sidebar.mobile-open { transform: translateX(0); width: var(--sidebar-w) !important; }
            .mobile-overlay.visible { display: block; }
            .main-wrap { margin-left: 0 !important; }
            .btn-mobile-menu { display: flex; }
        }
    </style>
</head>
<body>

<div class="mobile-overlay" id="mobileOverlay" onclick="closeMobile()"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="logo-icon">B</div>
        <span class="logo-text">BALTAE</span>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-label">Principal</div>

        <a href="<?= base_url('dashboard') ?>" class="nav-item <?= str_contains($_SERVER['REQUEST_URI'], '/dashboard') ? 'active' : '' ?>">
            <span class="nav-icon">
                <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            </span>
            <span class="nav-label">Dashboard</span>
            <span class="nav-tooltip">Dashboard</span>
        </a>

        <div class="nav-section-label">Gestión</div>

        <a href="<?= base_url('granjas') ?>" class="nav-item <?= str_contains($_SERVER['REQUEST_URI'], '/granjas') ? 'active' : '' ?>">
            <span class="nav-icon">
                <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            </span>
            <span class="nav-label">Granjas</span>
            <span class="nav-tooltip">Granjas</span>
        </a>

        <a href="<?= base_url('naves') ?>" class="nav-item <?= str_contains($_SERVER['REQUEST_URI'], '/naves') ? 'active' : '' ?>">
            <span class="nav-icon">
                <svg viewBox="0 0 24 24"><path d="M2 20h20M4 20V10l8-7 8 7v10"/><path d="M10 20v-6h4v6"/></svg>
            </span>
            <span class="nav-label">Naves</span>
            <span class="nav-tooltip">Naves</span>
        </a>

        <a href="<?= base_url('silos') ?>" class="nav-item <?= str_contains($_SERVER['REQUEST_URI'], '/silos') ? 'active' : '' ?>">
            <span class="nav-icon">
                <svg viewBox="0 0 24 24"><rect x="8" y="2" width="8" height="20" rx="2"/><path d="M8 6H4v14h4M16 6h4v14h-4"/></svg>
            </span>
            <span class="nav-label">Silos</span>
            <span class="nav-tooltip">Silos</span>
        </a>

        <a href="<?= base_url('lotes') ?>" class="nav-item <?= str_contains($_SERVER['REQUEST_URI'], '/lotes') ? 'active' : '' ?>">
            <span class="nav-icon">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M8 12h8M12 8v8"/></svg>
            </span>
            <span class="nav-label">Lotes</span>
            <span class="nav-tooltip">Lotes</span>
        </a>

        <div class="nav-section-label">Operaciones</div>

        <a href="<?= base_url('movimientos') ?>" class="nav-item <?= str_contains($_SERVER['REQUEST_URI'], '/movimientos') ? 'active' : '' ?>">
            <span class="nav-icon">
                <svg viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </span>
            <span class="nav-label">Movimientos</span>
            <span class="nav-tooltip">Movimientos</span>
        </a>

        <a href="<?= base_url('pesajes') ?>" class="nav-item <?= str_contains($_SERVER['REQUEST_URI'], '/pesajes') ? 'active' : '' ?>">
            <span class="nav-icon">
                <svg viewBox="0 0 24 24"><path d="M12 2a4 4 0 0 1 4 4H8a4 4 0 0 1 4-4z"/><path d="M4 6h16l-2 14H6L4 6z"/></svg>
            </span>
            <span class="nav-label">Pesajes</span>
            <span class="nav-tooltip">Pesajes</span>
        </a>

        <a href="<?= base_url('informes') ?>" class="nav-item <?= str_contains($_SERVER['REQUEST_URI'], '/informes') ? 'active' : '' ?>">
            <span class="nav-icon">
                <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/></svg>
            </span>
            <span class="nav-label">Informes</span>
            <span class="nav-tooltip">Informes</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <button class="btn-collapse" onclick="toggleSidebar()">
            <svg class="collapse-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M15 18l-6-6 6-6"/>
            </svg>
            <span class="collapse-label">Colapsar</span>
        </button>
    </div>
</aside>

<div class="main-wrap" id="mainWrap">
    <header class="topbar">
        <div style="display:flex;align-items:center">
            <button class="btn-mobile-menu" onclick="openMobile()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <span class="topbar-title"><?= e($pageTitle ?? 'Panel') ?></span>
        </div>
        <div class="topbar-right">
            <?php $u = auth_user(); ?>
            <div class="user-badge">
                <div class="user-avatar"><?= strtoupper(substr($u['nombre'] ?? 'U', 0, 1)) ?></div>
                <span><?= e($u['nombre'] ?? '') ?></span>
            </div>
            <a href="<?= base_url('logout') ?>" class="btn-logout">Salir</a>
        </div>
    </header>

    <main class="main-content">
        <?= $content ?>
    </main>
</div>

<script>
    const sidebar  = document.getElementById('sidebar');
    const mainWrap = document.getElementById('mainWrap');
    const overlay  = document.getElementById('mobileOverlay');
    const KEY      = 'baltae_collapsed';

    function toggleSidebar() {
        const c = sidebar.classList.toggle('collapsed');
        mainWrap.classList.toggle('collapsed', c);
        localStorage.setItem(KEY, c ? '1' : '0');
    }

    function openMobile() {
        sidebar.classList.add('mobile-open');
        overlay.classList.add('visible');
    }

    function closeMobile() {
        sidebar.classList.remove('mobile-open');
        overlay.classList.remove('visible');
    }

    if (localStorage.getItem(KEY) === '1') {
        sidebar.classList.add('collapsed');
        mainWrap.classList.add('collapsed');
    }
</script>
</body>
</html>
