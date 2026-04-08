<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Usuario;
use App\Core\Session;
use App\Core\Mailer;
use App\Core\RateLimiter;
use App\Core\SecurityLog;

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

        // Autenticar
        $user = $this->usuario->authenticate($email, $password);

        if (!$user) {
            SecurityLog::log('login_failed', ['email' => $email]);
            // Mensaje genérico para no revelar si el email existe
            Session::flash('error', 'Email o contraseña incorrectos.');
            $this->redirect('login');
        }

        // Éxito: limpiar contadores y rotar sesión
        RateLimiter::clear('login_account', $perAccount);
        RateLimiter::clear('login_ip', $ip);

        session_regenerate_id(true);
        Session::rotateCsrf();

        Session::set('usuario_id',     $user['id']);
        Session::set('usuario_nombre', $user['nombre']);
        Session::set('usuario_email',  $user['email']);
        Session::set('usuario_rol',    $user['rol'] ?? 'usuario');

        SecurityLog::log('login_success', ['user_id' => (int)$user['id']]);

        $this->redirect('dashboard');
    }

    // ── GET /register ────────────────────────────────────────────
    public function registerForm(): void
    {
        guest_only();
        $this->view('auth/register', [
            'error'   => Session::getFlash('error'),
            'success' => Session::getFlash('success'),
            'old'     => Session::getFlash('old') ? json_decode(Session::getFlash('old'), true) : [],
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

        Session::flash('success', '¡Cuenta creada! Ya puedes iniciar sesión.');
        $this->redirect('login');
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
            $this->sendResetEmail($email, $resetUrl);
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

        Session::flash('success', '¡Contraseña actualizada! Ya puedes iniciar sesión con tu nueva contraseña.');
        $this->redirect('login');
    }

    // ── Envío de email ───────────────────────────────────────────
    private function sendResetEmail(string $to, string $resetUrl): void
    {
        $subject = 'Restablecimiento de contraseña';
        $body    = "Hola,\n\n"
                 . "Hemos recibido una solicitud para restablecer la contraseña de tu cuenta.\n\n"
                 . "Haz clic en el siguiente enlace (válido durante 1 hora):\n"
                 . $resetUrl . "\n\n"
                 . "Si no solicitaste este cambio, puedes ignorar este mensaje.\n\n"
                 . "Saludos,\nEl equipo de Granja";

        (new Mailer())->send($to, $subject, $body);
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
