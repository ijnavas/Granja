<?php
declare(strict_types=1);

namespace App\Controllers;

abstract class BaseController
{
    protected function view(string $view, array $data = [], string $layout = 'main'): void
    {
        view($view, $data, $layout);
    }

    protected function redirect(string $path): never
    {
        redirect($path);
    }

    protected function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    protected function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    protected function postString(string $key): string
    {
        return trim((string)($this->post($key, '')));
    }

    /**
     * Extrae solo las claves permitidas del array indicado (por defecto $_POST).
     *
     * Sirve como allowlist explícita para evitar mass assignment: en lugar de
     * pasar $_POST entero a un modelo, se pasa solo lo que esperas.
     *
     *   $data = $this->only(['nombre', 'email']);
     *   $this->model->create($data);
     *
     * Las claves ausentes en la fuente NO aparecen en el resultado (no se
     * rellenan con null). Si necesitas defaults, úsalos en el modelo o en
     * un array_merge previo.
     */
    protected function only(array $keys, ?array $source = null): array
    {
        $source = $source ?? $_POST;
        $out = [];
        foreach ($keys as $k) {
            if (array_key_exists($k, $source)) {
                $out[$k] = $source[$k];
            }
        }
        return $out;
    }
}
