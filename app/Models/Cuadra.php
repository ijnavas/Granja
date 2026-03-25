<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class Cuadra
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function allByNave(int $naveId): array
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

    public function allByUsuario(int $userId, ?int $naveId = null): array
    {
        $sql = "
            SELECT c.*,
                   n.nombre AS nave_nombre,
                   g.nombre AS granja_nombre,
                   COALESCE(SUM(cl.num_animales), 0) AS ocupacion_actual,
                   COUNT(DISTINCT cl.lote_id)         AS num_lotes
            FROM cuadras c
            JOIN naves n   ON c.nave_id   = n.id
            JOIN granjas g ON n.granja_id = g.id
            LEFT JOIN cuadra_lote cl ON cl.cuadra_id = c.id AND cl.activo = 1
            WHERE g.usuario_id = :uid AND c.activa = 1
        ";
        $params = ['uid' => $userId];

        if ($naveId) {
            $sql .= " AND c.nave_id = :nave_id";
            $params['nave_id'] = $naveId;
        }

        $sql .= " GROUP BY c.id ORDER BY g.nombre, n.nombre, c.nombre";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function find(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT c.*, n.nombre AS nave_nombre, g.nombre AS granja_nombre
            FROM cuadras c
            JOIN naves n   ON c.nave_id   = n.id
            JOIN granjas g ON n.granja_id = g.id
            WHERE c.id = :id AND g.usuario_id = :uid
        ");
        $stmt->execute(['id' => $id, 'uid' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO cuadras (nave_id, nombre, capacidad_maxima, ancho_m, alto_m, largo_m, descripcion)
            VALUES (:nave_id, :nombre, :capacidad_maxima, :ancho_m, :alto_m, :largo_m, :descripcion)
        ");
        $stmt->execute($data);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, int $userId, array $data): bool
    {
        $stmt = $this->db->prepare("
            UPDATE cuadras c
            JOIN naves n   ON c.nave_id   = n.id
            JOIN granjas g ON n.granja_id = g.id
            SET c.nave_id          = :nave_id,
                c.nombre           = :nombre,
                c.capacidad_maxima = :capacidad_maxima,
                c.ancho_m          = :ancho_m,
                c.alto_m           = :alto_m,
                c.largo_m          = :largo_m,
                c.descripcion      = :descripcion
            WHERE c.id = :id AND g.usuario_id = :usuario_id
        ");
        $data['id'] = $id;
        $data['usuario_id'] = $userId;
        return $stmt->execute($data);
    }

    public function delete(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE cuadras c
            JOIN naves n   ON c.nave_id   = n.id
            JOIN granjas g ON n.granja_id = g.id
            SET c.activa = 0
            WHERE c.id = :id AND g.usuario_id = :uid
        ");
        return $stmt->execute(['id' => $id, 'uid' => $userId]);
    }

    // ── Lotes en una cuadra ──────────────────────────────────────
    public function lotesEnCuadra(int $cuadraId): array
    {
        $stmt = $this->db->prepare("
            SELECT cl.*, l.codigo, l.num_animales AS total_lote,
                   ta.nombre AS tipo_animal, ta.especie
            FROM cuadra_lote cl
            JOIN lotes l       ON cl.lote_id = l.id
            JOIN tipos_animal ta ON l.tipo_animal_id = ta.id
            WHERE cl.cuadra_id = :cuadra_id AND cl.activo = 1
            ORDER BY cl.fecha_entrada DESC
        ");
        $stmt->execute(['cuadra_id' => $cuadraId]);
        return $stmt->fetchAll();
    }

    // ── Cuadras donde está un lote ───────────────────────────────
    public function cuadrasDelLote(int $loteId): array
    {
        $stmt = $this->db->prepare("
            SELECT cl.*, c.nombre AS cuadra_nombre,
                   n.nombre AS nave_nombre, g.nombre AS granja_nombre
            FROM cuadra_lote cl
            JOIN cuadras c ON cl.cuadra_id = c.id
            JOIN naves n   ON c.nave_id    = n.id
            JOIN granjas g ON n.granja_id  = g.id
            WHERE cl.lote_id = :lote_id AND cl.activo = 1
            ORDER BY c.nombre
        ");
        $stmt->execute(['lote_id' => $loteId]);
        return $stmt->fetchAll();
    }

    // ── Asignar animales de un lote a una cuadra ─────────────────
    public function asignarLote(int $cuadraId, int $loteId, int $numAnimales, string $fechaEntrada, ?string $obs = null): int
    {
        // Si ya existe una asignación activa, actualiza
        $stmt = $this->db->prepare("
            SELECT id FROM cuadra_lote
            WHERE cuadra_id = :cuadra_id AND lote_id = :lote_id AND activo = 1
            LIMIT 1
        ");
        $stmt->execute(['cuadra_id' => $cuadraId, 'lote_id' => $loteId]);
        $existing = $stmt->fetchColumn();

        if ($existing) {
            $this->db->prepare("
                UPDATE cuadra_lote SET num_animales = :n, observaciones = :obs WHERE id = :id
            ")->execute(['n' => $numAnimales, 'obs' => $obs, 'id' => $existing]);
            return (int) $existing;
        }

        $stmt = $this->db->prepare("
            INSERT INTO cuadra_lote (cuadra_id, lote_id, num_animales, fecha_entrada, observaciones)
            VALUES (:cuadra_id, :lote_id, :num_animales, :fecha_entrada, :observaciones)
        ");
        $stmt->execute([
            'cuadra_id'    => $cuadraId,
            'lote_id'      => $loteId,
            'num_animales' => $numAnimales,
            'fecha_entrada'=> $fechaEntrada,
            'observaciones'=> $obs,
        ]);
        return (int) $this->db->lastInsertId();
    }

    // ── Retirar lote de una cuadra ───────────────────────────────
    public function retirarLote(int $cuadraLoteId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE cuadra_lote SET activo = 0, fecha_salida = CURDATE() WHERE id = :id
        ");
        return $stmt->execute(['id' => $cuadraLoteId]);
    }

    public function selectOptions(int $userId, ?int $naveId = null): array
    {
        $sql = "
            SELECT c.id, c.nombre, n.nombre AS nave_nombre, g.nombre AS granja_nombre
            FROM cuadras c
            JOIN naves n   ON c.nave_id   = n.id
            JOIN granjas g ON n.granja_id = g.id
            WHERE g.usuario_id = :uid AND c.activa = 1
        ";
        $params = ['uid' => $userId];
        if ($naveId) {
            $sql .= " AND c.nave_id = :nave_id";
            $params['nave_id'] = $naveId;
        }
        $sql .= " ORDER BY g.nombre, n.nombre, c.nombre";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
