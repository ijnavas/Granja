<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>BALTAE – <?= e($pageTitle ?? 'Panel') ?></title>
    <link rel="icon" href="<?= base_url('favicon.ico') ?>" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= base_url('favicon_32.png') ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= base_url('favicon_16.png') ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= base_url('favicon_180.png') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= base_url('css/app.css') ?>">
    <link rel="stylesheet" href="<?= base_url('lib/flatpickr/flatpickr.min.css') ?>">
    <style>
        /* Refinamientos sobre Flatpickr para destacar la columna de semana */
        .flatpickr-weeks { background:#fef2f2; border-right:1px solid #fecaca }
        .flatpickr-weeks .flatpickr-weekday { color:#dc2626 }
        .flatpickr-weeks span.flatpickr-day { color:#dc2626; font-weight:700 }
        /* Igualar la altura del input alterno con los inputs nativos */
        input.flatpickr-alt-input { width:100%; padding:.6rem .85rem; border:1.5px solid #d1d5db; border-radius:7px; font-size:.9rem; font-family:inherit; background:#fff }
        input.flatpickr-alt-input:focus { outline:none; border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.15) }
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

        <a href="<?= base_url('silos') ?>" class="nav-item <?= str_contains($_SERVER['REQUEST_URI'], '/silos') && !str_contains($_SERVER['REQUEST_URI'], '/almacen') ? 'active' : '' ?>">
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

        <a href="<?= base_url('almacen') ?>" class="nav-item <?= str_contains($_SERVER['REQUEST_URI'], '/almacen') ? 'active' : '' ?>">
            <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><line x1="9" y1="22" x2="9" y2="12"/><line x1="15" y1="22" x2="15" y2="12"/><line x1="9" y1="12" x2="15" y2="12"/></svg></span>
            <span class="nav-label">Almacén</span>
            <span class="nav-tooltip">Almacén</span>
        </a>

        <a href="<?= base_url('escaneo') ?>" class="nav-item <?= str_contains($_SERVER['REQUEST_URI'], '/escaneo') ? 'active' : '' ?>">
            <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M3 15h18M9 3v18M15 3v18"/></svg></span>
            <span class="nav-label">Escanear</span>
            <span class="nav-tooltip">Escanear cuaderno</span>
        </a>

        <a href="<?= base_url('informes') ?>" class="nav-item <?= str_contains($_SERVER['REQUEST_URI'], '/informes') ? 'active' : '' ?>">
            <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg></span>
            <span class="nav-label">Informes</span>
            <span class="nav-tooltip">Informes y gráficos</span>
        </a>

        <a href="<?= base_url('equipo') ?>" class="nav-item <?= str_contains($_SERVER['REQUEST_URI'], '/equipo') ? 'active' : '' ?>">
            <span class="nav-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
            <span class="nav-label">Equipo</span>
            <span class="nav-tooltip">Equipo y miembros de la organización</span>
        </a>

        <div class="nav-section-label">Sistema</div>
        <?php $cfgHref = es_admin() ? 'configuracion/razas' : 'configuracion/general'; ?>
        <a href="<?= base_url($cfgHref) ?>" class="nav-item <?= (str_contains($_SERVER['REQUEST_URI'], '/configuracion') || str_contains($_SERVER['REQUEST_URI'], '/recevet') || str_contains($_SERVER['REQUEST_URI'], '/admin/audit-log')) ? 'active' : '' ?>">
            <span class="nav-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></span>
            <span class="nav-label">Configuración</span>
            <span class="nav-tooltip">Configuración</span>
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
            <?php
            $u           = auth_user();
            $misOrgs     = [];
            $orgActivaId = 0;
            $orgActivaNm = '';
            if (\App\Core\Session::has('usuario_id')) {
                $orgActivaId = \App\Core\OrgContext::id();
                $misOrgs     = (new \App\Models\Organizacion())->deUsuario((int)($u['id'] ?? 0));
                foreach ($misOrgs as $o) {
                    if ((int)$o['id'] === $orgActivaId) { $orgActivaNm = $o['nombre']; break; }
                }
            }
            ?>
            <?php if (count($misOrgs) > 1): ?>
            <div class="org-switcher" id="orgSwitcher">
                <button type="button" class="org-switcher-btn" onclick="document.getElementById('orgSwitcher').classList.toggle('open')">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4"/></svg>
                    <span class="org-switcher-name"><?= e($orgActivaNm ?: 'Org') ?></span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div class="org-switcher-menu">
                    <div class="org-switcher-label">Cambiar de organización</div>
                    <?php foreach ($misOrgs as $o): ?>
                        <?php if ((int)$o['id'] === $orgActivaId): ?>
                            <div class="org-switcher-item current">
                                <strong><?= e($o['nombre']) ?></strong>
                                <span class="org-switcher-rol"><?= e($o['rol']) ?></span>
                            </div>
                        <?php else: ?>
                            <form method="POST" action="<?= base_url('equipo/cambiar-org/' . (int)$o['id']) ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="org-switcher-item">
                                    <span><?= e($o['nombre']) ?></span>
                                    <span class="org-switcher-rol"><?= e($o['rol']) ?></span>
                                </button>
                            </form>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <a href="<?= base_url('perfil') ?>" class="user-badge" style="text-decoration:none;color:inherit" title="Mi perfil">
                <div class="user-avatar"><?= strtoupper(substr($u['nombre'] ?? 'U', 0, 1)) ?></div>
                <span><?= e($u['nombre'] ?? '') ?></span>
            </a>
            <form method="POST" action="<?= base_url('logout') ?>" class="logout-form">
                <?= csrf_field() ?>
                <button type="submit" class="btn-logout">Salir</button>
            </form>
        </div>
        <script>
            document.addEventListener('click', function(e) {
                const sw = document.getElementById('orgSwitcher');
                if (sw && !sw.contains(e.target)) sw.classList.remove('open');
            });
        </script>
    </header>

    <main class="main-content">
        <?= $content ?>
    </main>

    <footer class="app-footer">
        <div class="app-footer-inner">
            <span>&copy; <?= date('Y') ?> BALTAE</span>
            <span class="app-footer-sep">·</span>
            <a href="<?= base_url('legal/aviso') ?>">Aviso legal</a>
            <span class="app-footer-sep">·</span>
            <a href="<?= base_url('legal/privacidad') ?>">Privacidad</a>
            <span class="app-footer-sep">·</span>
            <a href="<?= base_url('legal/cookies') ?>">Cookies</a>
            <span class="app-footer-spacer"></span>
            <span class="app-footer-credit">
                Diseñado por
                <a href="https://manadi.es" target="_blank" rel="noopener noreferrer">Manadi</a>
            </span>
        </div>
    </footer>
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

<!-- Flatpickr: date picker con número de semana ISO en columna lateral -->
<script src="<?= base_url('lib/flatpickr/flatpickr.min.js') ?>"></script>
<script src="<?= base_url('lib/flatpickr/flatpickr-es.js') ?>"></script>
<script>
    // Aplicar locale español globalmente (lunes como inicio de semana, ISO weeks)
    if (typeof flatpickr === 'function' && window.es) {
        flatpickr.localize(window.es);
    }

    // Inicializa Flatpickr en cada input[type=date] no procesado todavía.
    // El picker muestra una columna de semanas a la izquierda (weekNumbers).
    // altInput=true: el usuario ve "06/05/2026" pero se envía "2026-05-06".
    function initFlatpickr(scope) {
        if (typeof flatpickr !== 'function') return;
        const root   = scope || document;
        const inputs = root.querySelectorAll('input[type="date"]:not(.flatpickr-input)');
        inputs.forEach(inp => {
            try {
                flatpickr(inp, {
                    dateFormat:    'Y-m-d',
                    altInput:      true,
                    altFormat:     'd/m/Y',
                    weekNumbers:   true,
                    allowInput:    true,
                    disableMobile: true, // forzar nuestro picker también en móvil
                });
            } catch (e) { console.error('flatpickr init', e); }
        });
    }
    document.addEventListener('DOMContentLoaded', () => initFlatpickr());
    // Inputs añadidos dinámicamente (cards de cuadras, repetidores, etc.)
    new MutationObserver((muts) => {
        for (const m of muts) {
            for (const n of m.addedNodes) {
                if (n.nodeType !== 1) continue;
                if (n.matches && n.matches('input[type="date"]:not(.flatpickr-input)')) initFlatpickr(n.parentNode);
                else if (n.querySelector && n.querySelector('input[type="date"]:not(.flatpickr-input)')) initFlatpickr(n);
            }
        }
    }).observe(document.body, { childList: true, subtree: true });
</script>
</body>
</html>