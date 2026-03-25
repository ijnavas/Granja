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

Session::start();

// ── Rutas ────────────────────────────────────────────────────
$router = new Router();

// Auth
$router->get('/',            [AuthController::class,     'loginForm']);
$router->get('/login',       [AuthController::class,     'loginForm']);
$router->post('/login',      [AuthController::class,     'login']);
$router->get('/register',    [AuthController::class,     'registerForm']);
$router->post('/register',   [AuthController::class,     'register']);
$router->get('/logout',      [AuthController::class,     'logout']);

// Dashboard
$router->get('/dashboard',   [DashboardController::class, 'index']);

// Granjas
$router->get('/granjas',                    [GranjaController::class, 'index']);
$router->get('/granjas/crear',              [GranjaController::class, 'create']);
$router->post('/granjas',                   [GranjaController::class, 'store']);
$router->get('/granjas/{id}/editar',        [GranjaController::class, 'edit']);
$router->post('/granjas/{id}/actualizar',   [GranjaController::class, 'update']);
$router->post('/granjas/{id}/eliminar',     [GranjaController::class, 'delete']);

// Naves
$router->get('/naves',                      [NaveController::class, 'index']);
$router->get('/naves/crear',                [NaveController::class, 'create']);
$router->post('/naves',                     [NaveController::class, 'store']);
$router->get('/naves/{id}/editar',          [NaveController::class, 'edit']);
$router->post('/naves/{id}/actualizar',     [NaveController::class, 'update']);
$router->post('/naves/{id}/eliminar',       [NaveController::class, 'delete']);

// Silos
$router->get('/silos',                      [SiloController::class, 'index']);
$router->get('/silos/crear',                [SiloController::class, 'create']);
$router->post('/silos',                     [SiloController::class, 'store']);
$router->get('/silos/{id}/editar',          [SiloController::class, 'edit']);
$router->post('/silos/{id}/actualizar',     [SiloController::class, 'update']);
$router->post('/silos/{id}/eliminar',       [SiloController::class, 'delete']);

// Lotes
$router->get('/lotes',                      [LoteController::class, 'index']);
$router->get('/lotes/crear',                [LoteController::class, 'create']);
$router->post('/lotes',                     [LoteController::class, 'store']);
$router->get('/lotes/{id}/editar',          [LoteController::class, 'edit']);
$router->post('/lotes/{id}/actualizar',     [LoteController::class, 'update']);
$router->post('/lotes/{id}/ajustar',        [LoteController::class, 'ajustar']);
$router->post('/lotes/{id}/eliminar',       [LoteController::class, 'delete']);

$router->dispatch();
