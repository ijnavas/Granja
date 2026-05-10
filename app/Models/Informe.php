<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Modelo de informes / reportes agrupados.
 *
 * Genera consultas dinámicas sobre la tabla `movimientos` agrupadas por
 * la dimensión que el usuario elija (lote, nave, cuadra, semana de edad,
 * estado animal, motivo baja, tipo de movimiento o categoría).
 *
 * Filtros soportados:
 *   - Categoría: bajas / ventas / compras / traslados / salidas / todos
 *   - Rango de fechas
 *   - Estados animal (lechon/cebo/reposicion/madres) — multi
 *   - Naves seleccionadas — multi
 *   - Lotes seleccionados — multi
 */
class Informe
{
    private PDO $db;

    public const DIMENSIONES = [
        'lote'              => 'Lote',
        'nave'              => 'Nave',
        'cuadra'            => 'Cuadra',
        'semana_edad'       => 'Semana de edad',
        'estado_animal'     => 'Estado animal',
        'motivo_baja'       => 'Motivo de baja',
        'tipo_movimiento'   => 'Tipo de movimiento',
        'categoria'         => 'Categoría',
        'mes'               => 'Mes',
    ];

    public const CATEGORIAS_FILTRO = [
        'todos'    => 'Todos los movimientos',
        'baja'     => 'Bajas',
        'venta'    => 'Ventas',
        'entrada'  => 'Compras / entradas',
        'salida'   => 'Salidas (no venta)',
        'traslado' => 'Traslados',
    ];

