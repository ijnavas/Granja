<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Usuario;
use App\Core\Session;
use App\Core\RateLimiter;
use App\Core\SecurityLog;
use App\Core\SecurityNotifier;
use App\Core\Mailer;
use App\Core\EmailTemplate;

class AuthController extends BaseController
{
    private Usuario $usuario;

    public function __construct()
    {
        $this->usuario = new Usuario();
    }

    // ── GET /login ──────────────────────────────────────────────
    public function loginForm(): void
    {
        guest_only();
        $this->view('auth/login', [
            'error'   => Session::getFlash('error'),
            'success' => Session::getFlash('success'),
        ], 'auth');
    }

    // ── POST /login ─────────────────────────────────────────────
    public function login(): void
    {
        guest_only();
        // CSRF validado en el Router.

        $email    = strtolower($this->postString('email'));
        $password = $this->postString('password');
        $ip       = client_ip();

        // ─── Rate limit ──────────────────────────────────────────
        // 5 intentos por (IP+email) cada 15 min (anti-bruteforce de cuenta)
        // 20 intentos por IP cada 15 min (anti-spray)
        $perAccount = "{$ip}|{$email}";
        if (!RateLimiter::attempt('login_account', $perAccount, 5, 900)
            || !RateLimiter::attempt('login_ip', $ip, 20, 900)) {
            SecurityLog::log('login_rate_limited', ['email' => $email]);
            Session::flash('error', 'Demasiados intentos. Espera unos minutos e inténtalo de nuevo.');
            $this->redirect('login');
        }

        // Validaciones básicas
        if (empty($email) || empty($password)) {
            Session::flash('error', 'Por favor, introduce email y contraseña.');
            $this->redirect('login');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'El email no tiene un formato válido.');
            $this->redirect('login');
        }

        // ─── Account lockout (persistente, sobrevive al rotar IP) ───
        // Si la cuenta está bloqueada, se rechaza ANTES de verificar el
        // password para no tocar el hash (timing) ni regalar pistas.
        $lockRemaining = $this->usuario->lockoutSecondsRemaining($email);
        if ($lockRemaining > 0) {
            SecurityLog::log('login_locked', ['email' => $email, 'seconds_remaining' => $lockRemaining]);
            // Mensaje genérico (no confirma existencia de cuenta).
            Session::flash('error', 'Email o contraseña incorrectos.');
            $this->redirect('login');
        }

        // Autenticar
        $user = $this->usuario->authenticate($email, $password);

        if (!$user) {
            // Incrementa el contador persistente si la cuenta existe.
            $justLocked = $this->usuario->registerFailedLogin($email);
            SecurityLog::log('login_failed', [
                'email'       => $email,
                'just_locked' => $justLocked,
            ]);
            if ($justLocked) {
                SecurityLog::log('account_locked', [
                    'email'   => $email,
                    'minutes' => \App\Models\Usuario::LOCKOUT_MINUTES,
                ]);
                // Notificar al dueño de la cuenta (si existe). Comprobamos
                // emailExists para no enviar correo a direcciones inventadas
                // por un atacante haciendo spray con emails aleatorios.
                if ($this->usuario->emailExists($email)) {
                    SecurityNotifier::accountLocked(
                        $email,
                        \App\Models\Usuario::LOCKOUT_MINUTES,
                        $ip
                    );
                }
            }
            // Mensaje genérico para no revelar si el email existe
            Session::flash('error', 'Email o contraseña incorrectos.');
            $this->redirect('login');
        }

        // Éxito: limpiar contadores y rotar sesión
        $this->usuario->clearFailedLogins((int)$user['id']);
        RateLimiter::clear('login_account', $perAccount);
        RateLimiter::clear('login_ip', $ip);

        session_regenerate_id(true);
        Session::rotateCsrf();

        Session::set('usuario_id',     $user['id']);
        Session::set('usuario_nombre', $user['nombre']);
        Session::set('usuario_email',  $user['email']);
        Session::set('usuario_rol',    $user['rol'] ?? 'usuario');

        // Selecciona organizacion activa. Para usuarios legacy con granjas
        // huérfanas, ensureOrgForUsuario migra a una org "personal". Para
        // usuarios nuevos sin invitación todavía aceptada, devuelve null.
        $orgModel = new \App\Models\Organizacion();
        $orgId    = $orgModel->ensureOrgForUsuario((int)$user['id'], (string)$user['nombre']);
        if ($orgId) {
            $orgRol = $orgModel->rolEnOrg((int)$user['id'], $orgId) ?? 'operario';
            Session::set('current_org_id',  $orgId);
            Session::set('current_org_rol', $orgRol);
        } else {
            Session::set('current_org_id',  null);
            Session::set('current_org_rol', null);
        }

