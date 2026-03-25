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
            SELECT s.* FROM silos s
            JOIN granjas g ON s.granja_id = g.id
            WHERE s.id = :id AND g.usuario_id = :uid
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
