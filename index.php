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

Session::start();

// ── Rutas ────────────────────────────────────────────────────
$router = new Router();

$router->get('/',          [AuthController::class, 'loginForm']);
$router->get('/login',     [AuthController::class, 'loginForm']);
$router->post('/login',    [AuthController::class, 'login']);
$router->get('/register',  [AuthController::class, 'registerForm']);
$router->post('/register', [AuthController::class, 'register']);
$router->get('/logout',    [AuthController::class, 'logout']);

// TODO: añadir rutas del panel cuando se desarrolle
// $router->get('/dashboard', [DashboardController::class, 'index']);

$router->dispatch();
