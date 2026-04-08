<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Usuario;
use App\Services\RecevtService;
use App\Core\Session;

class PerfilController extends BaseController
{
    private Usuario $model;

    public function __construct()
    {
        $this->model = new Usuario();
    }

    public function show(): void
    {
        auth_required();
        $uid  = Session::get('usuario_id');
        $user = $this->model->findById($uid) ?? [];
        // Para la vista, exponer SOLO booleanos derivados, nunca los valores reales.
        $secrets = $this->model->findRecevetSecretsById($uid) ?? [];
        $user['recevet_password_set']  = !empty($secrets['recevet_password_enc']);
        $user['recevet_session_set']   = !empty($secrets['recevet_session_cookie']);

        $this->view('perfil/index', [
            'user'      => $user,
            'pageTitle' => 'Mi perfil',
            'success'   => Session::getFlash('success'),
            'error'     => Session::getFlash('error'),
        ]);
    }

    public function updateInfo(): void
    {
        auth_required();
        $uid = Session::get('usuario_id');

        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect('perfil');
        }

        $nombre    = $this->postString('nombre');
        $apellidos = $this->postString('apellidos');
        $email     = $this->postString('email');
        $movil     = $this->postString('movil');

        if (!$nombre || !$email) {
            Session::flash('error', 'Nombre y email son obligatorios.');
            $this->redirect('perfil');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'El email no es válido.');
            $this->redirect('perfil');
        }

        if ($this->model->emailExistsForOther($email, $uid)) {
            Session::flash('error', 'Ese email ya está en uso por otra cuenta.');
            $this->redirect('perfil');
        }

        $emailPedidos = $this->postString('email_pedidos') ?: null;
        if ($emailPedidos && !filter_var($emailPedidos, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'El email de pedidos no es válido.');
            $this->redirect('perfil');
        }

        $this->model->updatePerfil($uid, $nombre, $apellidos, $email, $movil);
        if ($emailPedidos !== null) {
            $this->model->updateEmailPedidos($uid, $emailPedidos);
        }

        // Actualizar nombre en sesión
        Session::set('usuario_nombre', $nombre);

        Session::flash('success', 'Información actualizada correctamente.');
        $this->redirect('perfil');
    }

    public function updatePassword(): void
    {
        auth_required();
        $uid = Session::get('usuario_id');

        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect('perfil');
        }

        $actual     = $this->postString('password_actual');
        $nueva      = $this->postString('password_nueva');
        $confirmar  = $this->postString('password_confirmar');

        if (!$actual || !$nueva || !$confirmar) {
            Session::flash('error', 'Completa todos los campos de contraseña.');
            $this->redirect('perfil');
        }

        if ($nueva !== $confirmar) {
            Session::flash('error', 'La nueva contraseña y la confirmación no coinciden.');
            $this->redirect('perfil');
        }

        if (strlen($nueva) < 8) {
            Session::flash('error', 'La nueva contraseña debe tener al menos 8 caracteres.');
            $this->redirect('perfil');
        }

        $result = $this->model->changePassword($uid, $actual, $nueva);
        if ($result !== true) {
            Session::flash('error', $result);
            $this->redirect('perfil');
        }

        // Cambio de contraseña: regenerar sesión y rotar CSRF
        session_regenerate_id(true);
        Session::rotateCsrf();

        Session::flash('success', 'Contraseña cambiada correctamente.');
        $this->redirect('perfil');
    }

    public function updateRecevet(): void
    {
        auth_required();
        $uid = Session::get('usuario_id');

        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            Session::flash('error', 'Token inválido.');
            $this->redirect('perfil');
        }

        $recevtUsuario  = trim($this->postString('recevet_usuario'));
        $recevtPassword = $this->postString('recevet_password');

        if (empty($recevtUsuario)) {
            Session::flash('error', 'El usuario de Recevet no puede estar vacío.');
            $this->redirect('perfil');
        }

        $secrets     = $this->model->findRecevetSecretsById($uid);
        $passwordEnc = $secrets['recevet_password_enc'] ?? null; // Mantener la anterior si no se cambia

        if (!empty($recevtPassword)) {
            $passwordEnc = RecevtService::encryptPassword($recevtPassword);
        }

        // Cookie de sesión (opcional, puede estar vacía)
        $sessionCookie = trim($this->postString('recevet_session_cookie'));

        $this->model->updateRecevet($uid, $recevtUsuario, $passwordEnc, $sessionCookie ?: null);

        Session::flash('success', 'Credenciales de Recevet guardadas correctamente.');
        $this->redirect('perfil');
    }
}
