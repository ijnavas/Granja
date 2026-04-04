<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Session;
use App\Models\Lote;
use PDO;

class DashboardController extends BaseController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function index(): void
    {
        auth_required();
        $userId = Session::get('usuario_id');

        // Transición automática lechón → cebo (≥22 kg según tabla)
        (new Lote())->actualizarEstadoLechonACebo($userId);

        $data = [
            'kpis'         => $this->getKpis($userId),
            'alertas'      => $this->getAlertas($userId),
            'movimientos'  => $this->getUltimosMovimientos($userId),
            'naves'        => $this->getOcupacionNaves($userId),
            'matadero'     => $this->getProximosMatadero($userId),
            'pageTitle'    => 'Dashboard',
        ];

        $this->view('dashboard/index', $data, 'main');
    }

    // ── KPIs globales ────────────────────────────────────────────
    private function getKpis(int $userId): array
    {
        // Total animales vivos en lotes activos del usuario
        $stmt = $this->db->prepare("
            SELECT
                COALESCE(SUM(l.num_animales), 0) AS total_animales,
                COUNT(DISTINCT l.id)              AS total_lotes,
                COUNT(DISTINCT n.granja_id)       AS total_granjas
            FROM lotes l
            JOIN naves n ON l.nave_id = n.id
            JOIN granjas g ON n.granja_id = g.id
            WHERE g.usuario_id = :uid
              AND l.estado = 'activo'
        ");
        $stmt->execute(['uid' => $userId]);
        $totales = $stmt->fetch();

        // Bajas última semana
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(m.num_animales), 0) AS bajas_semana
            FROM movimientos m
            JOIN lotes l ON m.lote_id = l.id
            JOIN naves n ON l.nave_id = n.id
            JOIN granjas g ON n.granja_id = g.id
            WHERE g.usuario_id = :uid
              AND m.tipo = 'baja'
              AND m.fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ");
        $stmt->execute(['uid' => $userId]);
        $bajasSemana = (int) $stmt->fetchColumn();

        // Bajas último mes
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(m.num_animales), 0) AS bajas_mes
            FROM movimientos m
            JOIN lotes l ON m.lote_id = l.id
            JOIN naves n ON l.nave_id = n.id
            JOIN granjas g ON n.granja_id = g.id
            WHERE g.usuario_id = :uid
              AND m.tipo = 'baja'
              AND m.fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ");
        $stmt->execute(['uid' => $userId]);
        $bajasMes = (int) $stmt->fetchColumn();

        // IC medio de lotes activos con pesajes
        $stmt = $this->db->prepare("
            SELECT ROUND(AVG(p.ic_real), 3) AS ic_medio
            FROM pesajes p
            JOIN lotes l ON p.lote_id = l.id
            JOIN naves n ON l.nave_id = n.id
            JOIN granjas g ON n.granja_id = g.id
            WHERE g.usuario_id = :uid
              AND l.estado = 'activo'
              AND p.ic_real IS NOT NULL
        ");
        $stmt->execute(['uid' => $userId]);
        $icMedio = $stmt->fetchColumn();

        return [
            'total_animales' => (int) $totales['total_animales'],
            'total_lotes'    => (int) $totales['total_lotes'],
            'total_granjas'  => (int) $totales['total_granjas'],
            'bajas_semana'   => $bajasSemana,
            'bajas_mes'      => $bajasMes,
            'ic_medio'       => $icMedio ?: '—',
        ];
    }

    // ── Alertas ──────────────────────────────────────────────────
    private function getAlertas(int $userId): array
    {
        $alertas = [];

        // Silos por debajo del mínimo
        $stmt = $this->db->prepare("
            SELECT s.nombre, g.nombre AS granja,
                   s.stock_actual_kg, s.stock_minimo_kg,
                   ROUND((s.stock_actual_kg / s.capacidad_kg) * 100) AS pct
            FROM silos s
            JOIN granjas g ON s.granja_id = g.id
            WHERE g.usuario_id = :uid
              AND s.activo = 1
              AND s.stock_actual_kg <= s.stock_minimo_kg
            ORDER BY pct ASC
        ");
        $stmt->execute(['uid' => $userId]);
        foreach ($stmt->fetchAll() as $row) {
            $alertas[] = [
                'tipo'     => 'pienso',
                'nivel'    => 'danger',
                'mensaje'  => "Silo <strong>{$row['nombre']}</strong> ({$row['granja']}): {$row['stock_actual_kg']} kg — {$row['pct']}% de capacidad",
            ];
        }

        // Bajas totales por lote esta semana
        $stmt = $this->db->prepare("
            SELECT l.codigo, g.nombre AS granja, n.nombre AS nave,
                   SUM(m.num_animales) AS total_bajas,
                   l.num_animales AS animales_lote
            FROM movimientos m
            JOIN lotes l ON m.lote_id = l.id
            JOIN naves n ON l.nave_id = n.id
            JOIN granjas g ON n.granja_id = g.id
            WHERE g.usuario_id = :uid
              AND m.tipo = 'baja'
              AND m.fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY l.id, l.codigo, g.nombre, n.nombre, l.num_animales
            ORDER BY total_bajas DESC
        ");
        $stmt->execute(['uid' => $userId]);
        foreach ($stmt->fetchAll() as $row) {
            $pct = $row['animales_lote'] > 0
                ? round(($row['total_bajas'] / $row['animales_lote']) * 100, 1)
                : 0;
            $nivel = $pct >= 5 ? 'danger' : ($pct >= 2 ? 'warning' : 'info');
            $alertas[] = [
                'tipo'    => 'baja',
                'nivel'   => $nivel,
                'mensaje' => "Lote <strong>{$row['codigo']}</strong> ({$row['nave']}, {$row['granja']}): {$row['total_bajas']} bajas esta semana ({$pct}%)",
            ];
        }

        // Lotes con desviación de peso > 10% respecto a tabla
        $stmt = $this->db->prepare("
            SELECT l.codigo, n.nombre AS nave, g.nombre AS granja,
                   p.peso_medio_kg AS peso_real,
                   lt.peso_esperado_kg AS peso_esperado,
                   ROUND(((p.peso_medio_kg - lt.peso_esperado_kg) / lt.peso_esperado_kg) * 100, 1) AS desviacion_pct
            FROM lotes l
            JOIN naves n ON l.nave_id = n.id
            JOIN granjas g ON n.granja_id = g.id
            JOIN tablas_crecimiento tc ON g.tabla_crecimiento_id = tc.id
            JOIN (
                SELECT lote_id, peso_medio_kg, fecha
                FROM pesajes
                WHERE (lote_id, fecha) IN (
                    SELECT lote_id, MAX(fecha) FROM pesajes GROUP BY lote_id
                )
            ) p ON p.lote_id = l.id
            JOIN lineas_tabla lt ON lt.tabla_id = tc.id
                AND lt.dia = DATEDIFF(CURDATE(), l.fecha_entrada)
            WHERE g.usuario_id = :uid
              AND l.estado = 'activo'
              AND ABS((p.peso_medio_kg - lt.peso_esperado_kg) / lt.peso_esperado_kg) > 0.10
            ORDER BY desviacion_pct ASC
        ");
        $stmt->execute(['uid' => $userId]);
        foreach ($stmt->fetchAll() as $row) {
            $signo = $row['desviacion_pct'] > 0 ? '+' : '';
            $nivel = $row['desviacion_pct'] < -15 ? 'danger' : 'warning';
            $alertas[] = [
                'tipo'    => 'peso',
                'nivel'   => $nivel,
                'mensaje' => "Lote <strong>{$row['codigo']}</strong> ({$row['nave']}): peso real {$row['peso_real']} kg vs esperado {$row['peso_esperado']} kg ({$signo}{$row['desviacion_pct']}%)",
            ];
        }

        return $alertas;
    }

    // ── Próximos a matadero ──────────────────────────────────────
    private function getProximosMatadero(int $userId): array
    {
        // Previsión basada en tabla de crecimiento: semana en que el lote alcanza 150 kg
        // Se aplica un 3% de bajas estimadas sobre el número de animales actual
        $stmt = $this->db->prepare("
            SELECT
                l.id,
                l.codigo,
                l.num_animales,
                l.fecha_nacimiento,
                COALESCE(n.nombre, '—')                                   AS nave,
                g.nombre                                                  AS granja,
                CEIL(DATEDIFF(CURDATE(), l.fecha_nacimiento) / 7)        AS semana_actual,
                MIN(tcl.semana)                                           AS semana_matadero
            FROM lotes l
            JOIN granjas g                   ON l.granja_id  = g.id
            LEFT JOIN naves n                ON l.nave_id    = n.id
            JOIN tabla_raza tr               ON tr.raza_id   = l.raza_id
            JOIN tablas_crecimiento tc       ON tc.id        = tr.tabla_id AND tc.activa = 1
            JOIN tablas_crecimiento_lineas tcl ON tcl.tabla_id = tc.id
                AND tcl.peso_kg >= 150
            WHERE g.usuario_id   = :uid
              AND l.estado       = 'activo'
              AND l.raza_id      IS NOT NULL
              AND l.fecha_nacimiento IS NOT NULL
            GROUP BY l.id, l.codigo, l.num_animales, l.fecha_nacimiento, n.nombre, g.nombre
            ORDER BY (MIN(tcl.semana) - CEIL(DATEDIFF(CURDATE(), l.fecha_nacimiento) / 7)) ASC
        ");
        $stmt->execute(['uid' => $userId]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$r) {
            $semActual   = (int) $r['semana_actual'];
            $semMatadero = (int) $r['semana_matadero'];
            $semRest     = $semMatadero - $semActual;

            $r['semanas_restantes']  = $semRest;
            $r['animales_estimados'] = (int) round($r['num_animales'] * 0.97);
            // Fecha anclada a fecha_nacimiento + semana_matadero semanas (siempre semana entera)
            $r['fecha_estimada']     = $semRest <= 0
                ? 'Listo'
                : date('d/m/Y', strtotime($r['fecha_nacimiento'] . " +{$semMatadero} weeks"));
        }

        return $rows;
    }

    // ── Últimos movimientos ───────────────────────────────────────
    private function getUltimosMovimientos(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT m.tipo, m.num_animales, m.fecha, m.motivo,
                   l.codigo AS lote,
                   n.nombre AS nave,
                   g.nombre AS granja,
                   nd.nombre AS nave_destino
            FROM movimientos m
            JOIN lotes l ON m.lote_id = l.id
            JOIN naves n ON l.nave_id = n.id
            JOIN granjas g ON n.granja_id = g.id
            LEFT JOIN naves nd ON m.nave_destino_id = nd.id
            WHERE g.usuario_id = :uid
            ORDER BY m.fecha DESC, m.id DESC
            LIMIT 10
        ");
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }

    // ── Ocupación de naves ────────────────────────────────────────
    private function getOcupacionNaves(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT n.nombre AS nave, g.nombre AS granja,
                   n.capacidad_maxima,
                   COALESCE(SUM(l.num_animales), 0) AS ocupacion_actual,
                   ROUND((COALESCE(SUM(l.num_animales), 0) / NULLIF(n.capacidad_maxima, 0)) * 100) AS pct_ocupacion
            FROM naves n
            JOIN granjas g ON n.granja_id = g.id
            LEFT JOIN lotes l ON l.nave_id = n.id AND l.estado = 'activo'
            WHERE g.usuario_id = :uid
              AND n.activa = 1
            GROUP BY n.id, n.nombre, g.nombre, n.capacidad_maxima
            ORDER BY g.nombre, n.nombre
        ");
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }
}
