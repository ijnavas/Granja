<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class Granja
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function allByUsuario(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT g.*,
                   COUNT(DISTINCT n.id) AS num_naves,
                   COUNT(DISTINCT s.id) AS num_silos
            FROM granjas g
            LEFT JOIN naves n ON n.granja_id = g.id AND n.activa = 1
            LEFT JOIN silos s ON s.granja_id = g.id AND s.activo = 1
            WHERE g.usuario_id = :uid
            GROUP BY g.id
            ORDER BY g.nombre
        ");
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }

    public function find(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM granjas WHERE id = :id AND usuario_id = :uid
        ");
        $stmt->execute(['id' => $id, 'uid' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO granjas (usuario_id, nombre, codigo_rega, capacidad_max, especie, direccion, municipio, provincia, codigo_postal, tipo_produccion, latitud, longitud)
            VALUES (:usuario_id, :nombre, :codigo_rega, :capacidad_max, :especie, :direccion, :municipio, :provincia, :codigo_postal, :tipo_produccion, :latitud, :longitud)
        ");
        $stmt->execute($data);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, int $userId, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE granjas SET
                nombre          = :nombre,
                codigo_rega     = :codigo_rega,
                capacidad_max   = :capacidad_max,
                especie         = :especie,
                direccion       = :direccion,
                municipio       = :municipio,
                provincia       = :provincia,
                codigo_postal   = :codigo_postal,
                tipo_produccion = :tipo_produccion,
                latitud         = :latitud,
                longitud        = :longitud
            WHERE id = :id AND usuario_id = :usuario_id
        ");
        $data['id'] = $id;
        $data['usuario_id'] = $userId;
        return $stmt->execute($data);
    }

    public function delete(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE granjas SET activa = 0 WHERE id = :id AND usuario_id = :uid
        ");
        return $stmt->execute(['id' => $id, 'uid' => $userId]);
    }

    public function selectOptions(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, nombre, especie FROM granjas WHERE usuario_id = :uid AND activa = 1 ORDER BY nombre
        ");
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }
}
