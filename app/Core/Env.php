<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Loader minimalista de variables de entorno desde un archivo .env
 *
 * Formato soportado:
 *   CLAVE=valor
 *   CLAVE="valor con espacios"
 *   # comentarios
 *
 * Las variables cargadas se exponen vía getenv() y $_ENV.
 * No sobreescribe variables ya definidas en el entorno real.
 */
class Env
{
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded || !is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;

            $eq = strpos($line, '=');
            if ($eq === false) continue;

            $key = trim(substr($line, 0, $eq));
            $val = trim(substr($line, $eq + 1));

            // Quitar comillas envolventes
            if (strlen($val) >= 2) {
                $first = $val[0];
                $last  = $val[strlen($val) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $val = substr($val, 1, -1);
                }
            }

            // No pisar variables reales del entorno
            if (getenv($key) !== false) continue;

            putenv("{$key}={$val}");
            $_ENV[$key]    = $val;
            $_SERVER[$key] = $val;
        }

        self::$loaded = true;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $v = getenv($key);
        if ($v === false || $v === '') return $default;
        return $v;
    }

    public static function getInt(string $key, int $default = 0): int
    {
        $v = getenv($key);
        if ($v === false || $v === '') return $default;
        return (int) $v;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $v = getenv($key);
        if ($v === false || $v === '') return $default;
        return in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
    }

    public static function require(string $key): string
    {
        $v = getenv($key);
        if ($v === false || $v === '') {
            throw new \RuntimeException("Variable de entorno requerida no definida: {$key}");
        }
        return $v;
    }
}
