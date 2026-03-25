<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BALTAE – <?= $pageTitle ?? 'Acceso' ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f4f6f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .auth-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 16px rgba(0,0,0,.08);
            padding: 2.5rem 2rem;
            width: 100%;
            max-width: 420px;
        }

        .auth-logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .auth-logo h1 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #1a56db;
            letter-spacing: -0.5px;
        }

        .auth-logo p {
            font-size: .85rem;
            color: #6b7280;
            margin-top: .25rem;
        }

        .alert {
            padding: .75rem 1rem;
            border-radius: 8px;
            font-size: .875rem;
            margin-bottom: 1.25rem;
            line-height: 1.5;
        }

        .alert-error   { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }

        .form-group { margin-bottom: 1.1rem; }

        label {
            display: block;
            font-size: .875rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: .35rem;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: .65rem .9rem;
            border: 1.5px solid #d1d5db;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color .15s;
            outline: none;
            color: #111827;
        }

        input:focus { border-color: #1a56db; }

        .btn-primary {
            display: block;
            width: 100%;
            padding: .75rem;
            background: #1a56db;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background .15s;
            margin-top: 1.5rem;
        }

        .btn-primary:hover { background: #1447c0; }

        .auth-footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: .875rem;
            color: #6b7280;
        }

        .auth-footer a { color: #1a56db; text-decoration: none; font-weight: 500; }
        .auth-footer a:hover { text-decoration: underline; }

        .password-hint {
            font-size: .78rem;
            color: #9ca3af;
            margin-top: .3rem;
        }
    </style>
</head>
<body>
    <div class="auth-card">
        <div class="auth-logo">
            <h1>BALTAE</h1>
            <p>Gestión de granjas</p>
        </div>

        <?= $content ?>
    </div>
</body>
</html>
