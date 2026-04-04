<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class Silo
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function allByUsuario(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT s.*, g.nombre AS granja_nombre,
                   ROUND((s.stock_actual_kg / NULLIF(s.capacidad_kg, 0)) * 100) AS pct_stock,
                   GROUP_CONCAT(n.nombre ORDER BY n.nombre SEPARATOR ', ') AS naves_abastecidas
            FROM silos s
            JOIN granjas g ON s.granja_id = g.id
            LEFT JOIN silo_nave sn ON sn.silo_id = s.id
            LEFT JOIN naves n ON sn.nave_id = n.id AND n.activa = 1
            WHERE g.usuario_id = :uid AND s.activo = 1
            GROUP BY s.id
            ORDER BY g.nombre, s.nombre
        ");
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }

    public function find(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT s.*, g.nombre AS granja_nombre,
                   ROUND((s.stock_actual_kg / NULLIF(s.capacidad_kg, 0)) * 100) AS pct_stock,
                   GROUP_CONCAT(n.nombre ORDER BY n.nombre SEPARATOR ', ') AS naves_abastecidas
            FROM silos s
            JOIN granjas g ON s.granja_id = g.id
            LEFT JOIN silo_nave sn ON sn.silo_id = s.id
            LEFT JOIN naves n ON sn.nave_id = n.id AND n.activa = 1
            WHERE s.id = :id AND g.usuario_id = :uid
            GROUP BY s.id
        ");
        $stmt->execute(['id' => $id, 'uid' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function navesAsignadas(int $siloId): array
    {
        $stmt = $this->db->prepare("
            SELECT nave_id FROM silo_nave WHERE silo_id = :id
        ");
        $stmt->execute(['id' => $siloId]);
        return array_column($stmt->fetchAll(), 'nave_id');
    }

    public function create(array $data, array $naveIds): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO silos (granja_id, nombre, capacidad_kg, stock_actual_kg, stock_minimo_kg, descripcion)
            VALUES (:granja_id, :nombre, :capacidad_kg, :stock_actual_kg, :stock_minimo_kg, :descripcion)
        ");
        $stmt->execute($data);
        $id = (int) $this->db->lastInsertId();
        $this->syncNaves($id, $naveIds);
        return $id;
    }

    public function update(int $id, int $userId, array $data, array $naveIds): bool
    {
        $stmt = $this->db->prepare("
            UPDATE silos s
            JOIN granjas g ON s.granja_id = g.id
            SET s.nombre = :nombre,
                s.capacidad_kg = :capacidad_kg,
                s.stock_actual_kg = :stock_actual_kg,
                s.stock_minimo_kg = :stock_minimo_kg,
                s.descripcion = :descripcion
            WHERE s.id = :id AND g.usuario_id = :usuario_id
        ");
        $data['id'] = $id;
        $data['usuario_id'] = $userId;
        $ok = $stmt->execute($data);
        $this->syncNaves($id, $naveIds);
        return $ok;
    }

    public function delete(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE silos s
            JOIN granjas g ON s.granja_id = g.id
            SET s.activo = 0
            WHERE s.id = :id AND g.usuario_id = :uid
        ");
        return $stmt->execute(['id' => $id, 'uid' => $userId]);
    }

    // ── Recargas ─────────────────────────────────────────────────

    public function recargas(int $siloId): array
    {
        $stmt = $this->db->prepare("
            SELECT r.*, u.nombre AS usuario_nombre
            FROM silo_recargas r
            JOIN usuarios u ON r.usuario_id = u.id
            WHERE r.silo_id = :id
            ORDER BY r.fecha DESC, r.id DESC
        ");
        $stmt->execute(['id' => $siloId]);
        return $stmt->fetchAll();
    }

    public function addRecarga(int $siloId, float $cantidadKg, string $fecha, ?string $proveedor, ?string $obs, int $userId): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO silo_recargas (silo_id, fecha, cantidad_kg, proveedor, observaciones, usuario_id)
            VALUES (:silo_id, :fecha, :cantidad_kg, :proveedor, :observaciones, :usuario_id)
        ");
        $stmt->execute([
            'silo_id'      => $siloId,
            'fecha'        => $fecha,
            'cantidad_kg'  => $cantidadKg,
            'proveedor'    => $proveedor,
            'observaciones'=> $obs,
            'usuario_id'   => $userId,
        ]);
        $id = (int) $this->db->lastInsertId();

        // Actualizar stock
        $this->db->prepare("UPDATE silos SET stock_actual_kg = stock_actual_kg + :kg WHERE id = :id")
            ->execute(['kg' => $cantidadKg, 'id' => $siloId]);

        return $id;
    }

    public function deleteRecarga(int $recargaId, int $siloId): void
    {
        // Recuperar cantidad antes de borrar
        $stmt = $this->db->prepare("SELECT cantidad_kg FROM silo_recargas WHERE id = :id AND silo_id = :silo_id");
        $stmt->execute(['id' => $recargaId, 'silo_id' => $siloId]);
        $r = $stmt->fetch();
        if (!$r) return;

        $this->db->prepare("DELETE FROM silo_recargas WHERE id = :id")->execute(['id' => $recargaId]);
        $this->db->prepare("UPDATE silos SET stock_actual_kg = GREATEST(0, stock_actual_kg - :kg) WHERE id = :id")
            ->execute(['kg' => $r['cantidad_kg'], 'id' => $siloId]);
    }

    /**
     * Calcula consumo diario (kg/día) de los lotes activos en las naves de este silo,
     * y estima cuántos días quedan hasta llegar al stock mínimo.
     * Devuelve ['consumo_diario_kg', 'dias_hasta_minimo', 'fecha_minimo', 'lotes']
     */
    public function proyeccionConsumo(int $siloId): array
    {
        // Lotes activos en naves abastecidas por este silo
        $stmt = $this->db->prepare("
            SELECT l.id, l.codigo, l.num_animales, l.fecha_nacimiento,
                   n.nombre AS nave_nombre,
                   CEIL(DATEDIFF(CURDATE(), l.fecha_nacimiento) / 7) AS semana_actual,
                   tcl_curr.consumo_acumulado_g AS consumo_acumulado_actual,
                   tcl_prev.consumo_acumulado_g AS consumo_acumulado_anterior
            FROM silos s
            JOIN silo_nave sn ON sn.silo_id = s.id
            JOIN naves n ON sn.nave_id = n.id
            JOIN lotes l ON l.nave_id = n.id AND l.estado = 'activo' AND l.fecha_nacimiento IS NOT NULL
            LEFT JOIN tabla_raza tr ON tr.raza_id = l.raza_id
            LEFT JOIN tablas_crecimiento tc ON tc.id = tr.tabla_id AND tc.activa = 1
            LEFT JOIN tablas_crecimiento_lineas tcl_curr
                ON tcl_curr.tabla_id = tc.id
                AND tcl_curr.semana  = CEIL(DATEDIFF(CURDATE(), l.fecha_nacimiento) / 7)
            LEFT JOIN tablas_crecimiento_lineas tcl_prev
                ON tcl_prev.tabla_id = tc.id
                AND tcl_prev.semana  = CEIL(DATEDIFF(CURDATE(), l.fecha_nacimiento) / 7) - 1
            WHERE s.id = :silo_id
        ");
        $stmt->execute(['silo_id' => $siloId]);
        $lotes = $stmt->fetchAll();

        $totalDiarioKg = 0.0;
        foreach ($lotes as &$l) {
            $consumoSemanalG = max(0,
                (float)($l['consumo_acumulado_actual'] ?? 0) - (float)($l['consumo_acumulado_anterior'] ?? 0)
            );
            $consumoDiarioKg = ($consumoSemanalG / 7 / 1000) * (int)$l['num_animales'];
            $l['consumo_diario_kg'] = round($consumoDiarioKg, 2);
            $totalDiarioKg += $consumoDiarioKg;
        }

        // Stock disponible hasta mínimo
        $siloData = $this->db->prepare("SELECT stock_actual_kg, stock_minimo_kg FROM silos WHERE id = :id");
        $siloData->execute(['id' => $siloId]);
        $s = $siloData->fetch();

        $margen = max(0, (float)$s['stock_actual_kg'] - (float)$s['stock_minimo_kg']);
        $diasHastaMinimo = $totalDiarioKg > 0 ? (int) floor($margen / $totalDiarioKg) : null;
        $fechaMinimo     = $diasHastaMinimo !== null
            ? date('Y-m-d', strtotime("+{$diasHastaMinimo} days"))
            : null;

        return [
            'consumo_diario_kg'  => round($totalDiarioKg, 2),
            'dias_hasta_minimo'  => $diasHastaMinimo,
            'fecha_minimo'       => $fechaMinimo,
            'lotes'              => $lotes,
        ];
    }

    private function syncNaves(int $siloId, array $naveIds): void
    {
        $this->db->prepare("DELETE FROM silo_nave WHERE silo_id = :id")->execute(['id' => $siloId]);
        if (empty($naveIds)) return;
        $stmt = $this->db->prepare("INSERT INTO silo_nave (silo_id, nave_id) VALUES (:silo_id, :nave_id)");
        foreach ($naveIds as $naveId) {
            $stmt->execute(['silo_id' => $siloId, 'nave_id' => (int)$naveId]);
        }
    }
}