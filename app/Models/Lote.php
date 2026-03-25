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

    public function allByUsuario(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT l.*,
                   ta.nombre  AS tipo_animal_nombre,
                   ta.especie AS especie,
                   n.nombre   AS nave_nombre,
                   g.nombre   AS granja_nombre,
                   DATEDIFF(CURDATE(), l.fecha_entrada) AS dias_en_granja,
                   (SELECT p.peso_medio_kg FROM pesajes p WHERE p.lote_id = l.id ORDER BY p.fecha DESC LIMIT 1) AS ultimo_peso
            FROM lotes l
            JOIN tipos_animal ta ON l.tipo_animal_id = ta.id
            LEFT JOIN naves n ON l.nave_id = n.id
            LEFT JOIN granjas g ON n.granja_id = g.id
            WHERE (g.usuario_id = :uid OR l.nave_id IS NULL)
            ORDER BY l.estado, l.fecha_entrada DESC
        ");
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }

    public function find(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT l.*, ta.nombre AS tipo_animal_nombre, n.nombre AS nave_nombre, g.nombre AS granja_nombre
            FROM lotes l
            JOIN tipos_animal ta ON l.tipo_animal_id = ta.id
            LEFT JOIN naves n ON l.nave_id = n.id
            LEFT JOIN granjas g ON n.granja_id = g.id
            WHERE l.id = :id AND (g.usuario_id = :uid OR l.nave_id IS NULL)
        ");
        $stmt->execute(['id' => $id, 'uid' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Genera código tipo L 26/17 a partir de fecha de nacimiento
     * L YY/WW donde YY = año 2 dígitos, WW = semana ISO
     */
    public static function generarCodigo(string $fechaNacimiento): string
    {
        $dt   = new \DateTime($fechaNacimiento);
        $year = $dt->format('y');      // 2 dígitos
        $week = $dt->format('W');      // semana ISO con cero inicial
        return "L {$year}/{$week}";
    }

    public function codigoExiste(string $codigo, ?int $exceptId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM lotes WHERE codigo = :codigo";
        $params = ['codigo' => $codigo];
        if ($exceptId) {
            $sql .= " AND id != :id";
            $params['id'] = $exceptId;
        }
        return (int) $this->db->prepare($sql)->execute($params) && 
               (int) $this->db->query("SELECT FOUND_ROWS()")->fetchColumn() >= 0
               ? (bool) $this->db->prepare($sql)->execute($params) 
               : false;
        // Versión simple:
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
        $stmt = $this->db->prepare("
            INSERT INTO lotes (nave_id, tipo_animal_id, codigo, num_animales, peso_entrada_kg, fecha_entrada, observaciones)
            VALUES (:nave_id, :tipo_animal_id, :codigo, :num_animales, :peso_entrada_kg, :fecha_entrada, :observaciones)
        ");
        $stmt->execute($data);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, int $userId, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE lotes l
            LEFT JOIN naves n ON l.nave_id = n.id
            LEFT JOIN granjas g ON n.granja_id = g.id
            SET l.nave_id         = :nave_id,
                l.tipo_animal_id  = :tipo_animal_id,
                l.num_animales    = :num_animales,
                l.peso_entrada_kg = :peso_entrada_kg,
                l.fecha_entrada   = :fecha_entrada,
                l.observaciones   = :observaciones
            WHERE l.id = :id AND (g.usuario_id = :usuario_id OR l.nave_id IS NULL)
        ");
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
            WHERE l.id = :id AND (g.usuario_id = :uid OR l.nave_id IS NULL)
        ");
        return $stmt->execute(['id' => $id, 'uid' => $userId]);
    }

    public function tiposAnimal(): array
    {
        return $this->db->query("SELECT id, nombre, especie FROM tipos_animal ORDER BY especie, nombre")->fetchAll();
    }
}
