<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BALTAE – <?= e($pageTitle ?? 'Panel') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('css/app.css') ?>">
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
            <span class="nav-icon"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg></span>
            <span class="nav-label">Dashboard</span>
            <span class="nav-tooltip">Dashboard</span>
        </a>

        <div class="nav-section-label">Gestión</div>

        <a href="<?= base_url('granjas') ?>" class="nav-item <?= str_contains($_SERVER['REQUEST_URI'], '/granjas') ? 'active' : '' ?>">
            <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span>
            <span class="nav-label">Granjas</span>
            <span class="nav-tooltip">Granjas</span>
        </a>

        <a href="<?= base_url('naves') ?>" class="nav-item <?= str_contains($_SERVER['REQUEST_URI'], '/naves') ? 'active' : '' ?>">
            <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M2 20h20M4 20V10l8-7 8 7v10"/><path d="M10 20v-6h4v6"/></svg></span>
            <span class="nav-label">Naves</span>
            <span class="nav-tooltip">Naves</span>
        </a>

        <a href="<?= base_url('cuadras') ?>" class="nav-item <?= str_contains($_SERVER['REQUEST_URI'], '/cuadras') ? 'active' : '' ?>">
            <span class="nav-icon"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="8" height="8" rx="1"/><rect x="13" y="3" width="8" height="8" rx="1"/><rect x="3" y="13" width="8" height="8" rx="1"/><rect x="13" y="13" width="8" height="8" rx="1"/></svg></span>
            <span class="nav-label">Cuadras</span>
            <span class="nav-tooltip">Cuadras</span>
        </a>

        <a href="<?= base_url('silos') ?>" class="nav-item <?= str_contains($_SERVER['REQUEST_URI'], '/silos') ? 'active' : '' ?>">
            <span class="nav-icon"><svg viewBox="0 0 24 24"><rect x="8" y="2" width="8" height="20" rx="2"/><path d="M8 6H4v14h4M16 6h4v14h-4"/></svg></span>
            <span class="nav-label">Silos</span>
            <span class="nav-tooltip">Silos</span>
        </a>

        <a href="<?= base_url('lotes') ?>" class="nav-item <?= str_contains($_SERVER['REQUEST_URI'], '/lotes') ? 'active' : '' ?>">
            <span class="nav-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M8 12h8M12 8v8"/></svg></span>
            <span class="nav-label">Lotes</span>
            <span class="nav-tooltip">Lotes</span>
        </a>

        <div class="nav-section-label">Operaciones</div>

        <a href="<?= base_url('movimientos') ?>" class="nav-item <?= str_contains($_SERVER['REQUEST_URI'], '/movimientos') ? 'active' : '' ?>">
            <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg></span>
            <span class="nav-label">Movimientos</span>
            <span class="nav-tooltip">Movimientos</span>
        </a>

        <a href="<?= base_url('inventarios') ?>" class="nav-item <?= str_contains($_SERVER['REQUEST_URI'], '/inventarios') ? 'active' : '' ?>">
            <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><line x1="9" y1="12" x2="15" y2="12"/><line x1="9" y1="16" x2="13" y2="16"/></svg></span>
            <span class="nav-label">Inventarios</span>
            <span class="nav-tooltip">Inventarios</span>
        </a>

        <a href="<?= base_url('pesajes') ?>" class="nav-item <?= str_contains($_SERVER['REQUEST_URI'], '/pesajes') ? 'active' : '' ?>">
            <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M12 2a4 4 0 0 1 4 4H8a4 4 0 0 1 4-4z"/><path d="M4 6h16l-2 14H6L4 6z"/></svg></span>
            <span class="nav-label">Pesajes</span>
            <span class="nav-tooltip">Pesajes</span>
        </a>

        <a href="<?= base_url('informes') ?>" class="nav-item <?= str_contains($_SERVER['REQUEST_URI'], '/informes') ? 'active' : '' ?>">
            <span class="nav-icon"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/></svg></span>
            <span class="nav-label">Informes</span>
            <span class="nav-tooltip">Informes</span>
        </a>

        <?php if (es_admin()): ?>
        <div class="nav-section-label">Sistema</div>
        <a href="<?= base_url('configuracion/razas') ?>" class="nav-item <?= str_contains($_SERVER['REQUEST_URI'], '/configuracion') ? 'active' : '' ?>">
            <span class="nav-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span>
            <span class="nav-label">Configuración</span>
            <span class="nav-tooltip">Configuración</span>
        </a>
        <?php endif; ?>
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
            <a href="<?= base_url('perfil') ?>" class="user-badge" style="text-decoration:none;color:inherit" title="Mi perfil">
                <div class="user-avatar"><?= strtoupper(substr($u['nombre'] ?? 'U', 0, 1)) ?></div>
                <span><?= e($u['nombre'] ?? '') ?></span>
            </a>
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