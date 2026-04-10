<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Envío de notificaciones de seguridad por email (HTML con template BALTAE).
 *
 * Todas las llamadas capturan excepciones y fallan silenciosamente (solo
 * error_log) para que un problema de SMTP nunca rompa el flujo del usuario.
 */
class SecurityNotifier
{
    /**
     * Aviso al usuario cuando alguien solicita un reset de contraseña.
     */
    public static function passwordResetRequested(string $to, string $resetUrl, string $ip): void
    {
        $subject = 'Solicitud de restablecimiento de contraseña — BALTAE';

        $body = EmailTemplate::title(
            'Restablecimiento de contraseña',
            date('d/m/Y H:i') . 'h',
            '#1e3a5f'
        );

        $body .= EmailTemplate::paragraph(
            'Hemos recibido una solicitud para restablecer la contraseña de tu cuenta BALTAE.'
        );

        $body .= EmailTemplate::alertBox(
            '<p style="margin:0 0 12px;font-size:14px">'
            . 'Este enlace es valido durante <strong>1 hora</strong>. Si no has solicitado este cambio, puedes ignorar este mensaje.'
            . '</p>'
            . self::metaHtml($ip),
            'warning'
        );

        $body .= EmailTemplate::button('Restablecer contraseña', $resetUrl);

        $body .= '<p style="margin:16px 0 0;font-size:12px;color:#9ca3af;text-align:center">'
            . 'Si el boton no funciona, copia y pega esta URL en tu navegador:<br>'
            . '<span style="font-size:11px;color:#6b7280;word-break:break-all">' . e($resetUrl) . '</span>'
            . '</p>';

        $html = EmailTemplate::build($body, 'orange');
        self::trySend($to, $subject, $html, 'password_reset_requested');
    }

    /**
     * Aviso al usuario cuando su contraseña acaba de ser modificada.
     */
    public static function passwordChanged(string $to, string $ip, string $context): void
    {
        $subject = 'Tu contraseña ha sido modificada — BALTAE';

        $body = EmailTemplate::title(
            'Contraseña modificada',
            date('d/m/Y H:i') . 'h',
            '#1e3a5f'
        );

        $body .= EmailTemplate::paragraph(
            'La contraseña de tu cuenta BALTAE ha sido modificada ' . e($context) . '.'
        );

        $body .= EmailTemplate::alertBox(
            self::metaHtml($ip)
            . '<p style="margin:12px 0 0;font-size:13px">'
            . 'Si has sido tu, no hace falta que hagas nada.'
            . '</p>',
            'warning'
        );

        $body .= EmailTemplate::paragraph(
            '<strong>Si NO has sido tu:</strong><br>'
            . '1. Intenta iniciar sesion inmediatamente y cambia la contraseña.<br>'
            . '2. Si no puedes acceder, usa "¿Olvidaste tu contraseña?" para recuperarla.<br>'
            . '3. Contacta con el administrador del sistema lo antes posible.',
            '#374151'
        );

        $html = EmailTemplate::build($body, 'orange');
        self::trySend($to, $subject, $html, 'password_changed');
    }

    /**
     * Aviso al usuario cuando su cuenta se bloquea por exceso de intentos.
     */
    public static function accountLocked(string $to, int $minutes, string $ip): void
    {
        $subject = 'Tu cuenta ha sido bloqueada temporalmente — BALTAE';

        $body = EmailTemplate::title(
            'Cuenta bloqueada temporalmente',
            date('d/m/Y H:i') . 'h',
            '#991b1b'
        );

        $body .= EmailTemplate::alertBox(
            '<p style="margin:0 0 12px;font-size:14px;font-weight:600">'
            . 'Tu cuenta ha sido bloqueada durante ' . $minutes . ' minutos por exceso de intentos fallidos de login.'
            . '</p>'
            . self::metaHtml($ip),
            'danger'
        );

        $body .= EmailTemplate::paragraph(
            '<strong>Si has sido tu</strong> (olvidaste la contraseña):<br>'
            . 'Espera a que expire el bloqueo o usa "¿Olvidaste tu contraseña?" para restablecerla.'
        );

        $body .= EmailTemplate::paragraph(
            '<strong>Si NO has sido tu:</strong><br>'
            . 'Alguien puede estar intentando adivinar tu contraseña. '
            . 'Te recomendamos restablecerla cuanto antes desde la pagina de login.'
        );

        $body .= EmailTemplate::button('Ir a la pagina de login', base_url('login'));

        $html = EmailTemplate::build($body, 'red');
        self::trySend($to, $subject, $html, 'account_locked');
    }

    // ── Helpers privados ─────────────────────────────────────────

    /**
     * Bloque HTML con metadatos del evento (IP, fecha, navegador).
     */
    private static function metaHtml(string $ip): string
    {
        $cuando = date('d/m/Y H:i:s');
        $ua     = $_SERVER['HTTP_USER_AGENT'] ?? 'desconocido';
        if (strlen($ua) > 100) $ua = substr($ua, 0, 97) . '...';

        return '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:13px">'
            . '<tr><td style="padding:4px 0;font-weight:600;width:110px">Fecha</td><td style="padding:4px 0">' . e($cuando) . '</td></tr>'
            . '<tr><td style="padding:4px 0;font-weight:600">IP</td><td style="padding:4px 0;font-family:monospace">' . e($ip) . '</td></tr>'
            . '<tr><td style="padding:4px 0;font-weight:600">Navegador</td><td style="padding:4px 0">' . e($ua) . '</td></tr>'
            . '</table>';
    }

    /**
     * Envía el email HTML capturando cualquier error.
     */
    private static function trySend(string $to, string $subject, string $html, string $tag): void
    {
        try {
            $ok = (new Mailer())->send($to, $subject, $html, true);
            if (!$ok) {
                error_log("[SecurityNotifier] fallo al enviar '{$tag}' a {$to}");
            }
        } catch (\Throwable $e) {
            error_log("[SecurityNotifier] excepcion enviando '{$tag}' a {$to}: " . $e->getMessage());
        }
    }
}
