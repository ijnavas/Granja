<?php
declare(strict_types=1);

/**
 * Snapshot diario de stock de silos.
 *
 * Ejecutar una vez al día (típicamente poco después de medianoche) para
 * persistir en silo_stock_historico el estado calculado por el modelo replay.
 *
 * Por cada silo activo:
 *   - stock_kg       = rebuildStockAt(silo, HOY)
 *   - consumo_dia_kg = rebuildStockAt(silo, AYER) + recargas(HOY) - rebuildStockAt(silo, HOY)
 *                      (aprox: no contabiliza recargas del día actual como consumo)
 *   - num_animales   = suma de animales en lotes activos de naves abastecidas
 *
 * Idempotente: UNIQUE(silo_id, fecha) + INSERT ... ON DUPLICATE KEY UPDATE.
 * Seguro: guard CLI para impedir ejecución vía web.
 *
 * Uso:
 *   php scripts/snapshot_silos.php                 # snapshot de hoy
 *   php scripts/snapshot_silos.php --date=2026-04-07  # backfill de un día concreto
 *
 * Cron Dinahosting (diario 00:05):
 *   /usr/bin/php /home/baltae/<RUTA_PROYECTO>/scripts/snapshot_silos.php
 */

// ── Guard anti-web ───────────────────────────────────────────
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

// ── Bootstrap mínimo (réplica del de index.php sin routing) ──
define('ROOT_PATH', dirname(__DIR__));

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $base   = ROOT_PATH . '/app/';
    if (!str_starts_with($class, $prefix)) return;
    $relative = substr($class, strlen($prefix));
    $file     = $base . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) require $file;
});

require ROOT_PATH . '/app/Helpers/functions.php';
\App\Core\Env::load(ROOT_PATH . '/.env');

// ── Logging con timestamp ────────────────────────────────────
$logLine = function (string $msg): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . "\n";
};

// ── Parseo de argumentos ─────────────────────────────────────
$fechaObjetivo = date('Y-m-d');
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--date=')) {
        $v = substr($arg, 7);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            fwrite(STDERR, "Formato de fecha inválido: $v (esperado YYYY-MM-DD)\n");
            exit(1);
        }
        $fechaObjetivo = $v;
    }
}
$fechaAnterior = date('Y-m-d', strtotime($fechaObjetivo . ' -1 day'));

$logLine("Iniciando snapshot para fecha $fechaObjetivo (comparando con $fechaAnterior)");

try {
    $db = \App\Core\Database::getInstance();
} catch (\Throwable $e) {
    fwrite(STDERR, "Error de conexión a BD: " . $e->getMessage() . "\n");
    exit(1);
}

// ── Silos activos ────────────────────────────────────────────
$silos = $db->query("
    SELECT s.id, s.nombre, g.nombre AS granja_nombre
    FROM silos s
    JOIN granjas g ON s.granja_id = g.id
    WHERE s.activo = 1
    ORDER BY g.nombre, s.nombre
")->fetchAll();

if (!$silos) {
    $logLine("No hay silos activos. Nada que hacer.");
    exit(0);
}

$logLine("Encontrados " . count($silos) . " silos activos.");

$siloModel = new \App\Models\Silo();

// Preparar statements reusables.
$countAnimalesStmt = $db->prepare("
    SELECT COALESCE(SUM(l.num_animales), 0) AS total
    FROM silo_nave sn
    JOIN lotes l ON l.nave_id = sn.nave_id
               AND l.estado = 'activo'
               AND l.fecha_nacimiento IS NOT NULL
    WHERE sn.silo_id = :silo_id
");

$recargasDelDiaStmt = $db->prepare("
    SELECT COALESCE(SUM(cantidad_kg), 0) AS total
    FROM silo_recargas
    WHERE silo_id = :silo_id AND fecha = :fecha
");

$upsertStmt = $db->prepare("
    INSERT INTO silo_stock_historico
        (silo_id, fecha, stock_kg, consumo_dia_kg, num_animales)
    VALUES
        (:silo_id, :fecha, :stock_kg, :consumo_dia_kg, :num_animales)
    ON DUPLICATE KEY UPDATE
        stock_kg       = VALUES(stock_kg),
        consumo_dia_kg = VALUES(consumo_dia_kg),
        num_animales   = VALUES(num_animales)
");

$procesados = 0;
$errores    = 0;

foreach ($silos as $silo) {
    $sid    = (int)$silo['id'];
    $etiqueta = "[{$silo['granja_nombre']} / {$silo['nombre']} #$sid]";

    try {
        $stockHoy  = $siloModel->rebuildStockAt($sid, $fechaObjetivo);
        $stockAyer = $siloModel->rebuildStockAt($sid, $fechaAnterior);

        $recargasDelDiaStmt->execute(['silo_id' => $sid, 'fecha' => $fechaObjetivo]);
        $recargasHoy = (float)$recargasDelDiaStmt->fetchColumn();

        // Consumo del día = (stock ayer + recargas de hoy) - stock hoy
        // Así aislamos el efecto del consumo, ignorando el "efecto recarga".
        // max(0,…) por si hay calibración manual que desvía el cálculo.
        $consumoDia = max(0.0, ($stockAyer + $recargasHoy) - $stockHoy);

        $countAnimalesStmt->execute(['silo_id' => $sid]);
        $numAnimales = (int)$countAnimalesStmt->fetchColumn();

        $upsertStmt->execute([
            'silo_id'        => $sid,
            'fecha'          => $fechaObjetivo,
            'stock_kg'       => round($stockHoy, 2),
            'consumo_dia_kg' => round($consumoDia, 2),
            'num_animales'   => $numAnimales,
        ]);

        $logLine(sprintf(
            "%s stock=%.2f kg · consumo_dia=%.2f kg · animales=%d",
            $etiqueta, $stockHoy, $consumoDia, $numAnimales
        ));
        $procesados++;
    } catch (\Throwable $e) {
        $errores++;
        $msg = "$etiqueta ERROR: " . $e->getMessage();
        $logLine($msg);
        error_log('[snapshot_silos] ' . $msg);
    }
}

$logLine("Hecho. Procesados=$procesados Errores=$errores");
exit($errores > 0 ? 2 : 0);
