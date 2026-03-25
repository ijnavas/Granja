<?php
declare(strict_types=1);

use App\Core\Session;

/**
 * Escapa HTML para evitar XSS
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Redirige a una URL relativa a base_url
 */
function redirect(string $path): never
{
    $cfg = require ROOT_PATH . '/config.php';
    header('Location: ' . rtrim($cfg['app']['base_url'], '/') . '/' . ltrim($path, '/'));
    exit;
}

/**
 * Renderiza una vista (incluye layout si se indica)
 */
function view(string $view, array $data = [], string $layout = 'main'): void
{
    extract($data, EXTR_SKIP);

    $viewFile = ROOT_PATH . '/views/' . $view . '.php';
    if (!file_exists($viewFile)) {
        throw new \RuntimeException("Vista no encontrada: {$view}");
    }

    if ($layout === 'none') {
        require $viewFile;
        return;
    }

    // Captura el contenido de la vista
    ob_start();
    require $viewFile;
    $content = ob_get_clean();

    $layoutFile = ROOT_PATH . '/views/layouts/' . $layout . '.php';
    if (!file_exists($layoutFile)) {
        throw new \RuntimeException("Layout no encontrado: {$layout}");
    }

    require $layoutFile;
}

/**
 * Devuelve la URL base de la app
 */
function base_url(string $path = ''): string
{
    $cfg = require ROOT_PATH . '/config.php';
    return rtrim($cfg['app']['base_url'], '/') . '/' . ltrim($path, '/');
}

/**
 * Retorna el token CSRF como campo oculto HTML
 */
function csrf_field(): string
{
    $token = Session::csrfToken();
    return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

/**
 * Comprueba si el usuario está autenticado; si no, redirige
 */
function auth_required(): void
{
    if (!Session::has('usuario_id')) {
        redirect('login');
    }
}

/**
 * Comprueba si NO está autenticado; si lo está, redirige al panel
 */
function guest_only(): void
{
    if (Session::has('usuario_id')) {
        redirect('dashboard');
    }
}

/**
 * Datos del usuario en sesión
 */
function auth_user(): ?array
{
    if (!Session::has('usuario_id')) return null;
    return [
        'id'     => Session::get('usuario_id'),
        'nombre' => Session::get('usuario_nombre'),
        'email'  => Session::get('usuario_email'),
    ];
}