    public const ESTADOS_ANIMAL = [
        'lechon'     => 'Lechón',
        'cebo'       => 'Cebo',
        'reposicion' => 'Reposición',
        'madres'     => 'Madres',
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Genera el informe agregado.
     *
     * @return array{filas: array<int, array<string,mixed>>, total: array<string,float|int>}
     */
    public function agregado(int $usuarioId, array $filtros): array
    {
        $dim     = $filtros['dimension']  ?? 'tipo_movimiento';
        $cat     = $filtros['categoria']  ?? 'todos';
        $desde   = $filtros['fecha_desde'] ?? null;
        $hasta   = $filtros['fecha_hasta'] ?? null;
        $estados = $filtros['estados']    ?? [];
        $naves   = $filtros['naves']      ?? [];
        $lotes   = $filtros['lotes']      ?? [];

        // Whitelist de dimensiones — protección contra SQL injection
        if (!isset(self::DIMENSIONES[$dim])) {
            $dim = 'tipo_movimiento';
        }

        // Expresión SQL para la dimensión elegida
        $dimExpr = match ($dim) {
            'lote'            => "lo.codigo",
            'nave'            => "COALESCE(no.nombre, nlo.nombre, '— Sin nave —')",
            'cuadra'          => "COALESCE(CONCAT(COALESCE(no.nombre, ''), ' · ', co.nombre), '— Sin cuadra —')",
            'semana_edad'     => "CONCAT('S', CEIL(DATEDIFF(m.fecha, lo.fecha_nacimiento) / 7))",
            'estado_animal'   => "COALESCE(lo.estado_animal, '—')",
            'motivo_baja'     => "COALESCE(m.motivo_baja, '—')",
            'tipo_movimiento' => "COALESCE(tm.nombre, m.tipo)",
            'categoria'       => "COALESCE(tm.categoria, '—')",
            'mes'             => "DATE_FORMAT(m.fecha, '%Y-%m')",
        };

        // Para semana_edad ordenamos numéricamente
        $orderBy = match ($dim) {
            'semana_edad' => "MIN(CEIL(DATEDIFF(m.fecha, lo.fecha_nacimiento) / 7)) ASC",
            'mes'         => "MIN(m.fecha) ASC",
            default       => "total_animales DESC",
        };

        $where  = ['(g.organizacion_id = :uid OR gn.organizacion_id = :uid2)'];
        $params = ['uid' => $usuarioId, 'uid2' => $usuarioId];

        // Filtro de categoría: usa tipos_movimiento.categoria + fallback de
        // los códigos legacy (venta/baja/etc.) que ya no están en la tabla.
        if ($cat !== 'todos') {
            $legacyCodes = match ($cat) {
                'baja'     => ['baja'],
                'venta'    => ['venta'],
                'traslado' => ['traslado_cuadra'],
                'entrada'  => [],
                'salida'   => [],
                default    => [],
            };
            $catConds = ['tm.categoria = :categoria'];
            $params['categoria'] = $cat;
            if ($legacyCodes) {
                $ph = [];
                foreach ($legacyCodes as $i => $code) {
                    $key = "lcat{$i}";
                    $ph[] = ":{$key}";
                    $params[$key] = $code;
                }
                $catConds[] = 'm.tipo IN (' . implode(',', $ph) . ')';
            }
            $where[] = '(' . implode(' OR ', $catConds) . ')';
        }

        if ($desde) { $where[] = 'm.fecha >= :desde'; $params['desde'] = $desde; }
        if ($hasta) { $where[] = 'm.fecha <= :hasta'; $params['hasta'] = $hasta; }

        if (!empty($estados)) {
            $ph = [];
            foreach ($estados as $i => $e) {
                $key = "est{$i}";
                $ph[] = ":{$key}";
                $params[$key] = $e;
            }
            $where[] = 'lo.estado_animal IN (' . implode(',', $ph) . ')';
        }

        if (!empty($naves)) {
            $ph = [];
            foreach ($naves as $i => $n) {
                $key = "nv{$i}";
                $ph[] = ":{$key}";
                $params[$key] = (int)$n;
            }
            // Match por nave de la cuadra origen O por nave del lote
            $where[] = '(co.nave_id IN (' . implode(',', $ph) . ') OR lo.nave_id IN (' . implode(',', $ph) . '))';
        }

        if (!empty($lotes)) {
            $ph = [];
            foreach ($lotes as $i => $l) {
                $key = "lt{$i}";
                $ph[] = ":{$key}";
                $params[$key] = (int)$l;
            }
            $where[] = 'm.lote_origen_id IN (' . implode(',', $ph) . ')';
        }

        $whereSql = implode(' AND ', $where);

        $sql = "
            SELECT
                {$dimExpr} AS dimension_label,
                COUNT(*)                                                  AS num_movimientos,
                COALESCE(SUM(m.num_animales), 0)                          AS total_animales,
                COALESCE(SUM(m.peso_canal_kg), 0)                         AS total_kg_canal,
                COALESCE(SUM(m.peso_real_kg * m.num_animales), 0)         AS total_kg_real,
                COALESCE(SUM(m.precio_eur), 0)                            AS total_eur,
                MIN(m.fecha)                                              AS primera_fecha,
                MAX(m.fecha)                                              AS ultima_fecha
            FROM movimientos m
            JOIN lotes lo        ON m.lote_origen_id = lo.id
            LEFT JOIN granjas g  ON lo.granja_id    = g.id
            LEFT JOIN naves nlo  ON lo.nave_id      = nlo.id
            LEFT JOIN granjas gn ON nlo.granja_id   = gn.id
            LEFT JOIN cuadras co ON m.cuadra_origen_id = co.id
            LEFT JOIN naves no   ON co.nave_id      = no.id
            LEFT JOIN tipos_movimiento tm ON tm.codigo COLLATE utf8mb4_general_ci = m.tipo
            WHERE {$whereSql}
            GROUP BY {$dimExpr}
            ORDER BY {$orderBy}
            LIMIT 200
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Totales para footer
        $total = [
            'num_movimientos' => 0,
            'total_animales'  => 0,
            'total_kg_canal'  => 0.0,
            'total_kg_real'   => 0.0,
            'total_eur'       => 0.0,
        ];
        foreach ($filas as $f) {
            $total['num_movimientos'] += (int)$f['num_movimientos'];
            $total['total_animales']  += (int)$f['total_animales'];
            $total['total_kg_canal']  += (float)$f['total_kg_canal'];
            $total['total_kg_real']   += (float)$f['total_kg_real'];
            $total['total_eur']       += (float)$f['total_eur'];
        }

        return ['filas' => $filas, 'total' => $total];
    }
}
