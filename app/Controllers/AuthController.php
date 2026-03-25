<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Usuario;
use App\Core\Session;

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

        // Validar CSRF
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token de seguridad inválido. Recarga la página.');
            $this->redirect('login');
        }

        $email    = $this->postString('email');
        $password = $this->postString('password');

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
            // Mensaje genérico para no revelar si el email existe
            Session::flash('error', 'Email o contraseña incorrectos.');
            $this->redirect('login');
        }

        // Regenerar ID de sesión para prevenir session fixation
        session_regenerate_id(true);

        Session::set('usuario_id',     $user['id']);
        Session::set('usuario_nombre', $user['nombre']);
        Session::set('usuario_email',  $user['email']);

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

        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token de seguridad inválido. Recarga la página.');
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

        $this->usuario->create($nombre, $email, $password);

        Session::flash('success', '¡Cuenta creada! Ya puedes iniciar sesión.');
        $this->redirect('login');
    }

    // ── GET /logout ──────────────────────────────────────────────
    public function logout(): void
    {
        Session::destroy();
        $this->redirect('login');
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
