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
                   COALESCE(g.nombre, g2.nombre) AS granja_nombre,
                   DATEDIFF(CURDATE(), l.fecha_entrada) AS dias_en_granja,
                   CEIL(DATEDIFF(CURDATE(), l.fecha_nacimiento) / 7) AS semana_actual,
                   (SELECT p.peso_medio_kg FROM pesajes p WHERE p.lote_id = l.id ORDER BY p.fecha DESC LIMIT 1) AS ultimo_peso,
                   r.nombre AS raza_nombre,
                   r.identificador AS raza_identificador,
                   tcl.peso_kg AS peso_tabla,
                   tcl.coste_eur AS coste_tabla,
                   tcl.consumo_acumulado_g AS consumo_tabla
            FROM lotes l
            JOIN tipos_animal ta ON l.tipo_animal_id = ta.id
            LEFT JOIN naves n   ON l.nave_id    = n.id
            LEFT JOIN granjas g ON n.granja_id  = g.id
            LEFT JOIN granjas g2 ON l.granja_id = g2.id
            LEFT JOIN razas_porcino r ON l.raza_id = r.id
            LEFT JOIN tabla_raza tr ON tr.raza_id = r.id
            LEFT JOIN tablas_crecimiento tc ON tc.id = tr.tabla_id AND tc.activa = 1
            LEFT JOIN tablas_crecimiento_lineas tcl
                ON tcl.tabla_id = tc.id
                AND tcl.semana = FLOOR(DATEDIFF(CURDATE(), l.fecha_nacimiento) / 7)
            WHERE (g.usuario_id = :uid OR g2.usuario_id = :uid2)
            AND l.estado = 'activo'
            ORDER BY l.estado, l.fecha_nacimiento ASC
        ");
        $stmt->execute(['uid' => $userId, 'uid2' => $userId]);
        return $stmt->fetchAll();
    }

    public function find(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT l.*, ta.nombre AS tipo_animal_nombre,
                   n.nombre AS nave_nombre,
                   COALESCE(g.nombre, g2.nombre) AS granja_nombre,
                   COALESCE(g.id, g2.id) AS granja_id
            FROM lotes l
            JOIN tipos_animal ta ON l.tipo_animal_id = ta.id
            LEFT JOIN naves n   ON l.nave_id    = n.id
            LEFT JOIN granjas g ON n.granja_id  = g.id
            LEFT JOIN granjas g2 ON l.granja_id = g2.id
            WHERE l.id = :id AND (g.usuario_id = :uid OR g2.usuario_id = :uid2 OR (l.nave_id IS NULL AND l.granja_id IS NULL))
        ");
        $stmt->execute(['id' => $id, 'uid' => $userId, 'uid2' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Genera código tipo L 26/17 o L 26/17 IB
     * sufijo = 'IB', 'DU', etc. según la raza
     */
    public static function generarCodigo(string $fechaNacimiento, ?string $sufijo = null): string
    {
        $dt   = new \DateTime($fechaNacimiento);
        $year = $dt->format('y');
        $week = $dt->format('W');
        $base = "L {$week}/{$year}";
        return $sufijo ? "{$base} {$sufijo}" : $base;
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
            INSERT INTO lotes (nave_id, granja_id, tipo_animal_id, raza_id, codigo, num_animales, peso_entrada_kg, fecha_entrada, fecha_nacimiento, observaciones)
            VALUES (:nave_id, :granja_id, :tipo_animal_id, :raza_id, :codigo, :num_animales, :peso_entrada_kg, :fecha_entrada, :fecha_nacimiento, :observaciones)
        ");
        $stmt->execute($data);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, int $userId, array $data): bool
    {
        $setCodigo = !empty($data['codigo']) ? 'l.codigo = :codigo,' : '';
        $stmt = $this->db->prepare("
            UPDATE lotes l
            LEFT JOIN naves n ON l.nave_id = n.id
            LEFT JOIN granjas g ON n.granja_id = g.id
            SET {$setCodigo}
                l.nave_id          = :nave_id,
                l.granja_id        = :granja_id,
                l.tipo_animal_id   = :tipo_animal_id,
                l.raza_id          = :raza_id,
                l.num_animales     = :num_animales,
                l.peso_entrada_kg  = :peso_entrada_kg,
                l.fecha_entrada    = :fecha_entrada,
                l.fecha_nacimiento = :fecha_nacimiento,
                l.observaciones    = :observaciones
            WHERE l.id = :id AND (g.usuario_id = :usuario_id OR l.nave_id IS NULL OR l.granja_id IS NOT NULL)
        ");
        if (empty($data['codigo'])) unset($data['codigo']);
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

    /**
     * Devuelve el tipo_animal_id más apropiado según especie y tipo de producción de la granja
     */
    public function tipoAnimalParaGranja(string $especie, ?string $tipoProduccion): ?int
    {
        // Mapa de tipo_produccion → palabras clave en nombre del tipo animal
        $mapa = [
            'Cebo'         => ['cebo', 'engorde'],
            'Maternidad'   => ['reproductora', 'maternidad', 'madre'],
            'Recría'       => ['lechón', 'recría', 'destete'],
            'Ciclo cerrado'=> ['engorde', 'cebo'],
            'Mixta'        => ['engorde', 'cebo'],
        ];

        $palabras = $mapa[$tipoProduccion ?? ''] ?? [];

        // Buscar por palabras clave si hay tipo de producción
        if ($palabras) {
            $stmt = $this->db->prepare("SELECT id, nombre FROM tipos_animal WHERE especie = :especie ORDER BY nombre");
            $stmt->execute(['especie' => $especie]);
            $tipos = $stmt->fetchAll();
            foreach ($tipos as $t) {
                foreach ($palabras as $palabra) {
                    if (stripos($t['nombre'], $palabra) !== false) {
                        return (int) $t['id'];
                    }
                }
            }
        }

        // Fallback: primer tipo de esa especie
        $stmt = $this->db->prepare("SELECT id FROM tipos_animal WHERE especie = :especie ORDER BY id LIMIT 1");
        $stmt->execute(['especie' => $especie]);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    }
}