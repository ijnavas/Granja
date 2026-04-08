<?php
declare(strict_types=1);

namespace App\Core;

class Router
{
    private array $routes = [];

    public function get(string $path, array $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    public function post(string $path, array $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // Eliminar el subdirectorio base si existe
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        if ($base && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }

        $uri = '/' . trim($uri, '/');
        if ($uri === '') $uri = '/';

        $routes = $this->routes[$method] ?? [];

        // Ruta exacta
        if (isset($routes[$uri])) {
            $this->call($routes[$uri]);
            return;
        }

        // Rutas con parámetros dinámicos: /lotes/{id}
        foreach ($routes as $pattern => $handler) {
            $regex = preg_replace('/\{([a-z_]+)\}/', '(?P<$1>[^/]+)', $pattern);
            $regex = '#^' . $regex . '$#';
            if (preg_match($regex, $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $this->call($handler, $params);
                return;
            }
        }

        // 404
        http_response_code(404);
        echo '404 - Página no encontrada';
    }

    private function call(array $handler, array $params = []): void
    {
        // Validación CSRF centralizada para todas las peticiones POST.
        // Cada form de la app ya incluye csrf_field(); si algún request
        // llega sin token válido, lo rechazamos aquí antes de tocar el
        // controlador.
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = (string)($_POST['csrf_token'] ?? '');
            if (!Session::validateCsrf($token)) {
                error_log('CSRF fallido en ' . ($_SERVER['REQUEST_URI'] ?? '?')
                    . ' desde ' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
                Session::flash('error', 'Token de seguridad inválido. Recarga la página e inténtalo de nuevo.');

                $cfg  = require ROOT_PATH . '/config.php';
                $base = rtrim($cfg['app']['base_url'], '/');
                $ref  = $_SERVER['HTTP_REFERER'] ?? null;
                // Solo aceptamos referers del propio dominio
                if ($ref && str_starts_with($ref, $base)) {
                    header('Location: ' . $ref);
                } else {
                    header('Location: ' . $base . '/login');
                }
                exit;
            }
        }

        [$class, $method] = $handler;
        $controller = new $class();
        $controller->$method(...array_values($params));
    }
}
