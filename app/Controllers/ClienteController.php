<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Organizacion;
use App\Models\Usuario;
use App\Core\Session;
use App\Core\Database;
use App\Core\Mailer;
use App\Core\EmailTemplate;
use App\Core\SecurityLog;

/**
 * Gestión de clientes (organizaciones) por parte del super-usuario.
 *
 * Solo accesible para usuarios con usuarios.rol = 'admin' (super-usuario
 * de plataforma, no admin dentro de una org). Permite crear un nuevo
 * cliente: crea la org y manda invitación como 'owner' al email del
 * admin del cliente.
 */
class ClienteController extends BaseController
{
    private Organizacion $orgModel;
    private Usuario $userModel;

    public function __construct()
    {
        $this->orgModel  = new Organizacion();
        $this->userModel = new Usuario();
    }

    private function requireSuperUser(): void
    {
        auth_required();
        if ((string)(Session::get('usuario_rol') ?? '') !== 'admin') {
            Session::flash('error', 'Solo el super-usuario puede gestionar clientes.');
            $this->redirect('dashboard');
        }
    }

    /** Listado de todos los clientes (organizaciones) de la plataforma. */
    public function index(): void
    {
        $this->requireSuperUser();

        $stmt = Database::getInstance()->prepare("
            SELECT o.id, o.nombre, o.plan, o.created_at,
                   (SELECT COUNT(*) FROM organizacion_usuarios ou WHERE ou.organizacion_id = o.id) AS num_miembros,
                   (SELECT COUNT(*) FROM granjas g WHERE g.organizacion_id = o.id AND g.activa = 1) AS num_granjas,
                   (SELECT u.nombre FROM organizacion_usuarios ou
                      JOIN usuarios u ON u.id = ou.usuario_id
                      WHERE ou.organizacion_id = o.id AND ou.rol = 'owner'
                      ORDER BY ou.created_at ASC LIMIT 1) AS owner_nombre,
                   (SELECT u.email FROM organizacion_usuarios ou
                      JOIN usuarios u ON u.id = ou.usuario_id
                      WHERE ou.organizacion_id = o.id AND ou.rol = 'owner'
                      ORDER BY ou.created_at ASC LIMIT 1) AS owner_email
            FROM organizaciones o
            WHERE o.activa = 1
            ORDER BY o.created_at DESC
        ");
        $stmt->execute();
        $clientes = $stmt->fetchAll();

        $this->view('clientes/index', [
            'pageTitle' => 'Clientes',
            'clientes'  => $clientes,
            'success'   => Session::getFlash('success'),
            'error'     => Session::getFlash('error'),
        ]);
    }

    /** Formulario de creación de cliente. */
    public function create(): void
    {
        $this->requireSuperUser();
        $this->view('clientes/nuevo', [
            'pageTitle' => 'Nuevo cliente',
            'error'     => Session::getFlash('error'),
            'old'       => Session::getFlash('old') ? json_decode((string)Session::getFlash('old'), true) : [],
        ]);
    }

    /**
     * Crea la organización y envía invitación al email indicado como
     * 'owner'. Esa persona recibirá un correo para registrarse (o
     * iniciar sesión si ya tenía cuenta) y al aceptar quedará como
     * owner de su propia org.
     */
    public function store(): void
    {
        $this->requireSuperUser();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            $this->redirect('clientes/nuevo');
        }

        $orgNombre = trim($this->postString('org_nombre'));
        $email     = strtolower(trim($this->postString('email')));

        Session::flash('old', json_encode(['org_nombre' => $orgNombre, 'email' => $email]));

        if (strlen($orgNombre) < 2) {
            Session::flash('error', 'El nombre de la organización es obligatorio.');
            $this->redirect('clientes/nuevo');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Email no válido.');
            $this->redirect('clientes/nuevo');
        }

        // 1. Crear la organización
        $orgId = $this->orgModel->create($orgNombre);

        // 2. Crear invitación como owner
        $token   = bin2hex(random_bytes(24));
        $expira  = date('Y-m-d H:i:s', strtotime('+7 days'));
        $invitor = (int) Session::get('usuario_id');
        Database::getInstance()->prepare("
            INSERT INTO invitaciones_organizacion (organizacion_id, email, rol, token, invitado_por, expira_at)
            VALUES (:oid, :em, 'owner', :tk, :ip, :ex)
        ")->execute([
            'oid' => $orgId, 'em' => $email, 'tk' => $token, 'ip' => $invitor, 'ex' => $expira,
        ]);

        SecurityLog::log('cliente_creado', [
            'org_id' => $orgId, 'org_nombre' => $orgNombre, 'owner_email' => $email,
        ]);

        // 3. Email de invitación
        $link    = base_url('aceptar-invitacion/' . $token);
        $emailOK = $this->enviarEmailClienteNuevo($email, $orgNombre, $link, $expira);

