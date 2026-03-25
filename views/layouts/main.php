<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BALTAE – <?= e($pageTitle ?? 'Panel') ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f4f6f9; color: #111827; }
        .navbar {
            background: #1a56db;
            color: #fff;
            padding: .85rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .navbar-brand { font-weight: 700; font-size: 1.2rem; letter-spacing: -.5px; }
        .navbar-user { font-size: .875rem; }
        .navbar-user a { color: #bfdbfe; text-decoration: none; margin-left: 1rem; }
        .navbar-user a:hover { color: #fff; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem 1.5rem; }
    </style>
</head>
<body>
    <nav class="navbar">
        <span class="navbar-brand">BALTAE</span>
        <div class="navbar-user">
            <?php $u = auth_user(); ?>
            Hola, <?= e($u['nombre'] ?? '') ?>
            <a href="<?= base_url('logout') ?>">Cerrar sesión</a>
        </div>
    </nav>
    <div class="container">
        <?= $content ?>
    </div>
</body>
</html>
