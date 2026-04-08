<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Rate limiter persistente respaldado por la tabla `rate_limits`.
 *
 * Uso típico:
 *
 *   if (!RateLimiter::attempt('login', $ip, 5, 900)) {
 *       // bloqueado: 5 intentos en 15 min
 *   }
 *
 * `attempt()` incrementa el contador y devuelve `true` si aún quedan
 * intentos disponibles, `false` si se ha excedido el límite.
 *
 * `clear()` resetea el contador (útil tras un login exitoso).
 */
class RateLimiter
{
    private static bool $schemaEnsured = false;

    /**
     * Garantiza que la tabla rate_limits existe. Idempotente.
     */
    private static function ensureSchema(PDO $db): void
    {
        if (self::$schemaEnsured) return;
        $db->exec("CREATE TABLE IF NOT EXISTS rate_limits (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            bucket     VARCHAR(64)  NOT NULL,
            `key`      VARCHAR(190) NOT NULL,
            attempts   INT          NOT NULL DEFAULT 0,
            expires_at DATETIME     NOT NULL,
            UNIQUE KEY uniq_bucket_key (bucket, `key`),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        self::$schemaEnsured = true;
    }

    /**
     * Intenta consumir un slot del bucket. Devuelve true si se permite,
     * false si se ha alcanzado el límite.
     *
     * @param string $bucket       Nombre lógico (login, register, forgot…)
     * @param string $key          Identificador (IP, email, IP:email…)
     * @param int    $maxAttempts  Intentos permitidos por ventana
     * @param int    $decaySeconds Duración de la ventana en segundos
     */
    public static function attempt(string $bucket, string $key, int $maxAttempts, int $decaySeconds): bool
    {
        $db = Database::getInstance();
        try {
            self::ensureSchema($db);
        } catch (\Throwable $e) {
            // Si no podemos asegurar la tabla, NO bloqueamos: dejar pasar
            // y loguear. Mejor permitir el intento que romper /login.
            error_log('RateLimiter ensureSchema fallo: ' . $e->getMessage());
            return true;
        }

        $hashedKey = hash('sha256', $key);
        $now       = date('Y-m-d H:i:s');
        $expires   = date('Y-m-d H:i:s', time() + $decaySeconds);

        // Limpia entradas expiradas oportunistamente (cada ~100 llamadas)
        if (random_int(1, 100) === 1) {
            $db->prepare('DELETE FROM rate_limits WHERE expires_at < :now')
               ->execute(['now' => $now]);
        }

        // Lee el registro actual
        $sel = $db->prepare(
            'SELECT attempts, expires_at FROM rate_limits
             WHERE bucket = :bucket AND `key` = :key LIMIT 1'
        );
        $sel->execute(['bucket' => $bucket, 'key' => $hashedKey]);
        $row = $sel->fetch();

        if (!$row || $row['expires_at'] <= $now) {
            // Nueva ventana: insertar o sustituir.
            // Nota: PDO con prepares reales no permite reutilizar el mismo
            // placeholder, por eso usamos :expires_ins y :expires_upd.
            $up = $db->prepare(
                'INSERT INTO rate_limits (bucket, `key`, attempts, expires_at)
                 VALUES (:bucket, :key, 1, :expires_ins)
                 ON DUPLICATE KEY UPDATE attempts = 1, expires_at = :expires_upd'
            );
            $up->execute([
                'bucket'      => $bucket,
                'key'         => $hashedKey,
                'expires_ins' => $expires,
                'expires_upd' => $expires,
            ]);
            return true;
        }

        if ((int)$row['attempts'] >= $maxAttempts) {
            return false;
        }

        $db->prepare(
            'UPDATE rate_limits SET attempts = attempts + 1
             WHERE bucket = :bucket AND `key` = :key'
        )->execute(['bucket' => $bucket, 'key' => $hashedKey]);

        return true;
    }

    /**
     * Devuelve cuántos segundos faltan hasta que se libere el bucket.
     */
    public static function retryAfter(string $bucket, string $key): int
    {
        $db        = Database::getInstance();
        $hashedKey = hash('sha256', $key);
        $stmt      = $db->prepare(
            'SELECT expires_at FROM rate_limits
             WHERE bucket = :bucket AND `key` = :key LIMIT 1'
        );
        $stmt->execute(['bucket' => $bucket, 'key' => $hashedKey]);
        $exp = $stmt->fetchColumn();
        if (!$exp) return 0;
        return max(0, strtotime($exp) - time());
    }

    /**
     * Resetea el contador para esa clave (tras un éxito).
     */
    public static function clear(string $bucket, string $key): void
    {
        $db        = Database::getInstance();
        $hashedKey = hash('sha256', $key);
        $db->prepare('DELETE FROM rate_limits WHERE bucket = :bucket AND `key` = :key')
           ->execute(['bucket' => $bucket, 'key' => $hashedKey]);
    }
}
