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
 * Devuelve la IP del cliente. Por defecto usa REMOTE_ADDR.
 * Si se configura APP_TRUST_PROXY=true en el .env, respeta el primer
 * IP válido de X-Forwarded-For (úsalo solo si hay un proxy/CDN delante).
 */
function client_ip(): string
{
    if (\App\Core\Env::getBool('APP_TRUST_PROXY', false)) {
        $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($xff) {
            foreach (explode(',', $xff) as $candidate) {
                $candidate = trim($candidate);
                if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                    return $candidate;
                }
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
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
 * Rol del usuario en sesión
 */
function auth_rol(): string
{
    return Session::get('usuario_rol', 'usuario');
}

/**
 * Helpers de rol — modelo SaaS multi-org.
 *
 * - es_admin(): el usuario es owner o admin de la org activa.
 *   También es admin si su rol global de cuenta (usuarios.rol) es 'admin'
 *   (super-admin de sistema, raramente usado).
 * - es_director(): el usuario puede modificar datos (no es lector).
 * - es_lector(): solo lectura.
 */
function es_admin(): bool
{
    if (auth_rol() === 'admin') return true;
    return \App\Core\OrgContext::esAdmin();
}
function es_director(): bool
{
    if (in_array(auth_rol(), ['admin', 'director'], true)) return true;
    return \App\Core\OrgContext::esOperarioOSuperior();
}
function es_lector(): bool { return \App\Core\OrgContext::esLector(); }

/**
 * ID de la organización activa del usuario en sesión. Toda query de datos
 * del cliente debe filtrar por este id.
 */
function org_id(): int { return \App\Core\OrgContext::id(); }
function org_rol(): string { return \App\Core\OrgContext::rol(); }

/**
 * Aborta con 403 si el usuario no tiene el rol mínimo requerido.
 * Loguea el intento y muestra una vista 403 con el layout normal.
 */
function require_rol(string $rolMinimo): void
{
    $jerarquia = ['usuario' => 1, 'director' => 2, 'admin' => 3];
    $actual    = $jerarquia[auth_rol()] ?? 1;
    $requerido = $jerarquia[$rolMinimo] ?? 1;

    if ($actual < $requerido) {
        \App\Core\SecurityLog::log('forbidden', [
            'user_id'      => Session::get('usuario_id'),
            'rol_actual'   => auth_rol(),
            'rol_requerido'=> $rolMinimo,
        ]);
        http_response_code(403);
        // Si hay sesión, mostramos la vista con layout principal;
        // si no, vista standalone (no debería ocurrir porque
        // require_rol siempre va tras auth_required).
        $layout = Session::has('usuario_id') ? 'main' : 'none';
        view('errors/403', ['pageTitle' => '403 — Sin permiso'], $layout);
        exit;
    }
}

/**
 * Pone en mayúscula la primera letra de cada palabra en un string
 */
function capitalizar(string $texto): string
{
    return mb_convert_case(mb_strtolower(trim($texto)), MB_CASE_TITLE, 'UTF-8');
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
