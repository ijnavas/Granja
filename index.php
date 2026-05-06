<?php
declare(strict_types=1);

// ROOT_PATH apunta a la misma carpeta donde está index.php
define('ROOT_PATH', __DIR__);

// Autoloader PSR-4 simple (sin Composer)
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $base   = ROOT_PATH . '/app/';

    if (!str_starts_with($class, $prefix)) return;

    $relative = substr($class, strlen($prefix));
    $file     = $base . str_replace('\\', '/', $relative) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Helpers globales
require ROOT_PATH . '/app/Helpers/functions.php';

// Cargar variables de entorno desde .env (si existe)
\App\Core\Env::load(ROOT_PATH . '/.env');

// ── Configuración de errores ─────────────────────────────────
// En producción: NUNCA mostrar errores al usuario, solo loguearlos.
// En desarrollo (APP_DEBUG=true): mostrar todo.
$__debug = \App\Core\Env::getBool('APP_DEBUG', false);
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('display_errors', $__debug ? '1' : '0');
ini_set('display_startup_errors', $__debug ? '1' : '0');

// ── Headers de seguridad HTTP ────────────────────────────────
// Se envían en TODA respuesta. CSP permite 'unsafe-inline' porque la
// app aún tiene muchos onclick= y style="" inline; refactor a nonces
// queda pendiente para cuando migremos handlers a addEventListener.
//
// La CSP es context-aware: las páginas que usan mapas (/granjas/*)
// reciben una política ampliada para permitir Leaflet + OSM + Arcgis
// + Nominatim. El resto recibe una política más restrictiva.
if (!headers_sent()) {
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('X-Permitted-Cross-Domain-Policies: none');
    header('Permissions-Policy: geolocation=(self), microphone=(), camera=(), payment=(), usb=()');
    if (($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }

    // ¿Esta request carga mapas? (solo formularios/ficha de granjas)
    $__uri      = $_SERVER['REQUEST_URI'] ?? '/';
    $__needsMap = (bool) preg_match('#/granjas(/|$|\?)#', $__uri);

    // Directivas base (cubren el 95% de las páginas)
    $csp = [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline'",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
        "font-src 'self' https://fonts.gstatic.com data:",
        "img-src 'self' data: blob:",
        "connect-src 'self'",
        "frame-ancestors 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "object-src 'none'",
        "worker-src 'self' blob:",
        "manifest-src 'self'",
        "upgrade-insecure-requests",
        // Reporta (sin romper) cualquier violación a nuestro endpoint.
        "report-uri " . base_url('csp-report'),
    ];

    // Extras solo para páginas con mapa
    if ($__needsMap) {
        $csp[1] = "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com";
        $csp[2] = "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com";
        $csp[4] = "img-src 'self' data: blob: https://*.tile.openstreetmap.org https://*.arcgisonline.com";
        $csp[5] = "connect-src 'self' https://nominatim.openstreetmap.org";
    }

    header('Content-Security-Policy: ' . implode('; ', $csp));
}

// Handler global: convierte cualquier excepción no capturada en un 500
// genérico sin filtrar detalles.
set_exception_handler(function (\Throwable $e) use ($__debug): void {
    error_log('Uncaught ' . get_class($e) . ': ' . $e->getMessage()
        . ' in ' . $e->getFile() . ':' . $e->getLine()
        . "\n" . $e->getTraceAsString());
    http_response_code(500);
    if ($__debug) {
        echo '<pre style="font-family:monospace;padding:2rem">';
        echo htmlspecialchars((string)$e, ENT_QUOTES, 'UTF-8');
        echo '</pre>';
    } else {
        echo '<h1 style="font-family:sans-serif;padding:2rem">500 — Error interno del servidor</h1>';
    }
    exit;
});

// Iniciar sesión
use App\Core\Session;
use App\Core\Router;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\GranjaController;
use App\Controllers\NaveController;
use App\Controllers\SiloController;
use App\Controllers\LoteController;
use App\Controllers\CuadraController;

use App\Controllers\MovimientoController;
use App\Controllers\InventarioController;
use App\Controllers\ConfigController;
use App\Controllers\PerfilController;
use App\Controllers\PesajeController;
use App\Controllers\AlmacenController;
use App\Controllers\EscaneoController;
use App\Controllers\RecevtController;
use App\Controllers\AuditLogController;
use App\Controllers\SecurityController;

Session::start();

// ── Rutas ────────────────────────────────────────────────────
$router = new Router();

// Auth
$router->get('/',            [AuthController::class,     'loginForm']);
$router->get('/login',       [AuthController::class,     'loginForm']);
$router->post('/login',      [AuthController::class,     'login']);
$router->get('/register',    [AuthController::class,     'registerForm']);
$router->post('/register',   [AuthController::class,     'register']);
$router->post('/logout',          [AuthController::class, 'logout']);
$router->get('/forgot-password',  [AuthController::class, 'forgotPasswordForm']);
$router->post('/forgot-password', [AuthController::class, 'forgotPassword']);
$router->get('/reset-password/{token}',  [AuthController::class, 'resetPasswordForm']);
$router->post('/reset-password/{token}', [AuthController::class, 'resetPassword']);

// Dashboard
$router->get('/dashboard',   [DashboardController::class, 'index']);

// Granjas
$router->get('/granjas',                    [GranjaController::class, 'index']);
$router->get('/granjas/crear',              [GranjaController::class, 'create']);
$router->post('/granjas',                   [GranjaController::class, 'store']);
$router->get('/granjas/{id}',               [GranjaController::class, 'show']);
$router->get('/granjas/{id}/editar',        [GranjaController::class, 'edit']);
$router->post('/granjas/{id}/actualizar',   [GranjaController::class, 'update']);
$router->post('/granjas/{id}/eliminar',     [GranjaController::class, 'delete']);

// Naves
$router->get('/naves',                      [NaveController::class, 'index']);
$router->get('/naves/crear',                [NaveController::class, 'create']);
$router->post('/naves',                     [NaveController::class, 'store']);
$router->get('/naves/{id}',                 [NaveController::class, 'show']);
$router->get('/naves/{id}/editar',          [NaveController::class, 'edit']);
$router->post('/naves/{id}/actualizar',     [NaveController::class, 'update']);
$router->post('/naves/{id}/eliminar',       [NaveController::class, 'delete']);

// Silos
$router->get('/silos',                      [SiloController::class, 'index']);
$router->get('/silos/crear',                [SiloController::class, 'create']);
$router->post('/silos',                     [SiloController::class, 'store']);
$router->get('/silos/{id}',                 [SiloController::class, 'show']);
$router->get('/silos/{id}/editar',          [SiloController::class, 'edit']);
$router->post('/silos/{id}/actualizar',     [SiloController::class, 'update']);
$router->post('/silos/{id}/tarar',           [SiloController::class, 'tarar']);
$router->post('/silos/{id}/calibraciones/{cid}/eliminar', [SiloController::class, 'deleteCalibracion']);
$router->post('/silos/{id}/eliminar',       [SiloController::class, 'delete']);
// Almacén (operativa de pienso)
$router->get('/almacen',                              [AlmacenController::class, 'index']);
$router->get('/almacen/{id}',                         [AlmacenController::class, 'show']);
$router->post('/almacen/{id}/recarga',                [AlmacenController::class, 'storeRecarga']);
$router->get('/almacen/recargas/{rid}/editar',        [AlmacenController::class, 'editRecarga']);
$router->post('/almacen/recargas/{rid}/actualizar',   [AlmacenController::class, 'updateRecarga']);
$router->post('/almacen/{id}/recarga/{rid}/eliminar', [AlmacenController::class, 'deleteRecarga']);
$router->post('/almacen/{id}/pedido',                 [AlmacenController::class, 'pedido']);
$router->get('/almacen/export',                       [AlmacenController::class, 'export']);

// Lotes
$router->get('/lotes/export',               [LoteController::class, 'export']);
$router->get('/lotes',                      [LoteController::class, 'index']);
$router->get('/lotes/crear',                [LoteController::class, 'create']);
$router->post('/lotes',                     [LoteController::class, 'store']);
$router->get('/lotes/tabla-semana',          [LoteController::class, 'tablaSemana']);
$router->post('/lotes/raza',                 [LoteController::class, 'crearRaza']);
$router->get('/lotes/historico',            [LoteController::class, 'historico']);
$router->get('/lotes/{id}/historico',       [LoteController::class, 'historicoShow']);
$router->get('/lotes/{id}/editar',          [LoteController::class, 'edit']);
$router->post('/lotes/{id}/actualizar',     [LoteController::class, 'update']);
$router->post('/lotes/{id}/ajustar',        [LoteController::class, 'ajustar']);
$router->post('/lotes/{id}/eliminar',       [LoteController::class, 'delete']);
$router->post('/lotes/{id}/borrar',         [LoteController::class, 'borrar']);

// Cuadras
$router->get('/cuadras',                        [CuadraController::class, 'index']);
$router->get('/cuadras/crear',                  [CuadraController::class, 'create']);
$router->get('/cuadras/masiva',                 [CuadraController::class, 'createMasiva']);
$router->post('/cuadras/masiva',                [CuadraController::class, 'storeMasiva']);
$router->post('/cuadras',                       [CuadraController::class, 'store']);
$router->get('/cuadras/{id}',                   [CuadraController::class, 'show']);
$router->get('/cuadras/{id}/editar',            [CuadraController::class, 'edit']);
$router->post('/cuadras/{id}/actualizar',       [CuadraController::class, 'update']);
$router->post('/cuadras/{id}/eliminar',         [CuadraController::class, 'delete']);
$router->post('/cuadras/{id}/asignar',          [CuadraController::class, 'asignarLote']);
$router->post('/cuadras/{id}/retirar',          [CuadraController::class, 'retirarLote']);

// Movimientos
$router->get('/movimientos/export',                  [MovimientoController::class, 'export']);
$router->get('/movimientos',                        [MovimientoController::class, 'index']);
$router->get('/movimientos/crear',                  [MovimientoController::class, 'create']);
$router->post('/movimientos',                       [MovimientoController::class, 'store']);
$router->get('/movimientos/cuadras',                [MovimientoController::class, 'cuadrasPorNave']);
$router->get('/movimientos/lotes-cuadra',           [MovimientoController::class, 'lotesPorCuadra']);
$router->get('/movimientos/cuadras-lote',           [MovimientoController::class, 'cuadrasPorLote']);
$router->get('/movimientos/todas-cuadras',          [MovimientoController::class, 'todasLasCuadras']);
$router->get('/movimientos/peso-estimado',          [MovimientoController::class, 'pesoEstimado']);
$router->get('/movimientos/lotes-de-nave',          [MovimientoController::class, 'lotesPorNave']);
$router->get('/movimientos/{id}/inventarios-afectados', [MovimientoController::class, 'inventariosAfectados']);

// Escaneo de cuaderno con IA
$router->get('/escaneo',                            [EscaneoController::class, 'form']);
$router->post('/escaneo/analizar',                  [EscaneoController::class, 'analizar']);
$router->post('/escaneo/confirmar',                 [EscaneoController::class, 'confirmar']);
$router->get('/movimientos/{id}/editar',            [MovimientoController::class, 'edit']);
$router->post('/movimientos/{id}/actualizar',       [MovimientoController::class, 'update']);
$router->post('/movimientos/{id}/eliminar',         [MovimientoController::class, 'delete']);

// Inventarios
$router->get('/inventarios',                        [InventarioController::class, 'index']);
$router->get('/inventarios/crear',                  [InventarioController::class, 'create']);
$router->post('/inventarios',                       [InventarioController::class, 'store']);
$router->get('/inventarios/preview',                [InventarioController::class, 'preview']);
$router->get('/inventarios/{id}',                   [InventarioController::class, 'show']);
$router->get('/inventarios/{id}/excel',             [InventarioController::class, 'excel']);
$router->post('/inventarios/{id}/email',            [InventarioController::class, 'email']);
$router->post('/inventarios/{id}/eliminar',         [InventarioController::class, 'delete']);

// Pesajes
$router->get('/pesajes/export',                              [PesajeController::class, 'export']);
$router->get('/pesajes',                                    [PesajeController::class, 'index']);
$router->get('/pesajes/crear',                              [PesajeController::class, 'create']);
$router->post('/pesajes',                                   [PesajeController::class, 'store']);
$router->get('/pesajes/{id}/editar',                        [PesajeController::class, 'edit']);
$router->post('/pesajes/{id}/actualizar',                   [PesajeController::class, 'update']);
$router->post('/pesajes/{id}/eliminar',                     [PesajeController::class, 'delete']);

// Perfil
$router->get('/perfil',                                     [PerfilController::class, 'show']);
$router->post('/perfil/info',                               [PerfilController::class, 'updateInfo']);
$router->post('/perfil/password',                           [PerfilController::class, 'updatePassword']);
$router->post('/perfil/recevet',                            [PerfilController::class, 'updateRecevet']);

// Recevet
$router->get('/recevet',                                    [RecevtController::class, 'index']);
$router->post('/recevet/iniciar-sesion',                    [RecevtController::class, 'iniciarSesion']);
$router->post('/recevet/verificar-codigo',                  [RecevtController::class, 'verificarCodigo']);
$router->post('/recevet/cancelar-login',                    [RecevtController::class, 'cancelarLogin']);
$router->get('/recevet/capturar-cookie',                    [RecevtController::class, 'capturarCookie']);
$router->post('/recevet/cerrar-sesion',                     [RecevtController::class, 'cerrarSesion']);
$router->post('/recevet/sincronizar',                       [RecevtController::class, 'sincronizar']);

$router->get('/configuracion',                              [ConfigController::class, 'index']);
$router->get('/configuracion/general',                       [ConfigController::class, 'general']);
$router->post('/configuracion/general',                      [ConfigController::class, 'actualizarGeneral']);
$router->post('/configuracion/motivos',                      [ConfigController::class, 'crearMotivo']);
$router->post('/configuracion/motivos/{id}/actualizar',      [ConfigController::class, 'actualizarMotivo']);
$router->post('/configuracion/motivos/{id}/eliminar',        [ConfigController::class, 'eliminarMotivo']);
$router->get('/configuracion/movimientos',                   [ConfigController::class, 'tiposMovimiento']);
$router->post('/configuracion/movimientos',                  [ConfigController::class, 'crearTipoMovimiento']);
$router->get('/configuracion/movimientos/{id}/editar',       [ConfigController::class, 'editarTipoMovimiento']);
$router->post('/configuracion/movimientos/{id}/actualizar',  [ConfigController::class, 'actualizarTipoMovimiento']);
$router->post('/configuracion/movimientos/{id}/eliminar',    [ConfigController::class, 'eliminarTipoMovimiento']);
$router->get('/configuracion/razas',                        [ConfigController::class, 'razas']);
$router->post('/configuracion/razas',                       [ConfigController::class, 'crearRaza']);
$router->get('/configuracion/razas/{id}/editar',            [ConfigController::class, 'editarRaza']);
$router->post('/configuracion/razas/{id}/actualizar',       [ConfigController::class, 'actualizarRaza']);
$router->post('/configuracion/razas/{id}/eliminar',         [ConfigController::class, 'eliminarRaza']);
$router->get('/configuracion/estados',                      [ConfigController::class, 'estados']);
$router->post('/configuracion/estados',                     [ConfigController::class, 'crearEstado']);
$router->get('/configuracion/estados/{id}/editar',          [ConfigController::class, 'editarEstado']);
$router->post('/configuracion/estados/{id}/actualizar',     [ConfigController::class, 'actualizarEstado']);
$router->post('/configuracion/estados/{id}/toggle',         [ConfigController::class, 'toggleEstado']);
$router->get('/configuracion/tablas',                       [ConfigController::class, 'tablas']);
$router->get('/configuracion/reset',                        [ConfigController::class, 'resetForm']);
$router->post('/configuracion/reset',                       [ConfigController::class, 'resetConfirm']);
$router->get('/configuracion/tablas/crear',                 [ConfigController::class, 'crearTabla']);
$router->post('/configuracion/tablas',                      [ConfigController::class, 'storeTabla']);
$router->get('/configuracion/tablas/{id}/editar',           [ConfigController::class, 'editarTabla']);
$router->post('/configuracion/tablas/{id}/actualizar',      [ConfigController::class, 'actualizarTabla']);
$router->post('/configuracion/tablas/{id}/eliminar',        [ConfigController::class, 'eliminarTabla']);

// ── Audit log (solo admin) ───────────────────────────────────
$router->get('/admin/audit-log',         [AuditLogController::class, 'index']);
$router->get('/admin/audit-log/{id}',    [AuditLogController::class, 'show']);

// ── Reportes de violaciones CSP (el navegador postea aquí) ──
$router->post('/csp-report',             [SecurityController::class, 'cspReport']);

$router->dispatch();