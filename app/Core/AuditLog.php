<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use Throwable;

/**
 * Audit log de negocio: registra creación, modificación y borrado de
 * entidades clave (lotes, silos, recargas, pesajes, movimientos…).
 *
 * A diferencia de SecurityLog (que va a fichero y cubre eventos de auth),
 * este log va a la tabla `audit_log` para poder consultarse desde la app.
 *
 * Convenciones:
 *   - $entidad: nombre lógico ('lote', 'silo', 'recarga', ...).
 *   - $accion:  'create' | 'update' | 'delete' | 'ajustar' | 'cerrar' | ...
 *   - $antes / $despues: arrays asociativos con el estado de la fila.
 *     Se serializan como JSON. Claves sensibles (password, token, hash…)
 *     se ocultan antes de guardar.
 *
 * Uso típico:
 *   $antes = $model->find($id, $uid);
 *   $model->update($id, $uid, $data);
 *   $despues = $model->find($id, $uid);
 *   AuditLog::log('lote', $id, 'update', $antes, $despues);
 *
 * Este método NUNCA debe romper el flujo del usuario: cualquier error
 * se captura y se registra en error_log.
 */
class AuditLog
{
    /** Claves que nunca se guardan en claro en el audit log. */
    private const SENSITIVE_KEYS = [
        'password',
        'password_hash',
        'password_enc',
        'recevet_password',
        'recevet_password_enc',
        'recevet_session_cookie',
        'token',
        'reset_token',
        'csrf_token',
    ];

    public static function log(
        string $entidad,
        ?int $entidadId,
        string $accion,
        ?array $antes = null,
        ?array $despues = null
    ): void {
        try {
            $userId = Session::get('usuario_id');
            $ip     = function_exists('client_ip')
                ? client_ip()
                : ($_SERVER['REMOTE_ADDR'] ?? null);
            $ua     = isset($_SERVER['HTTP_USER_AGENT'])
                ? substr((string)$_SERVER['HTTP_USER_AGENT'], 0, 255)
                : null;

            $antesJson   = self::encode($antes);
            $despuesJson = self::encode($despues);

            $pdo = Database::getInstance();
            $stmt = $pdo->prepare(
                'INSERT INTO audit_log
                    (user_id, accion, entidad, entidad_id,
                     datos_antes, datos_despues, ip, user_agent)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $userId !== null ? (int)$userId : null,
                $accion,
                $entidad,
                $entidadId,
                $antesJson,
                $despuesJson,
                $ip,
                $ua,
            ]);
        } catch (Throwable $e) {
            // Nunca rompemos el flujo del usuario por un fallo del log.
            error_log('[AuditLog] ' . $e->getMessage());
        }
    }

    /**
     * Sanea y serializa un array a JSON. Devuelve null si no hay datos.
     */
    private static function encode(?array $data): ?string
    {
        if ($data === null) return null;

        $clean = self::sanitize($data);
        $json  = json_encode(
            $clean,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR
        );

        return $json !== false ? $json : null;
    }

    /**
     * Reemplaza valores de claves sensibles por '[REDACTED]'.
     * Recorre arrays anidados por si acaso.
     */
    private static function sanitize(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SENSITIVE_KEYS, true)) {
                if ($value !== null && $value !== '') {
                    $data[$key] = '[REDACTED]';
                }
                continue;
            }
            if (is_array($value)) {
                $data[$key] = self::sanitize($value);
            }
        }
        return $data;
    }
}
