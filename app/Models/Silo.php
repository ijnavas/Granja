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
     * Enriquece una fila de silo con el stock real (descontando consumo desde
     * stock_base_fecha hasta hoy) y sobreescribe `stock_actual_kg` con ese
     * valor para que vistas/dashboard/etc. consuman datos correctos.
     *
     * Mantiene el valor crudo de la BD en `stock_base_kg` por si algo lo necesita.
     */
    private function withStockReal(array $row): array
    {
        $base       = (float)($row['stock_actual_kg'] ?? 0);
        $baseFecha  = $row['stock_base_fecha'] ?? null;
        $consumo    = $baseFecha
            ? $this->consumoAcumulado((int)$row['id'], $baseFecha, date('Y-m-d'))
            : 0.0;

        $real = max(0.0, $base - $consumo);

        $row['stock_base_kg']      = $base;
        $row['stock_consumido_kg'] = round($consumo, 2);
        $row['stock_real_kg']      = round($real, 2);
        // Compat: el resto del sistema lee stock_actual_kg, lo apuntamos al real.
        $row['stock_actual_kg']    = round($real, 2);
        $row['pct_stock']          = ((float)($row['capacidad_kg'] ?? 0) > 0)
            ? (int) round(($real / (float)$row['capacidad_kg']) * 100)
            : 0;

        return $row;
    }

    /**
     * Consumo acumulado (kg) entre dos fechas para los lotes activos del silo.
     * Itera día a día y, para cada lote, busca la línea de tabla de
     * crecimiento correspondiente a la semana del lote en ese día.
     *
     * Convención: $desde es exclusivo (ya está "contado" en el stock base),
     * $hasta es exclusivo también (todavía no consumido). Es decir, calcula
     * el consumo de los días [$desde+1 .. $hasta-1] inclusive — el consumo
     * del día de hoy aún no se ha producido.
     */
    public function consumoAcumulado(int $siloId, string $desde, string $hasta): float
    {
        $tsDesde = strtotime($desde);
        $tsHasta = strtotime($hasta);
        if (!$tsDesde || !$tsHasta || $tsHasta <= $tsDesde) return 0.0;

        // Lotes activos de las naves abastecidas por este silo + tabla_id de cada lote
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
        if (!$lotes) return 0.0;

        // Cargar todas las líneas de tabla relevantes una sola vez
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

        $total = 0.0;
        // Iterar por días: del día siguiente a $desde hasta el día anterior a $hasta (inclusive)
        for ($d = $tsDesde + 86400; $d < $tsHasta; $d += 86400) {
            foreach ($lotes as $l) {
                $tsNac = strtotime((string)$l['fecha_nacimiento']);
                if (!$tsNac || $tsNac > $d) continue; // aún no había nacido ese día

                $semana = (int) ceil(($d - $tsNac) / 86400 / 7);
                if ($semana < 1) $semana = 1;

                $tablaId = (int)($l['tabla_id'] ?? 0);
                if (!$tablaId || empty($tablas[$tablaId])) continue;

                // Buscar la línea con la mayor semana <= semana actual
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
        // Editar el silo desde el formulario equivale a una "calibración manual":
        // el valor introducido en stock_actual_kg es el stock real conocido HOY,
        // así que reseteamos stock_base_fecha = CURDATE() para que el descuento
        // por consumo arranque desde cero a partir de ahora.
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

    public function updateRecarga(int $recargaId, array $data, float $cantidadAnterior, int $siloAnterior): void
    {
        // Defensa: la columna `albaran` no existe en silo_recargas; el albarán se
        // almacena dentro de observaciones (convención compartida con EscaneoController).
        unset($data['albaran']);

        $this->db->beginTransaction();
        try {
            // Cargar estado completo actual de la recarga (fecha/silo antiguos reales).
            $old = $this->findRecarga($recargaId);
            if (!$old) {
                throw new \RuntimeException('Recarga no encontrada');
            }

            // 1. Revertir el efecto de la recarga antigua sobre su silo original.
            $this->revertRecargaEffect(
                (int)$old['silo_id'],
                (string)$old['fecha'],
                (float)$old['cantidad_kg'],
                $recargaId
            );

            // 2. Actualizar la fila silo_recargas con los nuevos valores.
            $stmt = $this->db->prepare("
                UPDATE silo_recargas
                SET silo_id = :silo_id, fecha = :fecha, cantidad_kg = :cantidad_kg,
                    tipo_pienso = :tipo_pienso, proveedor = :proveedor,
                    observaciones = :observaciones
                WHERE id = :id
            ");
            $data['id'] = $recargaId;
            $stmt->execute($data);

            // 3. Aplicar el efecto de la recarga nueva sobre el silo (posiblemente otro).
            $this->applyRecargaEffect(
                (int)$data['silo_id'],
                (string)$data['fecha'],
                (float)$data['cantidad_kg']
            );

            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log('Silo::updateRecarga FAIL recarga=' . $recargaId
                    . ' :: ' . $e->getMessage()
                    . ' @ ' . $e->getFile() . ':' . $e->getLine());
            throw $e;
        }
    }

    public function addRecarga(int $siloId, float $cantidadKg, string $fecha, ?string $proveedor, ?string $obs, int $userId, ?string $tipoPienso = null): int
    {
        $this->db->beginTransaction();
        try {
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
            $id = (int) $this->db->lastInsertId();

            // Consolidar stock y mover base_fecha → helper compartido.
            $this->applyRecargaEffect($siloId, $fecha, $cantidadKg);

            $this->db->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log('Silo::addRecarga FAIL silo=' . $siloId . ' fecha=' . $fecha . ' kg=' . $cantidadKg
                    . ' :: ' . $e->getMessage()
                    . ' @ ' . $e->getFile() . ':' . $e->getLine());
            throw $e;
        }
    }

    public function deleteRecarga(int $recargaId, int $siloId): void
    {
        $this->db->beginTransaction();
        try {
            // Cargar datos completos antes de borrar (fecha + cantidad).
            $stmt = $this->db->prepare(
                "SELECT fecha, cantidad_kg FROM silo_recargas WHERE id = :id AND silo_id = :silo_id"
            );
            $stmt->execute(['id' => $recargaId, 'silo_id' => $siloId]);
            $r = $stmt->fetch();
            if (!$r) { $this->db->rollBack(); return; }

            // 1. Revertir efecto sobre el silo (maneja el caso "era la más reciente").
            //    Pasamos $recargaId como exclude para que el MAX de fecha ignore esta fila,
            //    aunque técnicamente aún no la hemos borrado.
            $this->revertRecargaEffect(
                $siloId,
                (string)$r['fecha'],
                (float)$r['cantidad_kg'],
                $recargaId
            );

            // 2. Borrar la fila.
            $this->db->prepare("DELETE FROM silo_recargas WHERE id = :id")
                ->execute(['id' => $recargaId]);

            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log('Silo::deleteRecarga FAIL recarga=' . $recargaId . ' silo=' . $siloId
                    . ' :: ' . $e->getMessage()
                    . ' @ ' . $e->getFile() . ':' . $e->getLine());
            throw $e;
        }
    }

    // ── Helpers de stock ─────────────────────────────────────────

    /**
     * Aplica el efecto de una recarga sobre (stock_actual_kg, stock_base_fecha) del silo.
     *
     * Invariante que mantiene: `stock_base_fecha = MAX(recargas.fecha)` para el silo.
     *
     * - Si la recarga es posterior a la base actual: consolida el stock real hasta esa
     *   fecha y desplaza base_fecha hacia adelante.
     * - Si la recarga es anterior a la base actual (inserción retroactiva): suma la
     *   cantidad sin tocar base_fecha (la consolidación posterior ya absorberá el efecto
     *   linealmente).
     */
    private function applyRecargaEffect(int $siloId, string $recargaFecha, float $cantidadKg): void
    {
        $cur = $this->db->prepare("SELECT stock_actual_kg, stock_base_fecha FROM silos WHERE id = :id");
        $cur->execute(['id' => $siloId]);
        $silo = $cur->fetch();
        if (!$silo) return;

        $base       = (float)$silo['stock_actual_kg'];
        $baseFecha  = !empty($silo['stock_base_fecha']) ? (string)$silo['stock_base_fecha'] : $recargaFecha;

        if ($recargaFecha >= $baseFecha) {
            // Caso normal: recarga al día o posterior → consolidar y deslizar base_fecha.
            $consumo   = ($baseFecha < $recargaFecha)
                ? $this->consumoAcumulado($siloId, $baseFecha, $recargaFecha)
                : 0.0;
            $nuevoBase = max(0.0, $base - $consumo) + $cantidadKg;
            $nuevaFecha = $recargaFecha;
        } else {
            // Recarga retroactiva (fecha anterior a la base actual): suma lineal,
            // base_fecha no se mueve hacia atrás (perdería el historial consolidado).
            $nuevoBase  = $base + $cantidadKg;
            $nuevaFecha = $baseFecha;
        }

        $upd = $this->db->prepare("
            UPDATE silos SET stock_actual_kg = :stock, stock_base_fecha = :fecha WHERE id = :id
        ");
        $upd->execute(['stock' => $nuevoBase, 'fecha' => $nuevaFecha, 'id' => $siloId]);
    }

    /**
     * Revierte el efecto de una recarga sobre (stock_actual_kg, stock_base_fecha).
     *
     * Dos casos:
     *
     * A) La recarga era la MÁS RECIENTE del silo (fecha == stock_base_fecha).
     *    Hay que:
     *      1. Buscar la fecha máxima de las OTRAS recargas del silo.
     *      2. Si existe: mover base_fecha a esa fecha previa y recuperar el consumo
     *         que se había absorbido en la consolidación → `+ consumo(prev, fecha)`.
     *      3. Si no quedan otras recargas: simple resta, base_fecha se queda donde
     *         está (equivale a "el silo queda como si no hubiera ocurrido nada").
     *
     * B) La recarga NO era la más reciente. Su cantidad se fue sumando linealmente
     *    en las consolidaciones posteriores, así que basta con restarla. base_fecha
     *    no cambia.
     *
     * @param int $siloId        Silo afectado.
     * @param string $fecha      Fecha de la recarga que se revierte.
     * @param float $cantidad    Cantidad de la recarga que se revierte.
     * @param int $excludeId     ID de la recarga a excluir del MAX (para update/delete).
     */
    private function revertRecargaEffect(int $siloId, string $fecha, float $cantidad, int $excludeId): void
    {
        $cur = $this->db->prepare("SELECT stock_actual_kg, stock_base_fecha FROM silos WHERE id = :id");
        $cur->execute(['id' => $siloId]);
        $silo = $cur->fetch();
        if (!$silo) return;

        $base      = (float)$silo['stock_actual_kg'];
        $baseFecha = (string)($silo['stock_base_fecha'] ?? '');

        // ¿Era la recarga más reciente? (La que fija stock_base_fecha.)
        $eraMasReciente = ($baseFecha !== '' && $fecha === $baseFecha);

        if ($eraMasReciente) {
            // Buscar la fecha máxima de las OTRAS recargas del silo.
            $q = $this->db->prepare("
                SELECT MAX(fecha) FROM silo_recargas
                WHERE silo_id = :sid AND id != :rid
            ");
            $q->execute(['sid' => $siloId, 'rid' => $excludeId]);
            $prevFecha = $q->fetchColumn();

            if ($prevFecha) {
                // Mover base_fecha a la recarga anterior y recuperar el consumo
                // que se había absorbido al consolidar esta recarga.
                $recuperado = $this->consumoAcumulado($siloId, (string)$prevFecha, $fecha);
                $nuevoBase  = max(0.0, $base - $cantidad + $recuperado);
                $nuevaFecha = (string)$prevFecha;
            } else {
                // No quedan otras recargas: restar y dejar base_fecha donde estaba.
                // (No podemos reconstruir un estado pre-recarga sin historial adicional.)
                $nuevoBase  = max(0.0, $base - $cantidad);
                $nuevaFecha = $baseFecha;
            }
        } else {
            // No era la más reciente: consolidaciones posteriores absorbieron su cantidad
            // linealmente. Resta simple, base_fecha intacto.
            $nuevoBase  = max(0.0, $base - $cantidad);
            $nuevaFecha = $baseFecha;
        }

        $upd = $this->db->prepare("
            UPDATE silos SET stock_actual_kg = :stock, stock_base_fecha = :fecha WHERE id = :id
        ");
        $upd->execute(['stock' => $nuevoBase, 'fecha' => $nuevaFecha, 'id' => $siloId]);
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

        // Stock disponible hasta mínimo — usar el stock REAL (no el base)
        $siloData = $this->db->prepare("
            SELECT stock_actual_kg, stock_minimo_kg, stock_base_fecha
            FROM silos WHERE id = :id
        ");
        $siloData->execute(['id' => $siloId]);
        $s = $siloData->fetch();

        $stockReal = (float)$s['stock_actual_kg'];
        if (!empty($s['stock_base_fecha'])) {
            $stockReal = max(
                0.0,
                $stockReal - $this->consumoAcumulado($siloId, $s['stock_base_fecha'], date('Y-m-d'))
            );
        }
        $margen = max(0, $stockReal - (float)$s['stock_minimo_kg']);
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