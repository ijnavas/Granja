<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class Nave
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function allByUsuario(int $userId, ?int $granjaId = null): array
    {
        $sql = "
            SELECT n.*, g.nombre AS granja_nombre,
                   COALESCE(SUM(l.num_animales), 0) AS ocupacion_actual
            FROM naves n
            JOIN granjas g ON n.granja_id = g.id
            LEFT JOIN lotes l ON l.nave_id = n.id AND l.estado = 'activo'
            WHERE g.usuario_id = :uid AND n.activa = 1
        ";
        $params = ['uid' => $userId];

        if ($granjaId) {
            $sql .= " AND n.granja_id = :granja_id";
            $params['granja_id'] = $granjaId;
        }

        $sql .= " GROUP BY n.id ORDER BY g.nombre, n.nombre";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function find(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT n.*, g.nombre AS granja_nombre, g.id AS granja_id
            FROM naves n
            JOIN granjas g ON n.granja_id = g.id
            WHERE n.id = :id AND g.usuario_id = :uid
        ");
        $stmt->execute(['id' => $id, 'uid' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO naves (granja_id, nombre, especie, capacidad_maxima, ancho_m, alto_m, largo_m, descripcion)
            VALUES (:granja_id, :nombre, :especie, :capacidad_maxima, :ancho_m, :alto_m, :largo_m, :descripcion)
        ");
        $stmt->execute($data);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, int $userId, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE naves n
            JOIN granjas g ON n.granja_id = g.id
            SET n.nombre = :nombre,
                n.especie = :especie,
                n.capacidad_maxima = :capacidad_maxima,
                n.ancho_m = :ancho_m,
                n.alto_m = :alto_m,
                n.largo_m = :largo_m,
                n.descripcion = :descripcion
            WHERE n.id = :id AND g.usuario_id = :usuario_id
        ");
        $data['id'] = $id;
        $data['usuario_id'] = $userId;
        return $stmt->execute($data);
    }

    public function delete(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE naves n
            JOIN granjas g ON n.granja_id = g.id
            SET n.activa = 0
            WHERE n.id = :id AND g.usuario_id = :uid
        ");
        return $stmt->execute(['id' => $id, 'uid' => $userId]);
    }

    public function totales(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COALESCE(SUM(n.capacidad_maxima), 0)           AS capacidad_total,
                COALESCE(SUM(lotes_nave.ocupacion), 0)         AS ocupacion_total
            FROM naves n
            JOIN granjas g ON n.granja_id = g.id
            LEFT JOIN (
                SELECT nave_id, SUM(num_animales) AS ocupacion
                FROM lotes WHERE estado = 'activo' GROUP BY nave_id
            ) lotes_nave ON lotes_nave.nave_id = n.id
            WHERE g.usuario_id = :uid AND n.activa = 1
        ");
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetch() ?: ['capacidad_total' => 0, 'ocupacion_total' => 0];
    }

    public function cuadrasDeNave(int $naveId): array
    {
        $stmt = $this->db->prepare("
            SELECT c.*,
                   COALESCE(SUM(cl.num_animales), 0) AS ocupacion_actual,
                   COUNT(DISTINCT cl.lote_id)         AS num_lotes
            FROM cuadras c
            LEFT JOIN cuadra_lote cl ON cl.cuadra_id = c.id AND cl.activo = 1
            WHERE c.nave_id = :nave_id AND c.activa = 1
            GROUP BY c.id
            ORDER BY c.nombre
        ");
        $stmt->execute(['nave_id' => $naveId]);
        return $stmt->fetchAll();
    }

    public function selectOptions(int $userId, ?int $granjaId = null): array
    {
        $sql = "
            SELECT n.id, n.nombre, g.nombre AS granja_nombre
            FROM naves n
            JOIN granjas g ON n.granja_id = g.id
            WHERE g.usuario_id = :uid AND n.activa = 1
        ";
        $params = ['uid' => $userId];
        if ($granjaId) {
            $sql .= " AND n.granja_id = :granja_id";
            $params['granja_id'] = $granjaId;
        }
        $sql .= " ORDER BY g.nombre, n.nombre";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}