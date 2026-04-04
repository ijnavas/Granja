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

Session::start();

// ── Rutas ────────────────────────────────────────────────────
$router = new Router();

// Auth
$router->get('/',            [AuthController::class,     'loginForm']);
$router->get('/login',       [AuthController::class,     'loginForm']);
$router->post('/login',      [AuthController::class,     'login']);
$router->get('/register',    [AuthController::class,     'registerForm']);
$router->post('/register',   [AuthController::class,     'register']);
$router->get('/logout',           [AuthController::class, 'logout']);
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
$router->post('/silos/{id}/eliminar',       [SiloController::class, 'delete']);

// Lotes
$router->get('/lotes',                      [LoteController::class, 'index']);
$router->get('/lotes/crear',                [LoteController::class, 'create']);
$router->post('/lotes',                     [LoteController::class, 'store']);
$router->get('/lotes/tabla-semana',          [LoteController::class, 'tablaSemana']);
$router->post('/lotes/raza',                 [LoteController::class, 'crearRaza']);
$router->get('/lotes/{id}/editar',          [LoteController::class, 'edit']);
$router->post('/lotes/{id}/actualizar',     [LoteController::class, 'update']);
$router->post('/lotes/{id}/ajustar',        [LoteController::class, 'ajustar']);
$router->post('/lotes/{id}/eliminar',       [LoteController::class, 'delete']);

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
$router->get('/movimientos',                        [MovimientoController::class, 'index']);
$router->get('/movimientos/crear',                  [MovimientoController::class, 'create']);
$router->post('/movimientos',                       [MovimientoController::class, 'store']);
$router->get('/movimientos/cuadras',                [MovimientoController::class, 'cuadrasPorNave']);
$router->get('/movimientos/lotes-cuadra',           [MovimientoController::class, 'lotesPorCuadra']);
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
$router->get('/pesajes',                                    [PesajeController::class, 'index']);
$router->get('/pesajes/crear',                              [PesajeController::class, 'create']);
$router->post('/pesajes',                                   [PesajeController::class, 'store']);
$router->post('/pesajes/{id}/eliminar',                     [PesajeController::class, 'delete']);

// Perfil
$router->get('/perfil',                                     [PerfilController::class, 'show']);
$router->post('/perfil/info',                               [PerfilController::class, 'updateInfo']);
$router->post('/perfil/password',                           [PerfilController::class, 'updatePassword']);

$router->get('/configuracion',                              [ConfigController::class, 'index']);
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

$router->dispatch();