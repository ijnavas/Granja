<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Envío de notificaciones de seguridad por email.
 *
 * Todas las llamadas capturan excepciones y fallan silenciosamente (solo
 * error_log) para que un problema de SMTP nunca rompa el flujo del usuario.
 * La lógica crítica (guardar nueva contraseña, bloquear cuenta, etc.) ya
 * habrá sucedido antes de llegar aquí.
 *
 * Convenciones:
 *   - Cuerpo en texto plano (el Mailer actual solo envía text/plain).
 *   - Todos los emails llevan IP, timestamp y un disclaimer "si no fuiste tú".
 *   - Nunca se incluye información sensible (hash, token completo, etc.).
 */
class SecurityNotifier
{
    /**
     * Aviso al usuario cuando alguien solicita un reset de contraseña
     * para su cuenta. Incluye el enlace de reset.
     */
    public static function passwordResetRequested(string $to, string $resetUrl, string $ip): void
    {
        $subject = 'Solicitud de restablecimiento de contraseña — BALTAE';
        $body = self::header()
            . "Hemos recibido una solicitud para restablecer la contraseña de tu cuenta BALTAE.\n\n"
            . "Para continuar, haz clic en el siguiente enlace (válido durante 1 hora):\n"
            . $resetUrl . "\n\n"
            . self::metaBlock($ip)
            . "\n"
            . "Si NO has solicitado este cambio, puedes ignorar este mensaje: tu contraseña\n"
            . "actual seguirá funcionando y nadie podrá acceder sin abrir el enlace.\n"
            . "Si ves solicitudes repetidas que no has hecho, considera cambiar tu contraseña\n"
            . "desde la aplicación.\n"
            . self::footer();

        self::trySend($to, $subject, $body, 'password_reset_requested');
    }

    /**
     * Aviso al usuario cuando su contraseña acaba de ser modificada
     * (ya sea desde el formulario de perfil o vía un reset completado).
     *
     * $context describe brevemente cómo se produjo el cambio, ej:
     *   "desde tu perfil"
     *   "mediante un enlace de restablecimiento"
     */
    public static function passwordChanged(string $to, string $ip, string $context): void
    {
        $subject = 'Tu contraseña ha sido modificada — BALTAE';
        $body = self::header()
            . "Te informamos de que la contraseña de tu cuenta BALTAE acaba de ser\n"
            . "modificada {$context}.\n\n"
            . self::metaBlock($ip)
            . "\n"
            . "Si has sido tú, no hace falta que hagas nada.\n\n"
            . "Si NO has sido tú, alguien podría haber accedido a tu cuenta:\n"
            . "  1. Intenta iniciar sesión inmediatamente y cambia la contraseña.\n"
            . "  2. Si no puedes acceder, usa la opción \"¿Olvidaste tu contraseña?\"\n"
            . "     para recuperarla.\n"
            . "  3. Contacta con el administrador del sistema lo antes posible.\n"
            . self::footer();

        self::trySend($to, $subject, $body, 'password_changed');
    }

    /**
     * Aviso al usuario cuando su cuenta acaba de bloquearse por exceso de
     * intentos fallidos de login. $minutes es la duración del bloqueo.
     */
    public static function accountLocked(string $to, int $minutes, string $ip): void
    {
        $subject = 'Tu cuenta ha sido bloqueada temporalmente — BALTAE';
        $body = self::header()
            . "Tu cuenta BALTAE ha sido bloqueada temporalmente durante {$minutes} minutos\n"
            . "por exceso de intentos de inicio de sesión fallidos.\n\n"
            . self::metaBlock($ip)
            . "\n"
            . "Qué significa esto:\n"
            . "  - Durante los próximos {$minutes} minutos no se aceptará ningún intento\n"
            . "    de login para tu cuenta, aunque la contraseña sea correcta.\n"
            . "  - Pasado ese tiempo, el bloqueo se levanta automáticamente.\n\n"
            . "Si has sido tú (p. ej. olvidaste la contraseña):\n"
            . "  - Espera a que expire el bloqueo o usa \"¿Olvidaste tu contraseña?\"\n"
            . "    para restablecerla.\n\n"
            . "Si NO has sido tú:\n"
            . "  - Alguien puede estar intentando adivinar tu contraseña.\n"
            . "  - Te recomendamos restablecerla cuanto antes desde la página de login.\n"
            . self::footer();

        self::trySend($to, $subject, $body, 'account_locked');
    }

    // ── Helpers privados ─────────────────────────────────────────

    private static function header(): string
    {
        return "Hola,\n\n";
    }

    private static function footer(): string
    {
        return "\n— \n"
             . "BALTAE · Sistema de gestión de granjas\n"
             . "Este es un mensaje automático, no respondas a este email.\n";
    }

    private static function metaBlock(string $ip): string
    {
        $cuando = date('d/m/Y H:i:s');
        $ua     = $_SERVER['HTTP_USER_AGENT'] ?? 'desconocido';
        // Truncar UA para que no ocupe 3 líneas si es larguísimo.
        if (strlen($ua) > 120) $ua = substr($ua, 0, 117) . '...';
        return "Detalles:\n"
             . "  Fecha:    {$cuando}\n"
             . "  Dirección IP: {$ip}\n"
             . "  Navegador:    {$ua}\n";
    }

    /**
     * Envía el email capturando cualquier error y dejando traza en error_log.
     * Nunca propaga excepciones hacia arriba.
     */
    private static function trySend(string $to, string $subject, string $body, string $tag): void
    {
        try {
            $ok = (new Mailer())->send($to, $subject, $body);
            if (!$ok) {
                error_log("[SecurityNotifier] fallo al enviar '{$tag}' a {$to}");
            }
        } catch (\Throwable $e) {
            error_log("[SecurityNotifier] excepción enviando '{$tag}' a {$to}: " . $e->getMessage());
        }
    }
}
