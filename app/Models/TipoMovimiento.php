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

    /**
     * Asegura que los tipos fundamentales de sistema existen y están
     * activos. Idempotente; pensado para llamarse al entrar a páginas
     * que dependen de ellos (Configuración, formulario de movimiento)
     * para auto-instalarse sin migraciones manuales.
     */
    public function ensureSystemTipos(): void
    {
        $this->db->prepare("
            INSERT IGNORE INTO tipos_movimiento (codigo, nombre, categoria, es_sistema, activo, orden, color)
            VALUES ('traslado_cuadra', 'Traslado cuadra', 'traslado', 1, 1, 8, '#1d4ed8')
        ")->execute();
        $this->db->prepare("
            UPDATE tipos_movimiento
               SET es_sistema = 1, activo = 1, categoria = 'traslado'
             WHERE codigo = 'traslado_cuadra'
        ")->execute();
    }
}
