<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Organizacion;
use App\Models\Usuario;
use App\Core\Session;
use App\Core\OrgContext;
use App\Core\Database;

class EquipoController extends BaseController
{
    private Organizacion $orgModel;
    private Usuario      $userModel;

    public function __construct()
    {
        $this->orgModel  = new Organizacion();
        $this->userModel = new Usuario();
    }

    /** Listado de miembros de la org activa + form de invitación. */
    public function index(): void
    {
        auth_required();
        $orgId    = OrgContext::id();
        $miembros = $this->orgModel->miembros($orgId);
        $org      = $this->orgModel->find($orgId);

        // Invitaciones pendientes
        $stmt = Database::getInstance()->prepare("
            SELECT i.*, u.nombre AS invitado_por_nombre
            FROM invitaciones_organizacion i
            LEFT JOIN usuarios u ON i.invitado_por = u.id
            WHERE i.organizacion_id = :oid AND i.aceptado_at IS NULL AND i.expira_at > NOW()
            ORDER BY i.created_at DESC
        ");
        $stmt->execute(['oid' => $orgId]);
        $invsPendientes = $stmt->fetchAll();

        $this->view('equipo/index', [
            'pageTitle'      => 'Equipo · ' . ($org['nombre'] ?? ''),
            'org'            => $org,
            'miembros'       => $miembros,
            'invsPendientes' => $invsPendientes,
            'rolActual'      => OrgContext::rol(),
            'usuarioActual'  => (int) Session::get('usuario_id'),
            'success'        => Session::getFlash('success'),
            'error'          => Session::getFlash('error'),
        ]);
    }

    /** Crea una invitación con token. Devuelve el link para compartir. */
    public function invitar(): void
    {
        auth_required();
        if (!OrgContext::esAdmin()) {
            Session::flash('error', 'Solo admin/owner pueden invitar miembros.');
            $this->redirect('equipo');
        }
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            $this->redirect('equipo');
        }

        $email = strtolower(trim($this->postString('email')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Email no válido.');
            $this->redirect('equipo');
        }
        $rol = $this->postString('rol') ?: 'operario';
        if (!in_array($rol, ['admin', 'operario', 'lector'], true)) $rol = 'operario';

        $orgId   = OrgContext::id();
        $invitor = (int) Session::get('usuario_id');
        $token   = bin2hex(random_bytes(24));
        $expira  = date('Y-m-d H:i:s', strtotime('+7 days'));

        Database::getInstance()->prepare("
            INSERT INTO invitaciones_organizacion (organizacion_id, email, rol, token, invitado_por, expira_at)
            VALUES (:oid, :em, :rl, :tk, :ip, :ex)
        ")->execute([
            'oid' => $orgId,
            'em'  => $email,
            'rl'  => $rol,
            'tk'  => $token,
            'ip'  => $invitor,
            'ex'  => $expira,
        ]);

        $link = base_url('aceptar-invitacion/' . $token);
        Session::flash('success',
            "Invitación creada para <strong>{$email}</strong>. Comparte este enlace (válido 7 días):<br>"
            . "<code style='background:#e0f2fe;padding:.2rem .5rem;border-radius:.25rem;word-break:break-all;display:inline-block;margin-top:.4rem'>"
            . e($link) . "</code>"
        );
        $this->redirect('equipo');
    }

    /** Cambiar rol de un miembro (solo admin/owner, no se puede tocar al owner). */
    public function cambiarRol(string $userId): void
    {
        auth_required();
        if (!OrgContext::esAdmin()) $this->redirect('equipo');
        if (!Session::validateCsrf($this->postString('csrf_token'))) $this->redirect('equipo');

        $rol = $this->postString('rol');
        $ok  = $this->orgModel->cambiarRol(OrgContext::id(), (int)$userId, $rol);
        Session::flash($ok ? 'success' : 'error', $ok ? 'Rol actualizado.' : 'No se pudo cambiar el rol (¿es owner?).');
        $this->redirect('equipo');
    }

    /** Quitar a un miembro de la org (no al owner). */
    public function quitar(string $userId): void
    {
        auth_required();
        if (!OrgContext::esAdmin()) $this->redirect('equipo');
        if (!Session::validateCsrf($this->postString('csrf_token'))) $this->redirect('equipo');

        $ok = $this->orgModel->desvincular(OrgContext::id(), (int)$userId);
        Session::flash($ok ? 'success' : 'error', $ok ? 'Miembro eliminado.' : 'No se pudo eliminar (¿es owner?).');
        $this->redirect('equipo');
    }

    /** Pantalla pública para aceptar una invitación con token. */
    public function aceptarForm(string $token): void
    {
        $stmt = Database::getInstance()->prepare("
            SELECT i.*, o.nombre AS org_nombre
            FROM invitaciones_organizacion i
            JOIN organizaciones o ON o.id = i.organizacion_id
            WHERE i.token = :tk AND i.aceptado_at IS NULL AND i.expira_at > NOW()
            LIMIT 1
        ");
        $stmt->execute(['tk' => $token]);
        $inv = $stmt->fetch();

        $this->view('equipo/aceptar', [
            'pageTitle' => 'Aceptar invitación',
            'inv'       => $inv,
            'token'     => $token,
            'logueado'  => Session::has('usuario_id'),
            'error'     => Session::getFlash('error'),
        ]);
    }

    /** Procesa la aceptación: usuario debe estar logueado y el email debe coincidir. */
    public function aceptarPost(string $token): void
    {
        if (!Session::has('usuario_id')) {
            Session::flash('error', 'Inicia sesión o regístrate antes de aceptar la invitación.');
            $this->redirect('aceptar-invitacion/' . $token);
        }
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            $this->redirect('aceptar-invitacion/' . $token);
        }

        $db   = Database::getInstance();
        $stmt = $db->prepare("
            SELECT * FROM invitaciones_organizacion
            WHERE token = :tk AND aceptado_at IS NULL AND expira_at > NOW() LIMIT 1
        ");
        $stmt->execute(['tk' => $token]);
        $inv = $stmt->fetch();
        if (!$inv) {
            Session::flash('error', 'Invitación no válida o caducada.');
            $this->redirect('dashboard');
        }

        $userId    = (int) Session::get('usuario_id');
        $userEmail = strtolower((string) Session::get('usuario_email'));
        if ($userEmail !== strtolower($inv['email'])) {
            Session::flash('error',
                'Esta invitación es para ' . e($inv['email']) . '. Inicia sesión con esa cuenta.'
            );
            $this->redirect('dashboard');
        }

        // Vincular y marcar como aceptada
        $this->orgModel->vincular((int)$inv['organizacion_id'], $userId, $inv['rol'], (int)$inv['invitado_por']);
        $db->prepare("UPDATE invitaciones_organizacion SET aceptado_at = NOW() WHERE id = :id")
           ->execute(['id' => $inv['id']]);

        // Cambiar org activa a la nueva
        OrgContext::cambiar((int)$inv['organizacion_id']);

        Session::flash('success', 'Te has unido a la organización.');
        $this->redirect('dashboard');
    }
}
