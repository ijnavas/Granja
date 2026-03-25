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
        [$class, $method] = $handler;
        $controller = new $class();
        $controller->$method(...array_values($params));
    }
}
