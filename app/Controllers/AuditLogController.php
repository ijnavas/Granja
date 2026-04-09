<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Session;

/**
 * Viewer para el audit log de negocio.
 * Solo accesible para admin.
 */
class AuditLogController extends BaseController
{
    public function index(): void
    {
        auth_required();
        require_rol('admin');

        $db = Database::getInstance();

        // Filtros (todos opcionales)
        $entidad   = trim((string)($_GET['entidad']   ?? ''));
        $accion    = trim((string)($_GET['accion']    ?? ''));
        $userId    = trim((string)($_GET['user_id']   ?? ''));
        $entidadId = trim((string)($_GET['entidad_id'] ?? ''));
        $desde     = trim((string)($_GET['fecha_desde'] ?? ''));
        $hasta     = trim((string)($_GET['fecha_hasta'] ?? ''));
        $page      = max(1, (int)($_GET['page'] ?? 1));
        $perPage   = 50;
        $offset    = ($page - 1) * $perPage;

        $where  = [];
        $params = [];
        if ($entidad !== '')   { $where[] = 'a.entidad = :entidad';     $params['entidad']    = $entidad; }
        if ($accion  !== '')   { $where[] = 'a.accion = :accion';       $params['accion']     = $accion;  }
        if ($userId  !== '')   { $where[] = 'a.user_id = :user_id';     $params['user_id']    = (int)$userId; }
        if ($entidadId !== '') { $where[] = 'a.entidad_id = :entidad_id'; $params['entidad_id'] = (int)$entidadId; }
        if ($desde !== '')     { $where[] = 'a.created_at >= :desde';   $params['desde']      = $desde . ' 00:00:00'; }
        if ($hasta !== '')     { $where[] = 'a.created_at <= :hasta';   $params['hasta']      = $hasta . ' 23:59:59'; }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        // Total para paginación
        $stmtCount = $db->prepare("SELECT COUNT(*) FROM audit_log a {$whereSql}");
        $stmtCount->execute($params);
        $total = (int)$stmtCount->fetchColumn();

        // Listado con join a usuarios (si existe)
        $sql = "SELECT a.*, u.nombre AS usuario_nombre, u.email AS usuario_email
                FROM audit_log a
                LEFT JOIN usuarios u ON u.id = a.user_id
                {$whereSql}
                ORDER BY a.created_at DESC, a.id DESC
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Opciones para los select de filtro (distintos valores actuales)
        $entidades = $db->query("SELECT DISTINCT entidad FROM audit_log ORDER BY entidad")->fetchAll(\PDO::FETCH_COLUMN);
        $acciones  = $db->query("SELECT DISTINCT accion  FROM audit_log ORDER BY accion")->fetchAll(\PDO::FETCH_COLUMN);

        $this->view('audit_log/index', [
            'rows'       => $rows,
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'entidades'  => $entidades,
            'acciones'   => $acciones,
            'filtros'    => [
                'entidad'     => $entidad,
                'accion'      => $accion,
                'user_id'     => $userId,
                'entidad_id'  => $entidadId,
                'fecha_desde' => $desde,
                'fecha_hasta' => $hasta,
            ],
            'pageTitle'  => 'Audit log',
        ]);
    }

    public function show(string $id): void
    {
        auth_required();
        require_rol('admin');

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT a.*, u.nombre AS usuario_nombre, u.email AS usuario_email
             FROM audit_log a
             LEFT JOIN usuarios u ON u.id = a.user_id
             WHERE a.id = :id"
        );
        $stmt->execute(['id' => (int)$id]);
        $row = $stmt->fetch();
        if (!$row) $this->redirect('admin/audit-log');

        $row['datos_antes_decoded']   = $row['datos_antes']   ? json_decode($row['datos_antes'],   true) : null;
        $row['datos_despues_decoded'] = $row['datos_despues'] ? json_decode($row['datos_despues'], true) : null;

        $this->view('audit_log/show', [
            'row'       => $row,
            'pageTitle' => 'Audit log #' . (int)$id,
        ]);
    }
}
