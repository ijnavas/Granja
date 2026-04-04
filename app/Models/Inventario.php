<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class Inventario
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function allByUsuario(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT i.*,
                   COUNT(il.id)            AS num_lineas,
                   SUM(il.num_animales)    AS total_animales,
                   SUM(il.valor_total_eur) AS valor_total
            FROM inventarios i
            LEFT JOIN inventario_lineas il ON il.inventario_id = i.id
            WHERE i.usuario_id = :uid
            GROUP BY i.id
            ORDER BY i.fecha DESC, i.created_at DESC
        ");
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }

    public function find(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM inventarios WHERE id = :id AND usuario_id = :uid
        ");
        $stmt->execute(['id' => $id, 'uid' => $userId]);
        return $stmt->fetch() ?: null;
    }

    public function lineas(int $inventarioId): array
    {
        $stmt = $this->db->prepare("
            SELECT il.*,
                   l.codigo        AS lote_codigo,
                   l.estado_animal AS lote_estado_animal,
                   c.nombre        AS cuadra_nombre,
                   n.nombre        AS nave_nombre,
                   g.nombre        AS granja_nombre
            FROM inventario_lineas il
            JOIN lotes l    ON il.lote_id   = l.id
            LEFT JOIN cuadras c ON il.cuadra_id = c.id
            LEFT JOIN naves n   ON il.nave_id   = n.id
            LEFT JOIN granjas g ON il.granja_id = g.id
            WHERE il.inventario_id = :id
            ORDER BY g.nombre, n.nombre, c.nombre, l.codigo
        ");
        $stmt->execute(['id' => $inventarioId]);
        return $stmt->fetchAll();
    }

    public function create(int $userId, string $fecha, ?string $nombre): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO inventarios (usuario_id, fecha, nombre) VALUES (:uid, :fecha, :nombre)
        ");
        $stmt->execute(['uid' => $userId, 'fecha' => $fecha, 'nombre' => $nombre]);
        return (int) $this->db->lastInsertId();
    }

    public function insertLinea(int $inventarioId, array $linea): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO inventario_lineas
                (inventario_id, lote_id, cuadra_id, nave_id, granja_id, estado_animal,
                 num_animales, peso_kg, peso_total_kg, coste_eur, valor_total_eur, semana_tabla)
            VALUES
                (:inventario_id, :lote_id, :cuadra_id, :nave_id, :granja_id, :estado_animal,
                 :num_animales, :peso_kg, :peso_total_kg, :coste_eur, :valor_total_eur, :semana_tabla)
        ");
        $stmt->execute(array_merge(['inventario_id' => $inventarioId], $linea));
    }

    public function delete(int $id): void
    {
        $this->db->prepare("DELETE FROM inventarios WHERE id = :id")->execute(['id' => $id]);
    }

    // Reconstruye el estado de los lotes a una fecha dada:
    // - Solo lotes creados antes o en esa fecha
    // - Ajusta num_animales revirtiendo movimientos posteriores a la fecha
    public function calcularLineas(int $userId, string $fecha): array
    {
        // 1. Obtener lotes base con tabla de crecimiento
        $stmt = $this->db->prepare("
            SELECT
                l.id            AS lote_id,
                l.codigo        AS lote_codigo,
                l.estado_animal,
                l.granja_id,
                l.nave_id,
                l.num_animales  AS num_actual,
                g.nombre        AS granja_nombre,
                n.nombre        AS nave_nombre,
                cl.cuadra_id,
                c.nombre        AS cuadra_nombre,
                cl.num_animales AS cuadra_num,
                CEIL(DATEDIFF(:fecha1, l.fecha_nacimiento) / 7) AS semana_actual,
                tcl.peso_kg     AS peso_tabla,
                tcl.coste_eur   AS coste_tabla
            FROM lotes l
            JOIN granjas g ON l.granja_id = g.id
            LEFT JOIN naves n   ON l.nave_id   = n.id
            LEFT JOIN cuadra_lote cl ON cl.lote_id = l.id AND cl.activo = 1 AND cl.num_animales > 0
            LEFT JOIN cuadras c  ON cl.cuadra_id = c.id
            LEFT JOIN tabla_raza tr ON tr.raza_id = l.raza_id
            LEFT JOIN tablas_crecimiento tc  ON tc.id = tr.tabla_id AND tc.activa = 1
            LEFT JOIN tablas_crecimiento_lineas tcl
                ON tcl.tabla_id = tc.id
                AND tcl.semana  = CEIL(DATEDIFF(:fecha2, l.fecha_nacimiento) / 7)
            WHERE g.usuario_id  = :uid
              AND l.fecha_nacimiento <= :fecha3
              AND l.fecha_nacimiento IS NOT NULL
            ORDER BY g.nombre, n.nombre, c.nombre, l.codigo
        ");
        $stmt->execute(['uid' => $userId, 'fecha1' => $fecha, 'fecha2' => $fecha, 'fecha3' => $fecha]);
        $rows = $stmt->fetchAll();

        // 2. Movimientos POSTERIORES a la fecha que alteraron el número de animales
        $stmtMov = $this->db->prepare("
            SELECT m.tipo, m.num_animales, m.lote_origen_id, m.lote_destino_id
            FROM movimientos m
            JOIN lotes l  ON m.lote_origen_id = l.id
            JOIN granjas g ON l.granja_id = g.id
            WHERE g.usuario_id = :uid
              AND m.fecha > :fecha
        ");
        $stmtMov->execute(['uid' => $userId, 'fecha' => $fecha]);

        // Mapa de ajuste por lote_id: suma a aplicar sobre num_animales
        $ajustes = [];
        foreach ($stmtMov->fetchAll() as $m) {
            $n    = (int) $m['num_animales'];
            $orig = (int) $m['lote_origen_id'];
            $dest = $m['lote_destino_id'] ? (int) $m['lote_destino_id'] : null;

            // Movimientos que REDUCEN el origen → revertir sumando
            if (in_array($m['tipo'], ['baja', 'venta', 'entrada_reposicion', 'entrada_madres'])) {
                $ajustes[$orig] = ($ajustes[$orig] ?? 0) + $n;
            }
            // entrada_reposicion AUMENTA el lote RE destino → revertir restando
            if ($m['tipo'] === 'entrada_reposicion' && $dest && $dest !== $orig) {
                $ajustes[$dest] = ($ajustes[$dest] ?? 0) - $n;
            }
        }

        $lineas = [];
        foreach ($rows as $r) {
            $loteId = (int) $r['lote_id'];
            $ajuste = $ajustes[$loteId] ?? 0;

            // Reconstruir num_animales a la fecha
            $num = max(0, (int) $r['num_actual'] + $ajuste);
            if ($num <= 0) continue; // Lote sin animales en esa fecha, ignorar

            $pesoKg     = $r['peso_tabla']  !== null ? (float) $r['peso_tabla']  : null;
            $costeEur   = $r['coste_tabla'] !== null ? (float) $r['coste_tabla'] : null;
            $pesoTotal  = $pesoKg  !== null ? round($pesoKg  * $num, 3) : null;
            $valorTotal = $costeEur !== null ? round($costeEur * $num, 2) : null;

            $lineas[] = [
                'lote_id'         => $loteId,
                'cuadra_id'       => $r['cuadra_id']  ? (int) $r['cuadra_id']  : null,
                'nave_id'         => $r['nave_id']    ? (int) $r['nave_id']    : null,
                'granja_id'       => (int) $r['granja_id'],
                'estado_animal'   => $r['estado_animal'],
                'num_animales'    => $num,
                'peso_kg'         => $pesoKg,
                'peso_total_kg'   => $pesoTotal,
                'coste_eur'       => $costeEur,
                'valor_total_eur' => $valorTotal,
                'semana_tabla'    => $r['semana_actual'] ? (int) $r['semana_actual'] : null,
                '_lote_codigo'    => $r['lote_codigo'],
                '_cuadra_nombre'  => $r['cuadra_nombre'],
                '_nave_nombre'    => $r['nave_nombre'],
                '_granja_nombre'  => $r['granja_nombre'],
            ];
        }
        return $lineas;
    }
}