        SecurityLog::log('login_success', ['user_id' => (int)$user['id']]);

        // Si venia de una invitacion pendiente, retomamos el flujo
        $pending = Session::get('pending_invitation_token');
        if ($pending) {
            Session::set('pending_invitation_token', null);
            $this->redirect('aceptar-invitacion/' . $pending);
        }

        // Sin organización → pantalla "esperando invitación"
        if (!$orgId) $this->redirect('sin-organizacion');

        $this->redirect('dashboard');
    }

    // ── GET /sin-organizacion ────────────────────────────────────
    /** Pantalla "limbo": usuario logueado sin organizacion asignada. */
    public function sinOrganizacion(): void
    {
        if (!Session::has('usuario_id')) {
            $this->redirect('login');
        }
        // Si por algún motivo ya tiene org, mandarlo al dashboard.
        if (\App\Core\OrgContext::id() > 0) {
            $this->redirect('dashboard');
        }
        $this->view('auth/sin-organizacion', [
            'u'         => auth_user(),
            'pageTitle' => 'Sin organización',
        ], 'auth');
    }

    // ── GET /register ────────────────────────────────────────────
    public function registerForm(): void
    {
        guest_only();
        $oldRaw = Session::getFlash('old');
        $this->view('auth/register', [
            'error'   => Session::getFlash('error'),
            'success' => Session::getFlash('success'),
            'old'     => $oldRaw ? (json_decode($oldRaw, true) ?: []) : [],
        ], 'auth');
    }

    // ── POST /register ───────────────────────────────────────────
    public function register(): void
    {
        guest_only();
        // CSRF validado en el Router.

        $ip = client_ip();
        // Rate limit: 5 registros por IP cada hora
        if (!RateLimiter::attempt('register', $ip, 5, 3600)) {
            SecurityLog::log('register_rate_limited');
            Session::flash('error', 'Demasiados intentos. Inténtalo más tarde.');
            $this->redirect('register');
        }

        $nombre    = $this->postString('nombre');
        $email     = strtolower($this->postString('email'));
        $password  = $this->postString('password');
        $password2 = $this->postString('password_confirm');

        // Guardar datos del formulario para repoblar en caso de error
        Session::flash('old', json_encode(['nombre' => $nombre, 'email' => $email]));

        $errors = $this->validateRegister($nombre, $email, $password, $password2);

        if ($errors) {
            Session::flash('error', implode('<br>', $errors));
            $this->redirect('register');
        }

        if ($this->usuario->emailExists($email)) {
            Session::flash('error', 'Ya existe una cuenta con ese email.');
            $this->redirect('register');
        }

        $newId = $this->usuario->create($nombre, $email, $password);
        SecurityLog::log('register_success', ['user_id' => $newId, 'email' => $email]);

        // Email de bienvenida (no bloquear el registro si falla)
        $this->enviarEmailBienvenida($email, $nombre);

        Session::flash('success', '¡Cuenta creada! Ya puedes iniciar sesión.');
        $this->redirect('login');
    }

    /** Envía el email de bienvenida tras un registro correcto. */
    private function enviarEmailBienvenida(string $email, string $nombre): bool
    {
        $cfg = require ROOT_PATH . '/config.php';
        if (empty($cfg['mail']['enabled']) || empty($cfg['mail']['smtp']['host'])) {
            return false;
        }

        $T        = EmailTemplate::class;
        $loginUrl = base_url('login');
        $primer   = trim(explode(' ', trim($nombre))[0] ?? $nombre);

        $body  = $T::title('¡Bienvenido/a a BALTAE, ' . e($primer) . '!');
        $body .= $T::paragraph(
            'Tu cuenta ya está creada. BALTAE es la plataforma para gestionar la operativa diaria '
            . 'de tus granjas: lotes, pesajes, movimientos, inventarios, almacén y reportes — todo '
            . 'desde un único panel.'
        );
        $body .= $T::sectionTitle('Primeros pasos');
        $body .= '<ul style="margin:0 0 20px;padding-left:20px;color:#374151;font-size:14px;line-height:1.8">'
            . '<li>Inicia sesión con el email <strong>' . e($email) . '</strong>.</li>'
            . '<li>Crea tu primera <strong>granja</strong> en el menú lateral.</li>'
            . '<li>Añade naves, cuadras y registra tu primer lote.</li>'
            . '<li>Si trabajas con un equipo, invita compañeros desde <strong>Equipo</strong>.</li>'
            . '</ul>';
        $body .= $T::button('Iniciar sesión', $loginUrl);
        $body .= $T::paragraph(
            '<span style="font-size:12px;color:#9ca3af">¿Necesitas ayuda? Responde a este mensaje y te '
            . 'atenderemos personalmente.</span>'
        );

        // Aviso legal y protección de datos (RGPD / LOPDGDD)
        $body .= '<div style="margin-top:28px;padding:16px 20px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;font-size:11px;line-height:1.6;color:#6b7280">'
            . '<strong style="color:#374151;font-size:12px">Información básica de protección de datos</strong><br>'
            . '<table cellpadding="0" cellspacing="0" border="0" style="margin-top:8px;width:100%;font-size:11px;color:#6b7280">'
            . '<tr><td style="vertical-align:top;width:110px;padding:2px 0"><strong>Responsable</strong></td>'
            . '<td style="padding:2px 0">Ganaderías Baltae · CIF B22575294.</td></tr>'
            . '<tr><td style="vertical-align:top;padding:2px 0"><strong>Finalidad</strong></td>'
            . '<td style="padding:2px 0">Gestión de tu cuenta y prestación del servicio de gestión de granjas.</td></tr>'
            . '<tr><td style="vertical-align:top;padding:2px 0"><strong>Legitimación</strong></td>'
            . '<td style="padding:2px 0">Ejecución del contrato (tu registro como usuario) y consentimiento del interesado.</td></tr>'
            . '<tr><td style="vertical-align:top;padding:2px 0"><strong>Destinatarios</strong></td>'
            . '<td style="padding:2px 0">No se cederán datos a terceros, salvo proveedores técnicos imprescindibles (hosting Dinahosting, S.L.U.). No se realizan transferencias internacionales.</td></tr>'
            . '<tr><td style="vertical-align:top;padding:2px 0"><strong>Conservación</strong></td>'
            . '<td style="padding:2px 0">Mientras la cuenta permanezca activa o exista una obligación legal de conservación.</td></tr>'
            . '<tr><td style="vertical-align:top;padding:2px 0"><strong>Derechos</strong></td>'
            . '<td style="padding:2px 0">Acceso, rectificación, supresión, oposición, limitación, portabilidad y a no ser objeto de decisiones automatizadas. Puedes ejercerlos respondiendo a este correo o escribiendo a <a href="mailto:notificaciones@baltae.com" style="color:#3b82f6">notificaciones@baltae.com</a>. También puedes presentar una reclamación ante la Agencia Española de Protección de Datos (<a href="https://www.aepd.es" style="color:#3b82f6">www.aepd.es</a>).</td></tr>'
            . '</table></div>';

        $html    = $T::build($body, 'blue');
        $subject = '¡Bienvenido/a a BALTAE!';

        try {
            return (new Mailer())->send($email, $subject, $html, true);
        } catch (\Throwable $e) {
            error_log('[AuthController] email bienvenida error: ' . $e->getMessage());
            return false;
        }
    }

    // ── POST /logout ─────────────────────────────────────────────
    public function logout(): void
    {
        // CSRF validado en el Router.
        $uid = Session::get('usuario_id');
        Session::destroy();
        SecurityLog::log('logout', ['user_id' => $uid]);
        $this->redirect('login');
    }

    // ── GET /forgot-password ─────────────────────────────────────
    public function forgotPasswordForm(): void
    {
        guest_only();
        $this->view('auth/forgot-password', [
            'error'   => Session::getFlash('error'),
            'success' => Session::getFlash('success'),
        ], 'auth');
    }

    // ── POST /forgot-password ────────────────────────────────────
    public function forgotPassword(): void
    {
        guest_only();
        // CSRF validado en el Router.

        $ip = client_ip();
        // Rate limit: 5 solicitudes por IP cada hora.
        if (!RateLimiter::attempt('forgot', $ip, 5, 3600)) {
            SecurityLog::log('forgot_rate_limited');
            Session::flash('error', 'Demasiadas solicitudes. Inténtalo más tarde.');
            $this->redirect('forgot-password');
        }

        $email = strtolower($this->postString('email'));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'El email no tiene un formato válido.');
            $this->redirect('forgot-password');
        }

        // Respuesta uniforme: ni el contenido ni el tiempo de respuesta
        // deben permitir distinguir si el email existe o no.
        if ($this->usuario->emailExists($email)) {
            $token    = $this->usuario->createPasswordReset($email);
            $resetUrl = base_url('reset-password/' . $token);
            SecurityNotifier::passwordResetRequested($email, $resetUrl, $ip);
            SecurityLog::log('password_reset_requested', ['email' => $email]);
        } else {
            // Igualar timing de un envío SMTP normal: 300–800 ms
            usleep(random_int(300_000, 800_000));
            SecurityLog::log('password_reset_requested_unknown', ['email' => $email]);
        }

        Session::flash('success', 'Si existe una cuenta con ese email, recibirás un enlace para restablecer tu contraseña.');
        $this->redirect('forgot-password');
    }

    // ── GET /reset-password/{token} ──────────────────────────────
    public function resetPasswordForm(string $token): void
    {
        guest_only();

        $reset = $this->usuario->findValidReset($token);
        if (!$reset) {
            Session::flash('error', 'El enlace de restablecimiento no es válido o ha expirado.');
            $this->redirect('forgot-password');
        }

        $this->view('auth/reset-password', [
            'token' => $token,
            'error' => Session::getFlash('error'),
        ], 'auth');
    }

    // ── POST /reset-password/{token} ─────────────────────────────
    public function resetPassword(string $token): void
    {
        guest_only();
        // CSRF validado en el Router.

        $ip = client_ip();
        // Rate limit: 10 intentos por IP cada hora
        if (!RateLimiter::attempt('reset', $ip, 10, 3600)) {
            SecurityLog::log('reset_rate_limited');
            Session::flash('error', 'Demasiados intentos. Inténtalo más tarde.');
            $this->redirect('reset-password/' . $token);
        }

        $reset = $this->usuario->findValidReset($token);
        if (!$reset) {
            SecurityLog::log('reset_invalid_token');
            Session::flash('error', 'El enlace de restablecimiento no es válido o ha expirado.');
            $this->redirect('forgot-password');
        }

        $password  = $this->postString('password');
        $password2 = $this->postString('password_confirm');

        $errors = $this->validatePassword($password, $password2);
        if ($errors) {
            Session::flash('error', implode('<br>', $errors));
            $this->redirect('reset-password/' . $token);
        }

        $user = $this->usuario->findByEmail($reset['email']);
        if (!$user) {
            Session::flash('error', 'No se encontró el usuario asociado a este enlace.');
            $this->redirect('forgot-password');
        }

        $this->usuario->resetPasswordById((int) $user['id'], $password);
        $this->usuario->deletePasswordReset($token);
        SecurityLog::log('password_reset_completed', ['user_id' => (int)$user['id']]);

        // Notificar al usuario del cambio efectivo.
        SecurityNotifier::passwordChanged(
            (string)$user['email'],
            $ip,
            'mediante un enlace de restablecimiento'
        );

        Session::flash('success', '¡Contraseña actualizada! Ya puedes iniciar sesión con tu nueva contraseña.');
        $this->redirect('login');
    }

    // ── Validación de contraseña ─────────────────────────────────
    private function validatePassword(string $password, string $password2): array
    {
        $errors = [];

        if (strlen($password) < 8) {
            $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'La contraseña debe contener al menos una mayúscula.';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'La contraseña debe contener al menos un número.';
        }
        if ($password !== $password2) {
            $errors[] = 'Las contraseñas no coinciden.';
        }

        return $errors;
    }

    // ── Validaciones ─────────────────────────────────────────────
    private function validateRegister(
        string $nombre,
        string $email,
        string $password,
        string $password2
    ): array {
        $errors = [];

        if (strlen($nombre) < 2) {
            $errors[] = 'El nombre debe tener al menos 2 caracteres.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'El email no tiene un formato válido.';
        }

        if (strlen($password) < 8) {
            $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'La contraseña debe contener al menos una mayúscula.';
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'La contraseña debe contener al menos un número.';
        }

        if ($password !== $password2) {
            $errors[] = 'Las contraseñas no coinciden.';
        }

        return $errors;
    }
}
