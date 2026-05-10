<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class Movimiento
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Construye WHERE + params para filtros de movimientos.
     */
    private function buildMovFiltros(int $userId, array $filtros): array
    {
        $conditions = ['(g.organizacion_id = :uid OR gn.organizacion_id = :uid2)'];
        $oid    = \App\Core\OrgContext::id();
        $params = ['uid' => $oid, 'uid2' => $oid];

        if (!empty($filtros['fecha_desde'])) {
            $conditions[] = 'm.fecha >= :fecha_desde';
            $params['fecha_desde'] = $filtros['fecha_desde'];
        }
        if (!empty($filtros['fecha_hasta'])) {
            $conditions[] = 'm.fecha <= :fecha_hasta';
            $params['fecha_hasta'] = $filtros['fecha_hasta'];
        }
        if (!empty($filtros['tipo'])) {
            $conditions[] = 'm.tipo = :tipo';
            $params['tipo'] = $filtros['tipo'];
        }
        if (!empty($filtros['lote'])) {
            $conditions[] = '(lo.codigo LIKE :lote OR ld.codigo LIKE :lote2)';
            $params['lote']  = '%' . $filtros['lote'] . '%';
            $params['lote2'] = '%' . $filtros['lote'] . '%';
        }

        return [implode(' AND ', $conditions), $params];
    }

    // ── Listado ──────────────────────────────────────────────────
    public function countByUsuario(int $userId, array $filtros = []): int
    {
        [$where, $params] = $this->buildMovFiltros($userId, $filtros);
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM movimientos m
            JOIN lotes lo ON m.lote_origen_id = lo.id
            LEFT JOIN lotes ld ON m.lote_destino_id = ld.id
            LEFT JOIN granjas g  ON lo.granja_id = g.id
            LEFT JOIN naves  nlo ON lo.nave_id = nlo.id
            LEFT JOIN granjas gn ON nlo.granja_id = gn.id
            WHERE {$where}
        ");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function allByUsuario(int $userId, array $filtros = [], ?int $limit = null, int $offset = 0): array
    {
        [$where, $params] = $this->buildMovFiltros($userId, $filtros);

        $limitSql = $limit !== null ? 'LIMIT :lim OFFSET :off' : 'LIMIT 500';

        $stmt = $this->db->prepare("
            SELECT m.*,
                   lo.codigo  AS lote_origen_codigo,
                   ld.codigo  AS lote_destino_codigo,
                   co.nombre  AS cuadra_origen_nombre,
                   cd.nombre  AS cuadra_destino_nombre,
                   no.nombre  AS nave_origen_nombre,
                   nd.nombre  AS nave_destino_nombre,
                   u.nombre   AS usuario_nombre
            FROM movimientos m
            JOIN lotes lo ON m.lote_origen_id = lo.id
            LEFT JOIN lotes ld ON m.lote_destino_id = ld.id
            LEFT JOIN cuadras co ON m.cuadra_origen_id = co.id
            LEFT JOIN cuadras cd ON m.cuadra_destino_id = cd.id
            LEFT JOIN naves no ON co.nave_id = no.id
            LEFT JOIN naves nd ON cd.nave_id = nd.id
            LEFT JOIN granjas g  ON lo.granja_id = g.id
            LEFT JOIN naves  nlo ON lo.nave_id = nlo.id
            LEFT JOIN granjas gn ON nlo.granja_id = gn.id
            JOIN usuarios u ON m.usuario_id = u.id
            WHERE {$where}
            ORDER BY m.fecha DESC, m.created_at DESC
            {$limitSql}
        ");

        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        if ($limit !== null) {
            $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
            $stmt->bindValue('off', $offset, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT m.*,
                   lo.codigo AS lote_origen_codigo,
                   ld.codigo AS lote_destino_codigo,
                   co.nombre AS cuadra_origen_nombre,
                   cd.nombre AS cuadra_destino_nombre
            FROM movimientos m
            JOIN lotes lo ON m.lote_origen_id = lo.id
            LEFT JOIN lotes ld ON m.lote_destino_id = ld.id
            LEFT JOIN cuadras co ON m.cuadra_origen_id = co.id
            LEFT JOIN cuadras cd ON m.cuadra_destino_id = cd.id
            WHERE m.id = :id
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    // ── Crear ────────────────────────────────────────────────────
    public function create(array $data, int $userId): int
    {
        $data['usuario_id'] = $userId;
        $data['lote_id']    = $data['lote_origen_id'];

        // peso_real_kg es opcional (peso individual real para bajas)
        if (!array_key_exists('peso_real_kg', $data))    $data['peso_real_kg']    = null;
        if (!array_key_exists('albaran_archivo', $data)) $data['albaran_archivo'] = null;

        $stmt = $this->db->prepare("
            INSERT INTO movimientos
                (lote_id, tipo, fecha, lote_origen_id, lote_destino_id, cuadra_origen_id, cuadra_destino_id,
                 num_animales, peso_canal_kg, peso_real_kg, precio_eur, tipo_venta, motivo_baja, observaciones, albaran_archivo, usuario_id)
            VALUES
                (:lote_id, :tipo, :fecha, :lote_origen_id, :lote_destino_id, :cuadra_origen_id, :cuadra_destino_id,
                 :num_animales, :peso_canal_kg, :peso_real_kg, :precio_eur, :tipo_venta, :motivo_baja, :observaciones, :albaran_archivo, :usuario_id)
        ");
        $stmt->execute($data);
        $id = (int) $this->db->lastInsertId();
        $this->registrarHistorial($id, 'crear', null, $data, $userId);
        return $id;
    }

    // ── Actualizar ───────────────────────────────────────────────
    public function update(int $id, array $data, int $userId): bool
    {
        $antes = $this->find($id);
        if (!array_key_exists('peso_real_kg', $data))    $data['peso_real_kg']    = null;
        if (!array_key_exists('albaran_archivo', $data)) $data['albaran_archivo'] = $antes['albaran_archivo'] ?? null;

        $stmt  = $this->db->prepare("
            UPDATE movimientos SET
                tipo              = :tipo,
                fecha             = :fecha,
                lote_origen_id    = :lote_origen_id,
                lote_destino_id   = :lote_destino_id,
                cuadra_origen_id  = :cuadra_origen_id,
                cuadra_destino_id = :cuadra_destino_id,
                num_animales      = :num_animales,
                peso_canal_kg     = :peso_canal_kg,
                peso_real_kg      = :peso_real_kg,
                precio_eur        = :precio_eur,
                tipo_venta        = :tipo_venta,
                motivo_baja       = :motivo_baja,
                observaciones     = :observaciones,
                albaran_archivo   = :albaran_archivo
            WHERE id = :id
        ");
        $data['id'] = $id;
        $ok = $stmt->execute($data);
        if ($ok) $this->registrarHistorial($id, 'editar', $antes, $data, $userId);
        return $ok;
    }

    // ── Eliminar ─────────────────────────────────────────────────
    public function delete(int $id, int $userId): bool
    {
        $antes = $this->find($id);
        // Registrar historial ANTES de borrar (el CASCADE lo eliminaría después)
        $this->registrarHistorial($id, 'eliminar', $antes, null, $userId);
        $stmt = $this->db->prepare("DELETE FROM movimientos WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    // ── Historial ────────────────────────────────────────────────
    private function registrarHistorial(int $movId, string $accion, ?array $antes, ?array $despues, int $userId): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO movimientos_historial (movimiento_id, accion, datos_antes, datos_despues, usuario_id)
            VALUES (:mid, :accion, :antes, :despues, :uid)
        ");
        $stmt->execute([
            'mid'     => $movId,
            'accion'  => $accion,
            'antes'   => $antes   ? json_encode($antes)   : null,
            'despues' => $despues ? json_encode($despues)  : null,
            'uid'     => $userId,
        ]);
    }

    public function historial(int $movId): array
    {
        $stmt = $this->db->prepare("
            SELECT h.*, u.nombre AS usuario_nombre
            FROM movimientos_historial h
            JOIN usuarios u ON h.usuario_id = u.id
            WHERE h.movimiento_id = :id
            ORDER BY h.created_at DESC
        ");
        $stmt->execute(['id' => $movId]);
        return $stmt->fetchAll();
    }

    // ── Estados animal ───────────────────────────────────────────
    public function estadosAnimal(): array
    {
        return $this->db->query("SELECT * FROM estados_animal WHERE activo = 1 ORDER BY nombre")->fetchAll();
    }
}