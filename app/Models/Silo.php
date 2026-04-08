<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class Silo
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function allByUsuario(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT s.*, g.nombre AS granja_nombre,
                   GROUP_CONCAT(n.nombre ORDER BY n.nombre SEPARATOR ', ') AS naves_abastecidas
            FROM silos s
            JOIN granjas g ON s.granja_id = g.id
            LEFT JOIN silo_nave sn ON sn.silo_id = s.id
            LEFT JOIN naves n ON sn.nave_id = n.id AND n.activa = 1
            WHERE g.usuario_id = :uid AND s.activo = 1
            GROUP BY s.id
            ORDER BY g.nombre, s.nombre
        ");
        $stmt->execute(['uid' => $userId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) $r = $this->withStockReal($r);
        return $rows;
    }

    public function find(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT s.*, g.nombre AS granja_nombre,
                   GROUP_CONCAT(n.nombre ORDER BY n.nombre SEPARATOR ', ') AS naves_abastecidas
            FROM silos s
            JOIN granjas g ON s.granja_id = g.id
            LEFT JOIN silo_nave sn ON sn.silo_id = s.id
            LEFT JOIN naves n ON sn.nave_id = n.id AND n.activa = 1
            WHERE s.id = :id AND g.usuario_id = :uid
            GROUP BY s.id
        ");
        $stmt->execute(['id' => $id, 'uid' => $userId]);
        $row = $stmt->fetch();
        return $row ? $this->withStockReal($row) : null;
    }

    /**
     * Enriquece una fila de silo con el stock real calculado via replay del
     * timeline de recargas desde la calibración (stock_actual_kg/stock_base_fecha
     * en la BD son ahora la CALIBRACIÓN, no un estado consolidado).
     *
     * Sobreescribe `stock_actual_kg` con el real calculado por compatibilidad
     * con vistas existentes y mantiene el crudo en `stock_base_kg`.
     */
    private function withStockReal(array $row): array
    {
        $base = (float)($row['stock_actual_kg'] ?? 0);
        $real = $this->rebuildStockAt((int)$row['id'], date('Y-m-d'));

        $row['stock_base_kg']      = $base;                 // calibración cruda
        $row['stock_real_kg']      = round($real, 2);
        // Compat: el resto del sistema lee stock_actual_kg, lo apuntamos al real.
        $row['stock_actual_kg']    = round($real, 2);
        $row['stock_consumido_kg'] = 0; // legacy, deprecated con el modelo replay
        $row['pct_stock']          = ((float)($row['capacidad_kg'] ?? 0) > 0)
            ? (int) round(($real / (float)$row['capacidad_kg']) * 100)
            : 0;

        return $row;
    }

    /**
     * Reconstruye el stock real de un silo en una fecha dada replicando el
     * timeline completo de recargas desde la última calibración.
     *
     * Modelo:
     *   - (silos.stock_actual_kg, silos.stock_base_fecha) representan la última
     *     CALIBRACIÓN manual del silo — el estado físico conocido en una fecha.
     *     Se fijan solo por silos/crear y silos/editar, no por recargas.
     *   - silo_recargas contiene todas las recargas con su fecha real. Las que
     *     tienen fecha >= calibracion_fecha se replican en orden cronológico,
     *     aplicando el consumo entre evento y evento.
     *
     * Algoritmo:
     *   state = calibracion_kg
     *   date  = calibracion_fecha
     *   for each recarga R (asc fecha, R.fecha >= calibracion_fecha):
     *       state = max(0, state - consumo(date, R.fecha)) + R.cantidad
     *       date  = R.fecha
     *   state = max(0, state - consumo(date, target_date))
     *   return state
     */
    public function rebuildStockAt(int $siloId, string $targetDate): float
    {
        $siloStmt = $this->db->prepare(
            "SELECT stock_actual_kg, stock_base_fecha FROM silos WHERE id = :id"
        );
        $siloStmt->execute(['id' => $siloId]);
        $s = $siloStmt->fetch();
        if (!$s) return 0.0;

        $state = (float)$s['stock_actual_kg'];
        $date  = !empty($s['stock_base_fecha']) ? (string)$s['stock_base_fecha'] : null;

        // Sin fecha de calibración no hay consumo que descontar.
        if ($date === null) return $state;

        // Recargas posteriores (o iguales) a la calibración, orden cronológico.
        $rStmt = $this->db->prepare("
            SELECT fecha, cantidad_kg
            FROM silo_recargas
            WHERE silo_id = :sid AND fecha >= :cal
            ORDER BY fecha ASC, id ASC
        ");
        $rStmt->execute(['sid' => $siloId, 'cal' => $date]);
        $recargas = $rStmt->fetchAll();

        // Preload de lotes+tablas una sola vez, reusado para cada segmento.
        [$lotes, $tablas] = $this->loadConsumoData($siloId);

        foreach ($recargas as $r) {
            $rFecha = (string)$r['fecha'];
            if ($rFecha > $date) {
                $state = max(0.0, $state - $this->consumoEntre($date, $rFecha, $lotes, $tablas));
            }
            $state += (float)$r['cantidad_kg'];
            $date = $rFecha;
        }

        if ($targetDate > $date) {
            $state = max(0.0, $state - $this->consumoEntre($date, $targetDate, $lotes, $tablas));
        }

        return $state;
    }

    /**
     * Consumo acumulado (kg) entre dos fechas para los lotes activos del silo.
     *
     * Convención: $desde exclusivo, $hasta exclusivo. Es decir, calcula el
     * consumo de los días [$desde+1 .. $hasta-1] inclusive — el consumo del
     * día $hasta aún no se ha producido.
     *
     * Se mantiene como API pública para consumidores externos; internamente
     * reusa loadConsumoData + consumoEntre.
     */
    public function consumoAcumulado(int $siloId, string $desde, string $hasta): float
    {
        [$lotes, $tablas] = $this->loadConsumoData($siloId);
        return $this->consumoEntre($desde, $hasta, $lotes, $tablas);
    }

    /**
     * Carga lotes activos del silo + líneas de tabla de crecimiento relevantes,
     * para poder calcular consumo en varios segmentos sin re-consultar BD.
     */
    private function loadConsumoData(int $siloId): array
    {
        $stmt = $this->db->prepare("
            SELECT l.id, l.num_animales, l.fecha_nacimiento, tr.tabla_id
            FROM silo_nave sn
            JOIN lotes l ON l.nave_id = sn.nave_id
                        AND l.estado = 'activo'
                        AND l.fecha_nacimiento IS NOT NULL
            LEFT JOIN tabla_raza tr ON tr.raza_id = l.raza_id
            WHERE sn.silo_id = :silo_id
        ");
        $stmt->execute(['silo_id' => $siloId]);
        $lotes = $stmt->fetchAll();

        $tablaIds = array_values(array_unique(array_filter(array_map(
            fn($l) => (int)($l['tabla_id'] ?? 0), $lotes
        ))));
        $tablas = []; // [tablaId => [semana => consumo_g]]
        if ($tablaIds) {
            $in = implode(',', array_fill(0, count($tablaIds), '?'));
            $q  = $this->db->prepare("
                SELECT tcl.tabla_id, tcl.semana, tcl.consumo_acumulado_g
                FROM tablas_crecimiento_lineas tcl
                JOIN tablas_crecimiento tc ON tc.id = tcl.tabla_id AND tc.activa = 1
                WHERE tcl.tabla_id IN ($in) AND tcl.consumo_acumulado_g IS NOT NULL
                ORDER BY tcl.tabla_id, tcl.semana
            ");
            $q->execute($tablaIds);
            foreach ($q->fetchAll() as $r) {
                $tablas[(int)$r['tabla_id']][(int)$r['semana']] = (float)$r['consumo_acumulado_g'];
            }
        }

        return [$lotes, $tablas];
    }

    /**
     * Consumo entre dos fechas dados lotes+tablas ya cargados (versión interna
     * usada en bucles donde reloadear por cada segmento sería innecesario).
     */
    private function consumoEntre(string $desde, string $hasta, array $lotes, array $tablas): float
    {
        $tsDesde = strtotime($desde);
        $tsHasta = strtotime($hasta);
        if (!$tsDesde || !$tsHasta || $tsHasta <= $tsDesde) return 0.0;
        if (!$lotes) return 0.0;

        $total = 0.0;
        for ($d = $tsDesde + 86400; $d < $tsHasta; $d += 86400) {
            foreach ($lotes as $l) {
                $tsNac = strtotime((string)$l['fecha_nacimiento']);
                if (!$tsNac || $tsNac > $d) continue;

                $semana = (int) ceil(($d - $tsNac) / 86400 / 7);
                if ($semana < 1) $semana = 1;

                $tablaId = (int)($l['tabla_id'] ?? 0);
                if (!$tablaId || empty($tablas[$tablaId])) continue;

                // Línea con la mayor semana <= semana actual.
                $consumoG = 0.0;
                foreach ($tablas[$tablaId] as $sem => $g) {
                    if ($sem <= $semana) $consumoG = $g;
                    else break;
                }
                if ($consumoG <= 0) continue;

                $total += ($consumoG / 1000.0) * (int)$l['num_animales'];
            }
        }
        return $total;
    }

    public function navesAsignadas(int $siloId): array
    {
        $stmt = $this->db->prepare("
            SELECT nave_id FROM silo_nave WHERE silo_id = :id
        ");
        $stmt->execute(['id' => $siloId]);
        return array_column($stmt->fetchAll(), 'nave_id');
    }

    public function create(array $data, array $naveIds): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO silos (granja_id, nombre, capacidad_kg, stock_actual_kg, stock_minimo_kg, stock_base_fecha, descripcion)
            VALUES (:granja_id, :nombre, :capacidad_kg, :stock_actual_kg, :stock_minimo_kg, CURDATE(), :descripcion)
        ");
        $stmt->execute($data);
        $id = (int) $this->db->lastInsertId();
        $this->syncNaves($id, $naveIds);
        return $id;
    }

    public function update(int $id, int $userId, array $data, array $naveIds): bool
    {
        // Editar el silo desde el formulario equivale a una CALIBRACIÓN manual:
        // el valor introducido en stock_actual_kg es el estado físico conocido HOY.
        // Fijamos stock_base_fecha = CURDATE() para que rebuildStockAt arranque
        // desde este punto y solo procese recargas con fecha >= hoy. Las recargas
        // anteriores quedan como histórico informativo pero no afectan al cálculo.
        $stmt = $this->db->prepare("
            UPDATE silos s
            JOIN granjas g ON s.granja_id = g.id
            SET s.nombre = :nombre,
                s.capacidad_kg = :capacidad_kg,
                s.stock_actual_kg = :stock_actual_kg,
                s.stock_minimo_kg = :stock_minimo_kg,
                s.stock_base_fecha = CURDATE(),
                s.descripcion = :descripcion
            WHERE s.id = :id AND g.usuario_id = :usuario_id
        ");
        $data['id'] = $id;
        $data['usuario_id'] = $userId;
        $ok = $stmt->execute($data);
        $this->syncNaves($id, $naveIds);
        return $ok;
    }

    public function delete(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE silos s
            JOIN granjas g ON s.granja_id = g.id
            SET s.activo = 0
            WHERE s.id = :id AND g.usuario_id = :uid
        ");
        return $stmt->execute(['id' => $id, 'uid' => $userId]);
    }

    // ── Recargas ─────────────────────────────────────────────────

    public function recargas(int $siloId): array
    {
        $stmt = $this->db->prepare("
            SELECT r.*, u.nombre AS usuario_nombre
            FROM silo_recargas r
            JOIN usuarios u ON r.usuario_id = u.id
            WHERE r.silo_id = :id
            ORDER BY r.fecha DESC, r.id DESC
        ");
        $stmt->execute(['id' => $siloId]);
        return $stmt->fetchAll();
    }

    public function allRecargasUsuario(int $userId, array $filtros = []): array
    {
        $conditions = ['g.usuario_id = :uid'];
        $params = ['uid' => $userId];

        if (!empty($filtros['fecha_desde'])) {
            $conditions[] = 'r.fecha >= :fecha_desde';
            $params['fecha_desde'] = $filtros['fecha_desde'];
        }
        if (!empty($filtros['fecha_hasta'])) {
            $conditions[] = 'r.fecha <= :fecha_hasta';
            $params['fecha_hasta'] = $filtros['fecha_hasta'];
        }
        if (!empty($filtros['silo_id'])) {
            $conditions[] = 'r.silo_id = :silo_id';
            $params['silo_id'] = (int)$filtros['silo_id'];
        }
        if (!empty($filtros['tipo_pienso'])) {
            $conditions[] = 'r.tipo_pienso LIKE :tipo_pienso';
            $params['tipo_pienso'] = '%' . $filtros['tipo_pienso'] . '%';
        }

        $where = implode(' AND ', $conditions);
        $stmt = $this->db->prepare("
            SELECT r.*, s.nombre AS silo_nombre, g.nombre AS granja_nombre,
                   u.nombre AS usuario_nombre
            FROM silo_recargas r
            JOIN silos s    ON r.silo_id = s.id
            JOIN granjas g  ON s.granja_id = g.id
            JOIN usuarios u ON r.usuario_id = u.id
            WHERE {$where}
            ORDER BY r.fecha DESC, r.id DESC
            LIMIT 500
        ");
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function findRecarga(int $recargaId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM silo_recargas WHERE id = :id");
        $stmt->execute(['id' => $recargaId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Con el modelo replay, actualizar una recarga es simplemente tocar la fila.
     * El stock se recalcula siempre dinámicamente desde silo_recargas + calibración.
     *
     * $cantidadAnterior y $siloAnterior se mantienen en la firma por back-compat
     * con el controller pero ya no se usan.
     */
    public function updateRecarga(int $recargaId, array $data, float $cantidadAnterior = 0, int $siloAnterior = 0): void
    {
        // Defensa: la columna `albaran` no existe en silo_recargas; el albarán se
        // almacena dentro de observaciones (convención compartida con EscaneoController).
        unset($data['albaran']);

        $stmt = $this->db->prepare("
            UPDATE silo_recargas
            SET silo_id = :silo_id, fecha = :fecha, cantidad_kg = :cantidad_kg,
                tipo_pienso = :tipo_pienso, proveedor = :proveedor,
                observaciones = :observaciones
            WHERE id = :id
        ");
        $data['id'] = $recargaId;
        $stmt->execute($data);
    }

    /**
     * Con el modelo replay, añadir una recarga es un simple INSERT. El stock
     * se reconstruye dinámicamente al leer el silo via rebuildStockAt.
     */
    public function addRecarga(int $siloId, float $cantidadKg, string $fecha, ?string $proveedor, ?string $obs, int $userId, ?string $tipoPienso = null): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO silo_recargas (silo_id, fecha, cantidad_kg, tipo_pienso, proveedor, observaciones, usuario_id)
            VALUES (:silo_id, :fecha, :cantidad_kg, :tipo_pienso, :proveedor, :observaciones, :usuario_id)
        ");
        $stmt->execute([
            'silo_id'      => $siloId,
            'fecha'        => $fecha,
            'cantidad_kg'  => $cantidadKg,
            'tipo_pienso'  => $tipoPienso,
            'proveedor'    => $proveedor,
            'observaciones'=> $obs,
            'usuario_id'   => $userId,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Con el modelo replay, borrar una recarga es un simple DELETE.
     */
    public function deleteRecarga(int $recargaId, int $siloId): void
    {
        $this->db->prepare("DELETE FROM silo_recargas WHERE id = :id AND silo_id = :silo_id")
            ->execute(['id' => $recargaId, 'silo_id' => $siloId]);
    }

    /**
     * Calcula consumo diario (kg/día) de los lotes activos en las naves de este silo,
     * y estima cuántos días quedan hasta llegar al stock mínimo.
     * Devuelve ['consumo_diario_kg', 'dias_hasta_minimo', 'fecha_minimo', 'lotes']
     */
    public function proyeccionConsumo(int $siloId): array
    {
        // Lotes activos en naves abastecidas por este silo
        $stmt = $this->db->prepare("
            SELECT l.id, l.codigo, l.num_animales, l.fecha_nacimiento,
                   n.nombre AS nave_nombre,
                   CEIL(DATEDIFF(CURDATE(), l.fecha_nacimiento) / 7) AS semana_actual,
                   tcl.consumo_acumulado_g AS consumo_diario_g
            FROM silos s
            JOIN silo_nave sn ON sn.silo_id = s.id
            JOIN naves n ON sn.nave_id = n.id
            JOIN lotes l ON l.nave_id = n.id AND l.estado = 'activo' AND l.fecha_nacimiento IS NOT NULL
            LEFT JOIN tabla_raza tr ON tr.raza_id = l.raza_id
            LEFT JOIN tablas_crecimiento tc ON tc.id = tr.tabla_id AND tc.activa = 1
            LEFT JOIN tablas_crecimiento_lineas tcl
                ON tcl.tabla_id = tc.id
                AND tcl.semana = (
                    SELECT MAX(semana) FROM tablas_crecimiento_lineas
                    WHERE tabla_id = tc.id
                      AND semana <= CEIL(DATEDIFF(CURDATE(), l.fecha_nacimiento) / 7)
                      AND consumo_acumulado_g IS NOT NULL
                )
            WHERE s.id = :silo_id
        ");
        $stmt->execute(['silo_id' => $siloId]);
        $lotes = $stmt->fetchAll();

        $totalDiarioKg = 0.0;
        foreach ($lotes as &$l) {
            // consumo_diario_g = gramos/día/animal según tabla para la semana actual
            $consumoDiarioAnimalG = (float)($l['consumo_diario_g'] ?? 0);
            $consumoDiarioKg = $consumoDiarioAnimalG / 1000 * (int)$l['num_animales'];
            $l['consumo_diario_kg'] = round($consumoDiarioKg, 2);
            $totalDiarioKg += $consumoDiarioKg;
        }

        // Stock disponible hasta mínimo — reconstruido dinámicamente via replay.
        $siloData = $this->db->prepare("SELECT stock_minimo_kg FROM silos WHERE id = :id");
        $siloData->execute(['id' => $siloId]);
        $s = $siloData->fetch();

        $stockReal = $this->rebuildStockAt($siloId, date('Y-m-d'));
        $margen    = max(0, $stockReal - (float)($s['stock_minimo_kg'] ?? 0));
        $diasHastaMinimo = $totalDiarioKg > 0 ? (int) floor($margen / $totalDiarioKg) : null;
        $fechaMinimo     = $diasHastaMinimo !== null
            ? date('Y-m-d', strtotime("+{$diasHastaMinimo} days"))
            : null;

        return [
            'consumo_diario_kg'  => round($totalDiarioKg, 2),
            'dias_hasta_minimo'  => $diasHastaMinimo,
            'fecha_minimo'       => $fechaMinimo,
            'lotes'              => $lotes,
        ];
    }

    private function syncNaves(int $siloId, array $naveIds): void
    {
        $this->db->prepare("DELETE FROM silo_nave WHERE silo_id = :id")->execute(['id' => $siloId]);
        if (empty($naveIds)) return;
        $stmt = $this->db->prepare("INSERT INTO silo_nave (silo_id, nave_id) VALUES (:silo_id, :nave_id)");
        foreach ($naveIds as $naveId) {
            $stmt->execute(['silo_id' => $siloId, 'nave_id' => (int)$naveId]);
        }
    }
}