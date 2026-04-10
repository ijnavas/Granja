<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class Pesaje
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Construye cláusula WHERE + params para filtros de pesajes.
     */
    private function buildPesajeFiltros(int $userId, array $filtros): array
    {
        $conditions = ['g.usuario_id = :uid'];
        $params     = ['uid' => $userId];

        if (!empty($filtros['fecha_desde'])) {
            $conditions[] = 'p.fecha >= :fecha_desde';
            $params['fecha_desde'] = $filtros['fecha_desde'];
        }
        if (!empty($filtros['fecha_hasta'])) {
            $conditions[] = 'p.fecha <= :fecha_hasta';
            $params['fecha_hasta'] = $filtros['fecha_hasta'];
        }
        if (!empty($filtros['lote'])) {
            $conditions[] = 'l.codigo LIKE :lote';
            $params['lote'] = '%' . $filtros['lote'] . '%';
        }
        if (!empty($filtros['granja_id'])) {
            $conditions[] = 'g.id = :granja_id';
            $params['granja_id'] = (int) $filtros['granja_id'];
        }

        return [implode(' AND ', $conditions), $params];
    }

    public function countByUsuario(int $userId, array $filtros = []): int
    {
        [$where, $params] = $this->buildPesajeFiltros($userId, $filtros);
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM pesajes p
            JOIN lotes l   ON p.lote_id = l.id
            JOIN granjas g ON l.granja_id = g.id
            WHERE {$where}
        ");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function allByUsuario(int $userId, array $filtros = [], ?int $limit = null, int $offset = 0): array
    {
        [$where, $params] = $this->buildPesajeFiltros($userId, $filtros);

        $limitSql = '';
        if ($limit !== null) {
            $limitSql = 'LIMIT :lim OFFSET :off';
        }

        $stmt = $this->db->prepare("
            SELECT p.*,
                   l.codigo          AS lote_codigo,
                   l.fecha_nacimiento,
                   l.raza_id,
                   n.nombre          AS nave_nombre,
                   g.nombre          AS granja_nombre,
                   CEIL(DATEDIFF(p.fecha, l.fecha_nacimiento) / 7) AS semana_pesaje,
                   tcl_p.peso_kg     AS peso_tabla_pesaje,
                   tcl_h.peso_kg     AS peso_tabla_hoy,
                   ROUND(p.peso_medio_kg + COALESCE(tcl_h.peso_kg, 0) - COALESCE(tcl_p.peso_kg, 0), 3) AS peso_proyectado_hoy
            FROM pesajes p
            JOIN lotes l    ON p.lote_id = l.id
            JOIN granjas g  ON l.granja_id = g.id
            LEFT JOIN naves n ON l.nave_id = n.id
            LEFT JOIN tabla_raza tr ON tr.raza_id = l.raza_id
            LEFT JOIN tablas_crecimiento tc ON tc.id = tr.tabla_id AND tc.activa = 1
            LEFT JOIN tablas_crecimiento_lineas tcl_p
                ON tcl_p.tabla_id = tc.id
                AND tcl_p.semana  = CEIL(DATEDIFF(p.fecha, l.fecha_nacimiento) / 7)
            LEFT JOIN tablas_crecimiento_lineas tcl_h
                ON tcl_h.tabla_id = tc.id
                AND tcl_h.semana  = CEIL(DATEDIFF(CURDATE(), l.fecha_nacimiento) / 7)
            WHERE {$where}
            ORDER BY p.fecha DESC, p.id DESC
            {$limitSql}
        ");
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        if ($limit !== null) {
            $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
            $stmt->bindValue('off', $offset, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function allByLote(int $loteId): array
    {
        $stmt = $this->db->prepare("
            SELECT p.*,
                   l.fecha_nacimiento,
                   CEIL(DATEDIFF(p.fecha, l.fecha_nacimiento) / 7) AS semana_pesaje,
                   tcl_p.peso_kg     AS peso_tabla_pesaje,
                   tcl_h.peso_kg     AS peso_tabla_hoy,
                   ROUND(p.peso_medio_kg + COALESCE(tcl_h.peso_kg, 0) - COALESCE(tcl_p.peso_kg, 0), 3) AS peso_proyectado_hoy
            FROM pesajes p
            JOIN lotes l ON p.lote_id = l.id
            LEFT JOIN tabla_raza tr ON tr.raza_id = l.raza_id
            LEFT JOIN tablas_crecimiento tc ON tc.id = tr.tabla_id AND tc.activa = 1
            LEFT JOIN tablas_crecimiento_lineas tcl_p
                ON tcl_p.tabla_id = tc.id
                AND tcl_p.semana  = CEIL(DATEDIFF(p.fecha, l.fecha_nacimiento) / 7)
            LEFT JOIN tablas_crecimiento_lineas tcl_h
                ON tcl_h.tabla_id = tc.id
                AND tcl_h.semana  = CEIL(DATEDIFF(CURDATE(), l.fecha_nacimiento) / 7)
            WHERE p.lote_id = :lote_id
            ORDER BY p.fecha DESC
        ");
        $stmt->execute(['lote_id' => $loteId]);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM pesajes WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Último pesaje de un lote con proyección al momento actual.
     */
    public function ultimoPorLote(int $loteId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT p.*,
                   CEIL(DATEDIFF(p.fecha, l.fecha_nacimiento) / 7) AS semana_pesaje,
                   tcl_p.peso_kg  AS peso_tabla_pesaje,
                   tcl_h.peso_kg  AS peso_tabla_hoy,
                   ROUND(p.peso_medio_kg + COALESCE(tcl_h.peso_kg, 0) - COALESCE(tcl_p.peso_kg, 0), 3) AS peso_proyectado_hoy
            FROM pesajes p
            JOIN lotes l ON p.lote_id = l.id
            LEFT JOIN tabla_raza tr ON tr.raza_id = l.raza_id
            LEFT JOIN tablas_crecimiento tc ON tc.id = tr.tabla_id AND tc.activa = 1
            LEFT JOIN tablas_crecimiento_lineas tcl_p
                ON tcl_p.tabla_id = tc.id
                AND tcl_p.semana  = CEIL(DATEDIFF(p.fecha, l.fecha_nacimiento) / 7)
            LEFT JOIN tablas_crecimiento_lineas tcl_h
                ON tcl_h.tabla_id = tc.id
                AND tcl_h.semana  = CEIL(DATEDIFF(CURDATE(), l.fecha_nacimiento) / 7)
            WHERE p.lote_id = :lote_id
            ORDER BY p.fecha DESC
            LIMIT 1
        ");
        $stmt->execute(['lote_id' => $loteId]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO pesajes (lote_id, cuadra_id, fecha, peso_medio_kg, num_animales_pesados, consumo_pienso_kg, ic_real, observaciones, usuario_id)
            VALUES (:lote_id, :cuadra_id, :fecha, :peso_medio_kg, :num_animales_pesados, :consumo_pienso_kg, :ic_real, :observaciones, :usuario_id)
        ");
        $stmt->execute($data);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE pesajes SET
                fecha                = :fecha,
                peso_medio_kg        = :peso_medio_kg,
                num_animales_pesados = :num_animales_pesados,
                consumo_pienso_kg    = :consumo_pienso_kg,
                ic_real              = :ic_real,
                observaciones        = :observaciones
            WHERE id = :id
        ");
        $data['id'] = $id;
        return $stmt->execute($data);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM pesajes WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}
