<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class RazaPorcino
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function allParaUsuario(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM razas_porcino
            WHERE (usuario_id IS NULL OR usuario_id = :uid) AND activa = 1
            ORDER BY usuario_id IS NULL DESC, nombre
        ");
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }

    public function allAdmin(): array
    {
        $stmt = $this->db->query("
            SELECT r.*, u.nombre AS creador
            FROM razas_porcino r
            LEFT JOIN usuarios u ON r.usuario_id = u.id
            WHERE r.activa = 1
            ORDER BY r.usuario_id IS NULL DESC, r.nombre
        ");
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM razas_porcino WHERE id = :id AND activa = 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO razas_porcino (usuario_id, nombre, porcentaje, identificador)
            VALUES (:usuario_id, :nombre, :porcentaje, :identificador)
        ");
        $stmt->execute($data);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE razas_porcino
            SET nombre = :nombre, porcentaje = :porcentaje, identificador = :identificador
            WHERE id = :id
        ");
        $data['id'] = $id;
        return $stmt->execute($data);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE razas_porcino SET activa = 0 WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function sufijoCodigo(int $razaId): ?string
    {
        $stmt = $this->db->prepare("SELECT identificador FROM razas_porcino WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $razaId]);
        $val = $stmt->fetchColumn();
        return ($val && trim($val) !== '') ? strtoupper(trim($val)) : null;
    }
}