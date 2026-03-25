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
}
