<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Logging dedicado para eventos de seguridad.
 *
 * Escribe en `logs/security.log` (creando la carpeta si no existe).
 * Cada línea es JSON con timestamp, evento, ip, user-agent y contexto extra.
 *
 * Uso:
 *   SecurityLog::log('login_success', ['user_id' => 42]);
 *   SecurityLog::log('login_failed',  ['email' => $email]);
 */
class SecurityLog
{
    public static function log(string $event, array $context = []): void
    {
        $entry = [
            'ts'    => date('c'),
            'event' => $event,
            'ip'    => $_SERVER['REMOTE_ADDR'] ?? null,
            'ua'    => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200),
            'uri'   => $_SERVER['REQUEST_URI'] ?? null,
        ];
        if ($context) {
            $entry['ctx'] = $context;
        }

        $line = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($line === false) return;

        $dir  = ROOT_PATH . '/logs';
        $file = $dir . '/security.log';

        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }

        @file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX);
    }
}
