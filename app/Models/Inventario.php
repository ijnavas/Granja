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

    public function create(int $userId, string $fecha, ?string $nombre, string $tipo): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO inventarios (usuario_id, fecha, nombre, tipo) VALUES (:uid, :fecha, :nombre, :tipo)
        ");
        $stmt->execute(['uid' => $userId, 'fecha' => $fecha, 'nombre' => $nombre, 'tipo' => $tipo]);
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

    // ── Cálculo de líneas a una fecha dada ───────────────────────
    // tipo = 'cuadra'  → una fila por lote+cuadra, num_animales = animales en esa cuadra
    // tipo = 'global'  → una fila por lote, num_animales = total lote, nave = lista de naves
    public function calcularLineas(int $userId, string $fecha, string $tipo = 'cuadra'): array
    {
        // 1. Filas base: lote + cuadra_lote actual + tabla de crecimiento
        $stmt = $this->db->prepare("
            SELECT
                l.id            AS lote_id,
                l.codigo        AS lote_codigo,
                CASE
                    WHEN l.estado_animal IN ('lechon','cebo') AND ea.peso_min_kg IS NOT NULL AND tcl.peso_kg >= ea.peso_min_kg THEN 'cebo'
                    WHEN l.estado_animal IN ('lechon','cebo') AND ea.peso_min_kg IS NOT NULL AND tcl.peso_kg < ea.peso_min_kg  THEN 'lechon'
                    ELSE l.estado_animal
                END             AS estado_animal,
                l.granja_id,
                l.nave_id,
                l.num_animales  AS lote_num_actual,
                g.nombre        AS granja_nombre,
                n.nombre        AS nave_nombre,
                cl.cuadra_id,
                c.nombre        AS cuadra_nombre,
                COALESCE(cl.num_animales, 0) AS cuadra_num_actual,
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
            LEFT JOIN estados_animal ea ON ea.codigo = 'cebo'
            WHERE g.usuario_id      = :uid
              AND l.fecha_nacimiento <= :fecha3
              AND l.fecha_nacimiento IS NOT NULL
            ORDER BY g.nombre, n.nombre, c.nombre, l.codigo
        ");
        $stmt->execute(['uid' => $userId, 'fecha1' => $fecha, 'fecha2' => $fecha, 'fecha3' => $fecha]);
        $rows = $stmt->fetchAll();

        // 2. Movimientos POSTERIORES a la fecha para reconstruir conteos
        $stmtMov = $this->db->prepare("
            SELECT m.tipo, m.num_animales, m.lote_origen_id, m.lote_destino_id,
                   m.cuadra_origen_id, m.cuadra_destino_id
            FROM movimientos m
            JOIN lotes l   ON m.lote_origen_id = l.id
            JOIN granjas g ON l.granja_id = g.id
            WHERE g.usuario_id = :uid
              AND m.fecha > :fecha
        ");
        $stmtMov->execute(['uid' => $userId, 'fecha' => $fecha]);

        // Ajuste a nivel de lote (para modo global)
        $ajusteLote = [];
        // Ajuste a nivel de cuadra: [lote_id][cuadra_id]
        $ajusteCuadra = [];

        foreach ($stmtMov->fetchAll() as $m) {
            $n    = (int) $m['num_animales'];
            $orig = (int) $m['lote_origen_id'];
            $dest = $m['lote_destino_id'] ? (int) $m['lote_destino_id'] : null;
            $cOrig = $m['cuadra_origen_id']  ? (int) $m['cuadra_origen_id']  : null;
            $cDest = $m['cuadra_destino_id'] ? (int) $m['cuadra_destino_id'] : null;

            switch ($m['tipo']) {
                case 'baja':
                case 'venta':
                    // Restar del lote y cuadra → revertir sumando
                    $ajusteLote[$orig] = ($ajusteLote[$orig] ?? 0) + $n;
                    if ($cOrig) $ajusteCuadra[$orig][$cOrig] = ($ajusteCuadra[$orig][$cOrig] ?? 0) + $n;
                    break;

                case 'traslado_cuadra':
                    // No cambia total lote, pero sí cuadras → revertir movimiento
                    if ($cOrig) $ajusteCuadra[$orig][$cOrig] = ($ajusteCuadra[$orig][$cOrig] ?? 0) + $n;
                    if ($cDest) $ajusteCuadra[$orig][$cDest] = ($ajusteCuadra[$orig][$cDest] ?? 0) - $n;
                    break;

                case 'entrada_reposicion':
                    // Origen pierde animales, lote RE destino los gana → revertir
                    $ajusteLote[$orig] = ($ajusteLote[$orig] ?? 0) + $n;
                    if ($cOrig) $ajusteCuadra[$orig][$cOrig] = ($ajusteCuadra[$orig][$cOrig] ?? 0) + $n;
                    if ($dest && $dest !== $orig) {
                        $ajusteLote[$dest] = ($ajusteLote[$dest] ?? 0) - $n;
                    }
                    break;

                case 'entrada_madres':
                    $ajusteLote[$orig] = ($ajusteLote[$orig] ?? 0) + $n;
                    if ($cOrig) $ajusteCuadra[$orig][$cOrig] = ($ajusteCuadra[$orig][$cOrig] ?? 0) + $n;
                    break;
            }
        }

        // 3. Construir líneas según el tipo
        if ($tipo === 'global') {
            return $this->construirGlobal($rows, $ajusteLote);
        }
        return $this->construirPorCuadra($rows, $ajusteLote, $ajusteCuadra);
    }

    private function construirPorCuadra(array $rows, array $ajusteLote, array $ajusteCuadra): array
    {
        $lineas = [];
        foreach ($rows as $r) {
            $loteId  = (int) $r['lote_id'];
            $cuadraId = $r['cuadra_id'] ? (int) $r['cuadra_id'] : null;

            // Animales en esta cuadra ajustados
            $numCuadra = (int) $r['cuadra_num_actual'];
            $ajC = $cuadraId ? ($ajusteCuadra[$loteId][$cuadraId] ?? 0) : 0;
            $num = max(0, $numCuadra + $ajC);

            // Si no hay cuadra, usar total del lote ajustado (lote sin asignar a cuadra)
            if (!$cuadraId) {
                $num = max(0, (int) $r['lote_num_actual'] + ($ajusteLote[$loteId] ?? 0));
            }

            if ($num <= 0) continue;

            $pesoKg     = $r['peso_tabla']  !== null ? (float) $r['peso_tabla']  : null;
            $costeEur   = $r['coste_tabla'] !== null ? (float) $r['coste_tabla'] : null;

            $lineas[] = [
                'lote_id'         => $loteId,
                'cuadra_id'       => $cuadraId,
                'nave_id'         => $r['nave_id'] ? (int) $r['nave_id'] : null,
                'granja_id'       => (int) $r['granja_id'],
                'estado_animal'   => $r['estado_animal'],
                'num_animales'    => $num,
                'peso_kg'         => $pesoKg,
                'peso_total_kg'   => $pesoKg  !== null ? round($pesoKg  * $num, 3) : null,
                'coste_eur'       => $costeEur,
                'valor_total_eur' => $costeEur !== null ? round($costeEur * $num, 2) : null,
                'semana_tabla'    => $r['semana_actual'] ? (int) $r['semana_actual'] : null,
                '_lote_codigo'    => $r['lote_codigo'],
                '_cuadra_nombre'  => $r['cuadra_nombre'],
                '_nave_nombre'    => $r['nave_nombre'],
                '_granja_nombre'  => $r['granja_nombre'],
            ];
        }
        return $lineas;
    }

    private function construirGlobal(array $rows, array $ajusteLote): array
    {
        // Agrupar por lote_id
        $porLote = [];
        foreach ($rows as $r) {
            $loteId = (int) $r['lote_id'];
            if (!isset($porLote[$loteId])) {
                $porLote[$loteId] = [
                    'lote_id'      => $loteId,
                    'granja_id'    => (int) $r['granja_id'],
                    'nave_id'      => $r['nave_id'] ? (int) $r['nave_id'] : null,
                    'estado_animal'=> $r['estado_animal'],
                    'semana_actual'=> $r['semana_actual'],
                    'peso_tabla'   => $r['peso_tabla'],
                    'coste_tabla'  => $r['coste_tabla'],
                    'lote_num_actual' => (int) $r['lote_num_actual'],
                    '_lote_codigo' => $r['lote_codigo'],
                    '_granja_nombre' => $r['granja_nombre'],
                    '_naves'       => [],
                ];
            }
            // Acumular naves únicas
            if ($r['nave_nombre'] && !in_array($r['nave_nombre'], $porLote[$loteId]['_naves'])) {
                $porLote[$loteId]['_naves'][] = $r['nave_nombre'];
            }
        }

        $lineas = [];
        foreach ($porLote as $loteId => $d) {
            $num = max(0, $d['lote_num_actual'] + ($ajusteLote[$loteId] ?? 0));
            if ($num <= 0) continue;

            $pesoKg   = $d['peso_tabla']  !== null ? (float) $d['peso_tabla']  : null;
            $costeEur = $d['coste_tabla'] !== null ? (float) $d['coste_tabla'] : null;
            $naveStr  = !empty($d['_naves']) ? implode(', ', $d['_naves']) : null;

            $lineas[] = [
                'lote_id'         => $loteId,
                'cuadra_id'       => null,
                'nave_id'         => $d['nave_id'],
                'granja_id'       => $d['granja_id'],
                'estado_animal'   => $d['estado_animal'],
                'num_animales'    => $num,
                'peso_kg'         => $pesoKg,
                'peso_total_kg'   => $pesoKg  !== null ? round($pesoKg  * $num, 3) : null,
                'coste_eur'       => $costeEur,
                'valor_total_eur' => $costeEur !== null ? round($costeEur * $num, 2) : null,
                'semana_tabla'    => $d['semana_actual'] ? (int) $d['semana_actual'] : null,
                '_lote_codigo'    => $d['_lote_codigo'],
                '_cuadra_nombre'  => null,
                '_nave_nombre'    => $naveStr,
                '_granja_nombre'  => $d['_granja_nombre'],
            ];
        }
        return $lineas;
    }
}
