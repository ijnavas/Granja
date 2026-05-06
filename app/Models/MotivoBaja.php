<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class MotivoBaja
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function all(bool $soloActivos = true): array
    {
        $sql = "SELECT * FROM motivos_baja";
        if ($soloActivos) $sql .= " WHERE activo = 1";
        $sql .= " ORDER BY orden, nombre";
        return $this->db->query($sql)->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM motivos_baja WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function findByCodigo(string $codigo): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM motivos_baja WHERE codigo = :c");
        $stmt->execute(['c' => $codigo]);
        return $stmt->fetch() ?: null;
    }

    public function create(string $codigo, string $nombre, int $orden = 0, bool $activo = true): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO motivos_baja (codigo, nombre, orden, activo) VALUES (:c, :n, :o, :a)
        ");
        $stmt->execute(['c' => $codigo, 'n' => $nombre, 'o' => $orden, 'a' => $activo ? 1 : 0]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, string $codigo, string $nombre, int $orden, bool $activo): void
    {
        $stmt = $this->db->prepare("
            UPDATE motivos_baja SET codigo = :c, nombre = :n, orden = :o, activo = :a WHERE id = :id
        ");
        $stmt->execute(['id' => $id, 'c' => $codigo, 'n' => $nombre, 'o' => $orden, 'a' => $activo ? 1 : 0]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM motivos_baja WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}
