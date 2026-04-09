<?php
declare(strict_types=1);

namespace App\Controllers;

/**
 * Endpoints relacionados con cabeceras de seguridad.
 *
 * Por ahora solo recibe reportes de violación CSP enviados por el
 * navegador vía `report-uri`. El navegador manda un POST con JSON
 * describiendo qué directiva se violó y dónde. Los guardamos en
 * `logs/csp-violations.log` para poder ajustar la política.
 */
class SecurityController extends BaseController
{
    public function cspReport(): void
    {
        // El navegador manda el body como application/csp-report o
        // application/json. No hay CSRF (es el propio navegador).
        $raw = file_get_contents('php://input') ?: '';
        if ($raw === '') {
            http_response_code(204);
            return;
        }

        // Truncar por si el payload es absurdamente grande (DoS).
        if (strlen($raw) > 8192) {
            $raw = substr($raw, 0, 8192) . '...[truncated]';
        }

        $entry = [
            'ts'     => date('c'),
            'ip'     => $_SERVER['REMOTE_ADDR']      ?? null,
            'ua'     => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200),
            'report' => json_decode($raw, true) ?: $raw,
        ];

        $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($line !== false) {
            $dir  = ROOT_PATH . '/logs';
            $file = $dir . '/csp-violations.log';
            if (!is_dir($dir)) {
                @mkdir($dir, 0750, true);
            }
            @file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX);
        }

        http_response_code(204);
    }
}
