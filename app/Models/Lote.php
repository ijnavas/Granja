<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class Lote
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Construye WHERE + params para filtros de lotes.
     */
    private function buildLoteFiltros(int $userId, array $filtros): array
    {
        $conditions = ['(g.organizacion_id = :uid OR g2.organizacion_id = :uid2)'];
        $params     = ['uid' => \App\Core\OrgContext::id(), 'uid2' => \App\Core\OrgContext::id()];

        if (!empty($filtros['estado'])) {
            $conditions[] = 'l.estado = :estado';
            $params['estado'] = $filtros['estado'];
        }
        if (!empty($filtros['granja_id'])) {
            $conditions[] = 'COALESCE(g.id, g2.id) = :granja_id';
            $params['granja_id'] = (int) $filtros['granja_id'];
        }
        if (!empty($filtros['raza_id'])) {
            $conditions[] = 'l.raza_id = :raza_id';
            $params['raza_id'] = (int) $filtros['raza_id'];
        }
        if (!empty($filtros['codigo'])) {
            $conditions[] = 'l.codigo LIKE :codigo';
            $params['codigo'] = '%' . $filtros['codigo'] . '%';
        }

        return [implode(' AND ', $conditions), $params];
    }

    public function countByUsuario(int $userId, array $filtros = []): int
    {
        [$where, $params] = $this->buildLoteFiltros($userId, $filtros);
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM lotes l
            JOIN tipos_animal ta ON l.tipo_animal_id = ta.id
            LEFT JOIN naves n   ON l.nave_id    = n.id
            LEFT JOIN granjas g ON n.granja_id  = g.id
            LEFT JOIN granjas g2 ON l.granja_id = g2.id
            WHERE {$where}
        ");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function allByUsuario(int $userId, array $filtros = [], ?int $limit = null, int $offset = 0): array
    {
        [$where, $params] = $this->buildLoteFiltros($userId, $filtros);

        $limitSql = '';
        if ($limit !== null) {
            $limitSql = 'LIMIT :lim OFFSET :off';
        }

        $stmt = $this->db->prepare("
            SELECT l.*,
                   ta.nombre  AS tipo_animal_nombre,
                   ta.especie AS especie,
                   n.nombre   AS nave_nombre,
                   COALESCE(g.nombre, g2.nombre) AS granja_nombre,
                   DATEDIFF(CURDATE(), l.fecha_entrada) AS dias_en_granja,
                   CEIL(DATEDIFF(CURDATE(), l.fecha_nacimiento) / 7) AS semana_actual,
                   r.nombre AS raza_nombre,
                   r.identificador AS raza_identificador,
                   tcl.peso_kg  AS peso_tabla,
                   tcl.coste_eur AS coste_tabla,
                   tcl.consumo_acumulado_g AS consumo_tabla,
                   ult_p.peso_medio_kg   AS ultimo_peso_real,
                   ult_p.fecha           AS ultimo_pesaje_fecha,
                   CEIL(DATEDIFF(ult_p.fecha, l.fecha_nacimiento) / 7) AS semana_ultimo_pesaje,
                   tcl_p.peso_kg         AS peso_tabla_en_pesaje,
                   ROUND(ult_p.peso_medio_kg + COALESCE(tcl.peso_kg, 0) - COALESCE(tcl_p.peso_kg, 0), 3) AS peso_real_proyectado
            FROM lotes l
            JOIN tipos_animal ta ON l.tipo_animal_id = ta.id
            LEFT JOIN naves n   ON l.nave_id    = n.id
            LEFT JOIN granjas g ON n.granja_id  = g.id
            LEFT JOIN granjas g2 ON l.granja_id = g2.id
            LEFT JOIN razas_porcino r ON l.raza_id = r.id
            LEFT JOIN tabla_raza tr ON tr.raza_id = r.id
            LEFT JOIN tablas_crecimiento tc ON tc.id = tr.tabla_id AND tc.activa = 1
            LEFT JOIN tablas_crecimiento_lineas tcl
                ON tcl.tabla_id = tc.id
                AND tcl.semana = CEIL(DATEDIFF(CURDATE(), l.fecha_nacimiento) / 7)
            LEFT JOIN pesajes ult_p ON ult_p.id = (
                SELECT id FROM pesajes WHERE lote_id = l.id ORDER BY fecha DESC LIMIT 1
            )
            LEFT JOIN tablas_crecimiento_lineas tcl_p
                ON tcl_p.tabla_id = tc.id
                AND tcl_p.semana  = CEIL(DATEDIFF(ult_p.fecha, l.fecha_nacimiento) / 7)
            WHERE {$where}
            ORDER BY l.estado, l.fecha_nacimiento ASC
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

    public function find(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT l.*, ta.nombre AS tipo_animal_nombre,
                   n.nombre AS nave_nombre,
                   COALESCE(g.nombre, g2.nombre) AS granja_nombre,
                   COALESCE(g.id, g2.id) AS granja_id
            FROM lotes l
            JOIN tipos_animal ta ON l.tipo_animal_id = ta.id
            LEFT JOIN naves n   ON l.nave_id    = n.id
            LEFT JOIN granjas g ON n.granja_id  = g.id
            LEFT JOIN granjas g2 ON l.granja_id = g2.id
            WHERE l.id = :id AND (g.organizacion_id = :uid OR g2.organizacion_id = :uid2 OR (l.nave_id IS NULL AND l.granja_id IS NULL))
        ");
        $stmt->execute(['id' => $id, 'uid' => \App\Core\OrgContext::id(), 'uid2' => \App\Core\OrgContext::id()]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Genera código tipo L 26/17 o L 26/17 IB
     * sufijo = 'IB', 'DU', etc. según la raza
     */
    public static function generarCodigo(string $fechaNacimiento, ?string $sufijo = null): string
    {
        $dt   = new \DateTime($fechaNacimiento);
        $year = $dt->format('y');
        $week = $dt->format('W');
        $base = "L {$week}/{$year}";
        return $sufijo ? "{$base} {$sufijo}" : $base;
    }

    public function codigoExisteSimple(string $codigo, ?int $exceptId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM lotes WHERE codigo = :codigo";
        $params = ['codigo' => $codigo];
        if ($exceptId) {
            $sql .= " AND id != :id";
            $params['id'] = $exceptId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function create(array $data): int
    {
        // Guardar num_animales_entrada para el histórico
        $data['num_animales_entrada'] = $data['num_animales'] ?? 0;
        $stmt = $this->db->prepare("
            INSERT INTO lotes (nave_id, granja_id, tipo_animal_id, raza_id, codigo, num_animales, num_animales_entrada, peso_entrada_kg, fecha_entrada, fecha_nacimiento, observaciones)
            VALUES (:nave_id, :granja_id, :tipo_animal_id, :raza_id, :codigo, :num_animales, :num_animales_entrada, :peso_entrada_kg, :fecha_entrada, :fecha_nacimiento, :observaciones)
        ");
        $stmt->execute($data);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, int $userId, array $data): bool
    {
        $setCodigo = !empty($data['codigo']) ? 'l.codigo = :codigo,' : '';
        $stmt = $this->db->prepare("
            UPDATE lotes l
            LEFT JOIN naves n ON l.nave_id = n.id
            LEFT JOIN granjas g ON n.granja_id = g.id
            SET {$setCodigo}
                l.nave_id          = :nave_id,
                l.granja_id        = :granja_id,
                l.tipo_animal_id   = :tipo_animal_id,
                l.raza_id          = :raza_id,
                l.num_animales     = :num_animales,
                l.peso_entrada_kg  = :peso_entrada_kg,
                l.fecha_entrada    = :fecha_entrada,
                l.fecha_nacimiento = :fecha_nacimiento,
                l.observaciones    = :observaciones
            WHERE l.id = :id AND (g.organizacion_id = :usuario_id OR l.nave_id IS NULL OR l.granja_id IS NOT NULL)
        ");
        if (empty($data['codigo'])) unset($data['codigo']);
        $data['id'] = $id;
        $data['usuario_id'] = $userId;
        return $stmt->execute($data);
    }

    public function ajustarAnimales(int $id, int $cantidad, string $tipo): bool
    {
        // tipo: 'añadir' o 'reducir'
        $op = $tipo === 'añadir' ? '+' : '-';
        $stmt = $this->db->prepare("
            UPDATE lotes SET num_animales = GREATEST(0, num_animales {$op} :cantidad)
            WHERE id = :id
        ");
        return $stmt->execute(['cantidad' => $cantidad, 'id' => $id]);
    }

    public function asignarNave(int $id, ?int $naveId): bool
    {
        $stmt = $this->db->prepare("UPDATE lotes SET nave_id = :nave_id WHERE id = :id");
        return $stmt->execute(['nave_id' => $naveId, 'id' => $id]);
    }

    public function delete(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE lotes l
            LEFT JOIN naves n ON l.nave_id = n.id
            LEFT JOIN granjas g ON n.granja_id = g.id
            SET l.estado = 'cerrado'
            WHERE l.id = :id AND (g.organizacion_id = :uid OR l.nave_id IS NULL)
        ");
        return $stmt->execute(['id' => $id, 'uid' => \App\Core\OrgContext::id()]);
    }

    /**
     * Actualiza automáticamente estado_animal de 'lechon' a 'cebo'
     * cuando el peso de tabla para la semana actual >= 22 kg.
     * Devuelve el número de lotes actualizados.
     */
    public function actualizarEstadoLechonACebo(int $userId): int
    {
        $stmt = $this->db->prepare("
            UPDATE lotes l
            JOIN granjas g ON l.granja_id = g.id
            JOIN tabla_raza tr ON tr.raza_id = l.raza_id
            JOIN tablas_crecimiento tc ON tc.id = tr.tabla_id AND tc.activa = 1
            JOIN tablas_crecimiento_lineas tcl
                ON tcl.tabla_id = tc.id
                AND tcl.semana  = CEIL(DATEDIFF(CURDATE(), l.fecha_nacimiento) / 7)
            JOIN estados_animal ea ON ea.codigo = 'cebo' AND ea.peso_min_kg IS NOT NULL
            SET l.estado_animal = 'cebo'
            WHERE g.organizacion_id       = :uid
              AND l.estado           = 'activo'
              AND l.estado_animal    = 'lechon'
              AND l.fecha_nacimiento IS NOT NULL
              AND l.raza_id          IS NOT NULL
              AND tcl.peso_kg        >= ea.peso_min_kg
        ");
        $stmt->execute(['uid' => \App\Core\OrgContext::id()]);
        return $stmt->rowCount();
    }

    /**
     * Elimina el lote y todo lo relacionado (movimientos, cuadras, pesajes).
     * Usa transacción para garantizar integridad.
     */
    public function eliminarCompleto(int $id, int $userId): bool
    {
        // Verificar pertenencia
        $stmt = $this->db->prepare("
            SELECT l.id FROM lotes l
            LEFT JOIN naves n   ON l.nave_id   = n.id
            LEFT JOIN granjas g  ON n.granja_id  = g.id
            LEFT JOIN granjas g2 ON l.granja_id  = g2.id
            WHERE l.id = :id AND (g.organizacion_id = :uid OR g2.organizacion_id = :uid2)
        ");
        $stmt->execute(['id' => $id, 'uid' => \App\Core\OrgContext::id(), 'uid2' => \App\Core\OrgContext::id()]);
        if (!$stmt->fetch()) return false;

        $this->db->beginTransaction();
        try {
            // 1. movimiento_cuadras (hijos de movimientos de este lote)
            $this->db->prepare("
                DELETE mc FROM movimiento_cuadras mc
                JOIN movimientos m ON m.id = mc.movimiento_id
                WHERE m.lote_origen_id = :id OR m.lote_destino_id = :id2
            ")->execute(['id' => $id, 'id2' => $id]);

            // 2. movimientos_historial
            $this->db->prepare("
                DELETE mh FROM movimientos_historial mh
                JOIN movimientos m ON m.id = mh.movimiento_id
                WHERE m.lote_origen_id = :id OR m.lote_destino_id = :id2
            ")->execute(['id' => $id, 'id2' => $id]);

            // 3. movimientos
            $this->db->prepare("
                DELETE FROM movimientos
                WHERE lote_origen_id = :id OR lote_destino_id = :id2
            ")->execute(['id' => $id, 'id2' => $id]);

            // 4. cuadra_lote (libera las cuadras)
            $this->db->prepare("
                DELETE FROM cuadra_lote WHERE lote_id = :id
            ")->execute(['id' => $id]);

            // 5. pesajes
            $this->db->prepare("
                DELETE FROM pesajes WHERE lote_id = :id
            ")->execute(['id' => $id]);

            // 6. lote
            $this->db->prepare("
                DELETE FROM lotes WHERE id = :id
            ")->execute(['id' => $id]);

            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function cerrar(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE lotes l
            LEFT JOIN naves n  ON l.nave_id   = n.id
            LEFT JOIN granjas g ON n.granja_id = g.id
            LEFT JOIN granjas g2 ON l.granja_id = g2.id
            SET l.estado = 'cerrado', l.fecha_cierre = CURDATE()
            WHERE l.id = :id AND (g.organizacion_id = :uid OR g2.organizacion_id = :uid2)
        ");
        return $stmt->execute(['id' => $id, 'uid' => \App\Core\OrgContext::id(), 'uid2' => \App\Core\OrgContext::id()]);
    }

    public function cerrarSiVacio(int $id): void
    {
        $stmt = $this->db->prepare("SELECT num_animales FROM lotes WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if ($row && (int)$row['num_animales'] === 0) {
            $this->db->prepare("UPDATE lotes SET estado = 'cerrado', fecha_cierre = CURDATE() WHERE id = :id AND estado = 'activo'")
                     ->execute(['id' => $id]);
        }
    }

    public function allCerradosByUsuario(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT l.*,
                   ta.nombre  AS tipo_animal_nombre,
                   n.nombre   AS nave_nombre,
                   COALESCE(g.nombre, g2.nombre) AS granja_nombre,
                   r.nombre   AS raza_nombre,
                   -- Resumen ventas
                   SUM(CASE WHEN m.tipo = 'venta' THEN m.num_animales ELSE 0 END) AS total_vendidos,
                   SUM(CASE WHEN m.tipo = 'venta' THEN COALESCE(m.precio_eur, 0) ELSE 0 END) AS ingreso_total,
                   ROUND(SUM(CASE WHEN m.tipo = 'venta' AND m.precio_eur > 0 THEN m.precio_eur ELSE 0 END)
                       / NULLIF(SUM(CASE WHEN m.tipo = 'venta' AND m.precio_eur > 0 THEN m.num_animales ELSE 0 END), 0), 2) AS precio_medio_eur,
                   ROUND(SUM(CASE WHEN m.tipo = 'venta' AND m.peso_canal_kg > 0 THEN m.peso_canal_kg ELSE 0 END)
                       / NULLIF(SUM(CASE WHEN m.tipo = 'venta' AND m.peso_canal_kg > 0 THEN m.num_animales ELSE 0 END), 0), 2) AS peso_medio_venta_kg,
                   -- Resumen bajas
                   SUM(CASE WHEN m.tipo = 'baja' THEN m.num_animales ELSE 0 END) AS total_bajas
            FROM lotes l
            JOIN tipos_animal ta ON l.tipo_animal_id = ta.id
            LEFT JOIN naves n    ON l.nave_id    = n.id
            LEFT JOIN granjas g  ON n.granja_id  = g.id
            LEFT JOIN granjas g2 ON l.granja_id  = g2.id
            LEFT JOIN razas_porcino r ON l.raza_id = r.id
            LEFT JOIN movimientos m ON m.lote_origen_id = l.id
            WHERE l.estado = 'cerrado'
              AND (g.organizacion_id = :uid OR g2.organizacion_id = :uid2)
            GROUP BY l.id
            ORDER BY COALESCE(l.fecha_cierre, l.fecha_entrada) DESC
        ");
        $stmt->execute(['uid' => \App\Core\OrgContext::id(), 'uid2' => \App\Core\OrgContext::id()]);
        return $stmt->fetchAll();
    }

    public function historicoDetalle(int $id, int $userId): ?array
    {
        // Datos del lote
        $stmt = $this->db->prepare("
            SELECT l.*,
                   ta.nombre  AS tipo_animal_nombre,
                   n.nombre   AS nave_nombre,
                   COALESCE(g.nombre, g2.nombre) AS granja_nombre,
                   r.nombre   AS raza_nombre,
                   SUM(CASE WHEN m.tipo = 'venta' THEN m.num_animales ELSE 0 END) AS total_vendidos,
                   SUM(CASE WHEN m.tipo = 'venta' THEN COALESCE(m.precio_eur, 0) ELSE 0 END) AS ingreso_total,
                   ROUND(SUM(CASE WHEN m.tipo = 'venta' AND m.precio_eur > 0 THEN m.precio_eur ELSE 0 END)
                       / NULLIF(SUM(CASE WHEN m.tipo = 'venta' AND m.precio_eur > 0 THEN m.num_animales ELSE 0 END), 0), 2) AS precio_medio_eur,
                   ROUND(SUM(CASE WHEN m.tipo = 'venta' AND m.peso_canal_kg > 0 THEN m.peso_canal_kg ELSE 0 END)
                       / NULLIF(SUM(CASE WHEN m.tipo = 'venta' AND m.peso_canal_kg > 0 THEN m.num_animales ELSE 0 END), 0), 2) AS peso_medio_venta_kg,
                   SUM(CASE WHEN m.tipo = 'baja'  THEN m.num_animales ELSE 0 END) AS total_bajas
            FROM lotes l
            JOIN tipos_animal ta ON l.tipo_animal_id = ta.id
            LEFT JOIN naves n    ON l.nave_id    = n.id
            LEFT JOIN granjas g  ON n.granja_id  = g.id
            LEFT JOIN granjas g2 ON l.granja_id  = g2.id
            LEFT JOIN razas_porcino r ON l.raza_id = r.id
            LEFT JOIN movimientos m ON m.lote_origen_id = l.id
            WHERE l.id = :id AND (g.organizacion_id = :uid OR g2.organizacion_id = :uid2)
            GROUP BY l.id
        ");
        $stmt->execute(['id' => $id, 'uid' => \App\Core\OrgContext::id(), 'uid2' => \App\Core\OrgContext::id()]);
        $lote = $stmt->fetch();
        if (!$lote) return null;

        // Timeline: movimientos
        $stmt2 = $this->db->prepare("
            SELECT m.*, u.nombre AS usuario_nombre,
                   cd.nombre AS cuadra_destino_nombre,
                   nd.nombre AS nave_destino_nombre
            FROM movimientos m
            LEFT JOIN usuarios u ON m.usuario_id = u.id
            LEFT JOIN cuadras cd ON m.cuadra_destino_id = cd.id
            LEFT JOIN naves nd   ON cd.nave_id = nd.id
            WHERE m.lote_origen_id = :id
            ORDER BY m.fecha DESC, m.id DESC
        ");
        $stmt2->execute(['id' => $id]);
        $lote['movimientos'] = $stmt2->fetchAll();

        // Timeline: pesajes
        $stmt3 = $this->db->prepare("
            SELECT p.*, u.nombre AS usuario_nombre
            FROM pesajes p
            LEFT JOIN usuarios u ON p.usuario_id = u.id
            WHERE p.lote_id = :id
            ORDER BY p.fecha DESC
        ");
        $stmt3->execute(['id' => $id]);
        $lote['pesajes'] = $stmt3->fetchAll();

        return $lote;
    }

    public function tiposAnimal(): array
    {
        return $this->db->query("SELECT id, nombre, especie FROM tipos_animal ORDER BY especie, nombre")->fetchAll();
    }

    /**
     * Devuelve el tipo_animal_id más apropiado según especie y tipo de producción de la granja
     */
    public function tipoAnimalParaGranja(string $especie, ?string $tipoProduccion): ?int
    {
        // Mapa de tipo_produccion → palabras clave en nombre del tipo animal
        $mapa = [
            'Cebo'         => ['cebo', 'engorde'],
            'Maternidad'   => ['reproductora', 'maternidad', 'madre'],
            'Recría'       => ['lechón', 'recría', 'destete'],
            'Ciclo cerrado'=> ['engorde', 'cebo'],
            'Mixta'        => ['engorde', 'cebo'],
        ];

        $palabras = $mapa[$tipoProduccion ?? ''] ?? [];

        // Buscar por palabras clave si hay tipo de producción
        if ($palabras) {
            $stmt = $this->db->prepare("SELECT id, nombre FROM tipos_animal WHERE especie = :especie ORDER BY nombre");
            $stmt->execute(['especie' => $especie]);
            $tipos = $stmt->fetchAll();
            foreach ($tipos as $t) {
                foreach ($palabras as $palabra) {
                    if (stripos($t['nombre'], $palabra) !== false) {
                        return (int) $t['id'];
                    }
                }
            }
        }

        // Fallback: primer tipo de esa especie
        $stmt = $this->db->prepare("SELECT id FROM tipos_animal WHERE especie = :especie ORDER BY id LIMIT 1");
        $stmt->execute(['especie' => $especie]);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    }
}