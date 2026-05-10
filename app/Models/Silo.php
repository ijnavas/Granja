<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class Silo
{
    private PDO $db;

    /**
     * Cache de rebuildStockAt por (silo_id, fecha) dentro del request.
     * Vital para dashboards/listados que pintan stock de >5 silos —
     * cada llamada original lanza 4-5 queries.
     */
    private static array $stockCache = [];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function allByUsuario(int $userId): array
    {
        $filter = \App\Core\OrgContext::granjaFilterSql('g.id');
        $stmt = $this->db->prepare("
            SELECT s.*, g.nombre AS granja_nombre,
                   GROUP_CONCAT(n.nombre ORDER BY n.nombre SEPARATOR ', ') AS naves_abastecidas
            FROM silos s
            JOIN granjas g ON s.granja_id = g.id
            LEFT JOIN silo_nave sn ON sn.silo_id = s.id
            LEFT JOIN naves n ON sn.nave_id = n.id AND n.activa = 1
            WHERE g.organizacion_id = :uid AND s.activo = 1 $filter
            GROUP BY s.id
            ORDER BY g.nombre, s.nombre
        ");
        $stmt->execute(['uid' => \App\Core\OrgContext::id()]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$r) $r = $this->withStockReal($r);
        return $rows;
    }

    public function find(int $id, int $userId): ?array
    {
        $filter = \App\Core\OrgContext::granjaFilterSql('g.id');
        $stmt = $this->db->prepare("
            SELECT s.*, g.nombre AS granja_nombre,
                   GROUP_CONCAT(n.nombre ORDER BY n.nombre SEPARATOR ', ') AS naves_abastecidas
            FROM silos s
            JOIN granjas g ON s.granja_id = g.id
            LEFT JOIN silo_nave sn ON sn.silo_id = s.id
            LEFT JOIN naves n ON sn.nave_id = n.id AND n.activa = 1
            WHERE s.id = :id AND g.organizacion_id = :uid $filter
            GROUP BY s.id
        ");
        $stmt->execute(['id' => $id, 'uid' => \App\Core\OrgContext::id()]);
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
     * timeline completo de eventos (calibración + recargas).
     *
     * Modelo:
     *   - (silos.stock_actual_kg, silos.stock_base_fecha) representan la última
     *     CALIBRACIÓN manual: un evento 'set' que fija el estado a un valor
     *     concreto en una fecha concreta (sobrescribe lo anterior).
     *   - silo_recargas contiene recargas: eventos 'add' que suman cantidad.
     *
     * Algoritmo (calibración como evento más en la timeline):
     *   events = [(cal_fecha, 'set', cal_kg)] ∪ [(r.fecha, 'add', r.kg) ∀r]
     *   sort asc (fecha, tipo: 'add' antes que 'set' si empatan — la
     *   calibración del mismo día manda)
     *   state = 0, date = first event date
     *   for each event e:
     *       if e.fecha > date: state = max(0, state - consumo(date, e.fecha))
     *       if e.type == 'set': state = e.kg
     *       else:               state += e.kg
     *       date = e.fecha
     *   if target > date: state = max(0, state - consumo(date, target))
     *
     * Esto permite recargas con fecha anterior a la calibración: si el usuario
     * calibra hoy a 0 kg y luego registra una recarga de hace 3 días con 12.000
     * kg, la calibración de hoy "mata" el estado previo, no al revés.
     */
    public function rebuildStockAt(int $siloId, string $targetDate): float
    {
        $cacheKey = $siloId . '|' . $targetDate;
        if (isset(self::$stockCache[$cacheKey])) {
            return self::$stockCache[$cacheKey];
        }

        $result = $this->rebuildStockAtUncached($siloId, $targetDate);
        self::$stockCache[$cacheKey] = $result;
        return $result;
    }

    /** Invalida la cache de stock de un silo (usar tras añadir recarga/calibración). */
    public static function invalidateStockCache(?int $siloId = null): void
    {
        if ($siloId === null) {
            self::$stockCache = [];
            return;
        }
        foreach (array_keys(self::$stockCache) as $k) {
            if (str_starts_with($k, $siloId . '|')) unset(self::$stockCache[$k]);
        }
    }

    private function rebuildStockAtUncached(int $siloId, string $targetDate): float
    {
        $siloStmt = $this->db->prepare(
            "SELECT stock_actual_kg, stock_base_fecha FROM silos WHERE id = :id"
        );
        $siloStmt->execute(['id' => $siloId]);
        $s = $siloStmt->fetch();
        if (!$s) return 0.0;

        // Cargar todas las recargas (sin filtrar por calibración — la calibración
        // es ahora un evento más dentro del timeline).
        $rStmt = $this->db->prepare("
            SELECT fecha, cantidad_kg
            FROM silo_recargas
            WHERE silo_id = :sid
            ORDER BY fecha ASC, id ASC
        ");
        $rStmt->execute(['sid' => $siloId]);
        $recargas = $rStmt->fetchAll();

        // Cargar todas las calibraciones manuales (taras). Cada una es un
        // evento 'set' dentro del timeline.
        $cStmt = $this->db->prepare("
            SELECT fecha, stock_kg
            FROM silo_calibraciones
            WHERE silo_id = :sid
            ORDER BY fecha ASC, id ASC
        ");
        $cStmt->execute(['sid' => $siloId]);
        $calibraciones = $cStmt->fetchAll();

        // Construir timeline unificado.
        // Orden de desempate (misma fecha): las recargas ('add') se aplican ANTES
        // que la calibración ('set'). Así si un usuario recarga y luego calibra
        // el mismo día, la calibración manda.
        $events = [];
        foreach ($recargas as $r) {
            $events[] = [
                'fecha' => (string)$r['fecha'],
                'tipo'  => 'add',
                'kg'    => (float)$r['cantidad_kg'],
                'ord'   => 0,
            ];
        }
        if (!empty($s['stock_base_fecha'])) {
            $events[] = [
                'fecha' => (string)$s['stock_base_fecha'],
                'tipo'  => 'set',
                'kg'    => (float)$s['stock_actual_kg'],
                'ord'   => 1,
            ];
        }
        foreach ($calibraciones as $c) {
            $events[] = [
                'fecha' => (string)$c['fecha'],
                'tipo'  => 'set',
                'kg'    => (float)$c['stock_kg'],
                'ord'   => 2, // las taras explícitas mandan sobre la calibración legacy del mismo día
            ];
        }

        // Sin ningún evento: stock desconocido, devolvemos 0.
        if (!$events) return 0.0;

        usort($events, function ($a, $b) {
            return ($a['fecha'] <=> $b['fecha']) ?: ($a['ord'] <=> $b['ord']);
        });

        // Preload de lotes+tablas una sola vez, reusado para cada segmento.
        [$lotes, $tablas] = $this->loadConsumoData($siloId);

        $state = 0.0;
        $date  = $events[0]['fecha'];

        foreach ($events as $e) {
            if ($e['fecha'] > $date) {
                $state = max(0.0, $state - $this->consumoEntre($date, $e['fecha'], $lotes, $tablas));
            }
            if ($e['tipo'] === 'set') {
                $state = $e['kg'];
            } else {
                $state += $e['kg'];
            }
            $date = $e['fecha'];
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
        // Si el usuario crea el silo con stock_actual_kg=0, interpretamos que
        // NO está calibrando manualmente (es el default del form). Dejamos
        // stock_base_fecha=NULL para que el replay no inyecte un evento 'set'
        // que bloquee recargas retroactivas posteriores. Si introduce un valor
        // >0, sí lo tratamos como calibración explícita con fecha de hoy.
        $tieneCalibracion = ((float)($data['stock_actual_kg'] ?? 0)) > 0;
        $sql = $tieneCalibracion
            ? "INSERT INTO silos (granja_id, nombre, capacidad_kg, stock_actual_kg, stock_minimo_kg, stock_base_fecha, descripcion)
               VALUES (:granja_id, :nombre, :capacidad_kg, :stock_actual_kg, :stock_minimo_kg, CURDATE(), :descripcion)"
            : "INSERT INTO silos (granja_id, nombre, capacidad_kg, stock_actual_kg, stock_minimo_kg, stock_base_fecha, descripcion)
               VALUES (:granja_id, :nombre, :capacidad_kg, :stock_actual_kg, :stock_minimo_kg, NULL, :descripcion)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($data);
        $id = (int) $this->db->lastInsertId();
        $this->syncNaves($id, $naveIds);
        return $id;
    }

    public function update(int $id, int $userId, array $data, array $naveIds): bool
    {
        // Con la nueva tabla silo_calibraciones, la edición del silo ya NO
        // toca stock_base_fecha: las calibraciones (taras) se hacen desde el
        // botón dedicado. Aquí solo actualizamos nombre, capacidad, mínimo,
        // descripción, y conservamos stock_actual_kg / stock_base_fecha tal
        // cual estaban para no interferir con el timeline.
        //
        // Solo si es un silo NUEVO (create) se escribe stock_base_fecha.
        $stmt = $this->db->prepare("
            UPDATE silos s
            JOIN granjas g ON s.granja_id = g.id
            SET s.nombre = :nombre,
                s.capacidad_kg = :capacidad_kg,
                s.stock_minimo_kg = :stock_minimo_kg,
                s.descripcion = :descripcion
            WHERE s.id = :id AND g.organizacion_id = :usuario_id
        ");
        $params = [
            'nombre'          => $data['nombre'],
            'capacidad_kg'    => $data['capacidad_kg'],
            'stock_minimo_kg' => $data['stock_minimo_kg'],
            'descripcion'     => $data['descripcion'],
            'id'              => $id,
            'usuario_id'      => $userId,
        ];
        $ok = $stmt->execute($params);
        $this->syncNaves($id, $naveIds);
        return $ok;
    }

    public function delete(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE silos s
            JOIN granjas g ON s.granja_id = g.id
            SET s.activo = 0
            WHERE s.id = :id AND g.organizacion_id = :uid
        ");
        return $stmt->execute(['id' => $id, 'uid' => \App\Core\OrgContext::id()]);
    }

    // ── Calibraciones (taras) ────────────────────────────────────

    /**
     * Registra una calibración manual (tara) del silo. Se guarda como un
     * evento 'set' en el timeline de replay: a partir de esa fecha, el stock
     * queda fijado al valor indicado y los días siguientes se calculan
     * restando consumo + sumando recargas posteriores.
     *
     * Devuelve el ID de la calibración insertada.
     */
    public function addCalibracion(int $siloId, string $fecha, float $stockKg, ?string $motivo, int $userId): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO silo_calibraciones (silo_id, fecha, stock_kg, motivo, usuario_id)
            VALUES (:silo_id, :fecha, :stock_kg, :motivo, :usuario_id)
        ");
        $stmt->execute([
            'silo_id'    => $siloId,
            'fecha'      => $fecha,
            'stock_kg'   => $stockKg,
            'motivo'     => $motivo,
            'usuario_id' => $userId,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Histórico de calibraciones de un silo, más reciente primero.
     * Incluye nombre del usuario que la hizo.
     */
    public function calibraciones(int $siloId): array
    {
        $stmt = $this->db->prepare("
            SELECT c.*, u.nombre AS usuario_nombre
            FROM silo_calibraciones c
            LEFT JOIN usuarios u ON c.usuario_id = u.id
            WHERE c.silo_id = :sid
            ORDER BY c.fecha DESC, c.id DESC
        ");
        $stmt->execute(['sid' => $siloId]);
        return $stmt->fetchAll();
    }

    public function findCalibracion(int $calibId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM silo_calibraciones WHERE id = :id");
        $stmt->execute(['id' => $calibId]);
        return $stmt->fetch() ?: null;
    }

    public function deleteCalibracion(int $calibId, int $siloId): void
    {
        $this->db->prepare("DELETE FROM silo_calibraciones WHERE id = :id AND silo_id = :sid")
            ->execute(['id' => $calibId, 'sid' => $siloId]);
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

    /**
     * Construye WHERE + params para filtros de recargas.
     */
    private function buildRecargaFiltros(int $userId, array $filtros): array
    {
        $filter     = \App\Core\OrgContext::granjaFilterSql('g.id');
        $conditions = ["g.organizacion_id = :uid $filter"];
        $params     = ['uid' => \App\Core\OrgContext::id()];

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

        return [implode(' AND ', $conditions), $params];
    }

    public function countRecargasUsuario(int $userId, array $filtros = []): int
    {
        [$where, $params] = $this->buildRecargaFiltros($userId, $filtros);
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM silo_recargas r
            JOIN silos s   ON r.silo_id = s.id
            JOIN granjas g ON s.granja_id = g.id
            WHERE {$where}
        ");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function allRecargasUsuario(int $userId, array $filtros = [], ?int $limit = null, int $offset = 0): array
    {
        [$where, $params] = $this->buildRecargaFiltros($userId, $filtros);

        $limitSql = $limit !== null ? 'LIMIT :lim OFFSET :off' : 'LIMIT 500';

        $stmt = $this->db->prepare("
            SELECT r.*, s.nombre AS silo_nombre, g.nombre AS granja_nombre,
                   u.nombre AS usuario_nombre
            FROM silo_recargas r
            JOIN silos s    ON r.silo_id = s.id
            JOIN granjas g  ON s.granja_id = g.id
            JOIN usuarios u ON r.usuario_id = u.id
            WHERE {$where}
            ORDER BY r.fecha DESC, r.id DESC
            {$limitSql}
        ");
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        if ($limit !== null) {
            $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
            $stmt->bindValue('off', $offset, PDO::PARAM_INT);
        }
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