        $msgBase = "Cliente <strong>" . e($orgNombre) . "</strong> creado correctamente. "
                 . "Invitación de owner enviada a <strong>" . e($email) . "</strong>";
        if ($emailOK) {
            Session::flash('success', $msgBase . '.');
        } else {
            Session::flash('success',
                $msgBase . " (no se pudo enviar el email; comparte este enlace manualmente):<br>"
                . "<code style='background:#e0f2fe;padding:.2rem .5rem;border-radius:.25rem;word-break:break-all;display:inline-block;margin-top:.4rem'>"
                . e($link) . "</code>"
            );
        }
        $this->redirect('clientes');
    }

    /** Detalle de un cliente: miembros + granjas + invitaciones pendientes. */
    public function show(string $id): void
    {
        $this->requireSuperUser();
        $orgId = (int)$id;

        $org = $this->orgModel->find($orgId);
        if (!$org) {
            Session::flash('error', 'Cliente no encontrado.');
            $this->redirect('clientes');
        }

        $db = Database::getInstance();

        $miembros = $this->orgModel->miembros($orgId);

        $stmt = $db->prepare("
            SELECT g.id, g.nombre, g.codigo_rega, g.especie, g.tipo_produccion, g.capacidad_max,
                   (SELECT COUNT(*) FROM naves n WHERE n.granja_id = g.id AND n.activa = 1) AS num_naves
            FROM granjas g
            WHERE g.organizacion_id = :oid AND g.activa = 1
            ORDER BY g.nombre
        ");
        $stmt->execute(['oid' => $orgId]);
        $granjas = $stmt->fetchAll();

        $stmt = $db->prepare("
            SELECT i.email, i.rol, i.expira_at, i.created_at, i.token
            FROM invitaciones_organizacion i
            WHERE i.organizacion_id = :oid AND i.aceptado_at IS NULL AND i.expira_at > NOW()
            ORDER BY i.created_at DESC
        ");
        $stmt->execute(['oid' => $orgId]);
        $invsPendientes = $stmt->fetchAll();

        $this->view('clientes/show', [
            'pageTitle'      => $org['nombre'],
            'org'            => $org,
            'miembros'       => $miembros,
            'granjas'        => $granjas,
            'invsPendientes' => $invsPendientes,
            'success'        => Session::getFlash('success'),
            'error'          => Session::getFlash('error'),
        ]);
    }

    /** Edita nombre y plan del cliente. */
    public function update(string $id): void
    {
        $this->requireSuperUser();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            $this->redirect('clientes/' . $id);
        }
        $orgId  = (int)$id;
        $nombre = trim($this->postString('nombre'));
        $plan   = $this->postString('plan') ?: 'free';
        if (!in_array($plan, ['free', 'pro', 'enterprise'], true)) $plan = 'free';

        if (strlen($nombre) < 2) {
            Session::flash('error', 'El nombre es obligatorio.');
            $this->redirect('clientes/' . $orgId);
        }

        $stmt = Database::getInstance()->prepare("
            UPDATE organizaciones SET nombre = :n, plan = :p WHERE id = :id
        ");
        $stmt->execute(['n' => $nombre, 'p' => $plan, 'id' => $orgId]);

        SecurityLog::log('cliente_editado', ['org_id' => $orgId, 'nombre' => $nombre, 'plan' => $plan]);
        Session::flash('success', 'Cliente actualizado.');
        $this->redirect('clientes/' . $orgId);
    }

    /**
     * Desactiva un cliente (soft-delete activa=0). Sus datos quedan
     * intactos en BD pero la org deja de aparecer en /clientes y sus
     * miembros pierden acceso (su sesion sigue, pero auth_required
     * los manda a /sin-organizacion).
     */
    public function delete(string $id): void
    {
        $this->requireSuperUser();
        if (!Session::validateCsrf($this->postString('csrf_token'))) {
            $this->redirect('clientes/' . $id);
        }
        $orgId = (int)$id;

        $stmt = Database::getInstance()->prepare("
            UPDATE organizaciones SET activa = 0 WHERE id = :id
        ");
        $stmt->execute(['id' => $orgId]);
        $afectados = $stmt->rowCount();

        SecurityLog::log('cliente_desactivado', ['org_id' => $orgId]);
        Session::flash($afectados > 0 ? 'success' : 'error',
            $afectados > 0 ? 'Cliente desactivado correctamente.' : 'No se pudo desactivar.'
        );
        $this->redirect('clientes');
    }

    private function enviarEmailClienteNuevo(string $email, string $orgNombre, string $link, string $expira): bool
    {
        $cfg = require ROOT_PATH . '/config.php';
        if (empty($cfg['mail']['enabled']) || empty($cfg['mail']['smtp']['host'])) {
            return false;
        }

        $T = EmailTemplate::class;
        $body  = $T::title('Tu cuenta de BALTAE está lista');
        $body .= $T::paragraph(
            'Te damos acceso como administrador de tu propia organización en BALTAE — la plataforma '
            . 'de gestión de granjas. Tendrás control total sobre granjas, lotes, pesajes, movimientos, '
            . 'almacén e informes.'
        );
        $body .= $T::dataTable([
            ['label' => 'Organización', 'value' => e($orgNombre), 'bold' => true],
            ['label' => 'Email',         'value' => e($email)],
            ['label' => 'Rol',           'value' => 'Owner (admin propietario)'],
            ['label' => 'Caduca',        'value' => e(date('d/m/Y H:i', strtotime($expira)))],
        ]);
        $body .= $T::paragraph(
            'Pulsa el botón para aceptar el acceso. Si todavía no tienes cuenta, podrás crearla en '
            . 'el mismo paso con tu email ya asignado.'
        );
        $body .= $T::button('Activar mi cuenta', $link);
        $body .= $T::paragraph(
            '<span style="font-size:12px;color:#9ca3af">Si el botón no funciona, copia este enlace '
            . 'en tu navegador:<br><a href="' . e($link) . '" style="color:#3b82f6">' . e($link) . '</a></span>'
        );

        $html    = $T::build($body, 'blue');
        $subject = 'Bienvenido a BALTAE — Activa tu cuenta de ' . $orgNombre;

        try {
            return (new Mailer())->send($email, $subject, $html, true);
        } catch (\Throwable $e) {
            error_log('[ClienteController] email cliente nuevo error: ' . $e->getMessage());
            return false;
        }
    }
}
