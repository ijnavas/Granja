<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class TipoMovimiento
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function all(bool $soloActivos = true): array
    {
        $sql = "SELECT * FROM tipos_movimiento";
        if ($soloActivos) $sql .= " WHERE activo = 1";
        $sql .= " ORDER BY orden, nombre";
        return $this->db->query($sql)->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM tipos_movimiento WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function findByCodigo(string $codigo): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM tipos_movimiento WHERE codigo = :c");
        $stmt->execute(['c' => $codigo]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Devuelve la categoría de un tipo (por código). Si no existe, asume 'salida'
     * como fallback seguro para que el efecto sea reversible.
     */
    public function categoriaDe(string $codigo): string
    {
        $t = $this->findByCodigo($codigo);
        return $t['categoria'] ?? 'salida';
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO tipos_movimiento (codigo, nombre, categoria, es_sistema, activo, orden, color)
            VALUES (:codigo, :nombre, :categoria, 0, :activo, :orden, :color)
        ");
        $stmt->execute([
            'codigo'    => $data['codigo'],
            'nombre'    => $data['nombre'],
            'categoria' => $data['categoria'],
            'activo'    => !empty($data['activo']) ? 1 : 0,
            'orden'     => (int)($data['orden'] ?? 0),
            'color'     => $data['color'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare("
            UPDATE tipos_movimiento SET
                codigo = :codigo, nombre = :nombre, categoria = :categoria,
                activo = :activo, orden = :orden, color = :color
            WHERE id = :id
        ");
        $stmt->execute([
            'id'        => $id,
            'codigo'    => $data['codigo'],
            'nombre'    => $data['nombre'],
            'categoria' => $data['categoria'],
            'activo'    => !empty($data['activo']) ? 1 : 0,
            'orden'     => (int)($data['orden'] ?? 0),
            'color'     => $data['color'] ?? null,
        ]);
    }

    public function delete(int $id): bool
    {
        // Tipos de sistema (es_sistema=1) no son borrables: actualmente solo
        // 'destete'. Bloqueamos el DELETE en la propia query como defensa.
        $stmt = $this->db->prepare("DELETE FROM tipos_movimiento WHERE id = :id AND es_sistema = 0");
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }
}
