<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class EstadoAnimal
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function all(): array
    {
        return $this->db->query("SELECT * FROM estados_animal ORDER BY nombre")->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM estados_animal WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function create(string $nombre, string $codigo, ?float $pesoMinKg): int
    {
        $stmt = $this->db->prepare("INSERT INTO estados_animal (nombre, codigo, peso_min_kg) VALUES (:nombre, :codigo, :peso_min_kg)");
        $stmt->execute(['nombre' => $nombre, 'codigo' => strtolower($codigo), 'peso_min_kg' => $pesoMinKg]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, string $nombre, string $codigo, ?float $pesoMinKg): bool
    {
        $stmt = $this->db->prepare("UPDATE estados_animal SET nombre = :nombre, codigo = :codigo, peso_min_kg = :peso_min_kg WHERE id = :id");
        return $stmt->execute(['nombre' => $nombre, 'codigo' => strtolower($codigo), 'peso_min_kg' => $pesoMinKg, 'id' => $id]);
    }

    public function toggleActivo(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE estados_animal SET activo = NOT activo WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}