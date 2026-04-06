<?php
// DIAGNÓSTICO TEMPORAL — BORRAR DESPUÉS
error_reporting(E_ALL);
ini_set('display_errors', '1');

define('ROOT_PATH', __DIR__);

echo '<h2>Diagnóstico Recevet</h2><pre>';

// 1. Versión PHP
echo "PHP: " . PHP_VERSION . "\n";

// 2. Extensiones
echo "cURL: "    . (function_exists('curl_init')       ? 'OK' : 'NO DISPONIBLE') . "\n";
echo "OpenSSL: " . (function_exists('openssl_encrypt') ? 'OK' : 'NO DISPONIBLE') . "\n";
echo "DOM: "     . (class_exists('DOMDocument')        ? 'OK' : 'NO DISPONIBLE') . "\n";

// 3. Cargar autoloader
echo "\nCargando autoloader...\n";
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $base   = ROOT_PATH . '/app/';
    if (!str_starts_with($class, $prefix)) return;
    $relative = substr($class, strlen($prefix));
    $file     = $base . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) require $file;
});

require ROOT_PATH . '/app/Helpers/functions.php';

// 4. Probar DB
echo "Probando DB...\n";
try {
    $db = \App\Core\Database::getInstance();
    echo "DB: OK\n";

    // Comprobar columnas
    $cols = $db->query("SHOW COLUMNS FROM usuarios")->fetchAll(\PDO::FETCH_COLUMN);
    echo "Columnas usuarios: " . implode(', ', $cols) . "\n";
    echo "recevet_usuario: "     . (in_array('recevet_usuario', $cols)     ? 'OK' : 'FALTA - ejecuta el SQL') . "\n";
    echo "recevet_password_enc: " . (in_array('recevet_password_enc', $cols) ? 'OK' : 'FALTA - ejecuta el SQL') . "\n";

    $colsG = $db->query("SHOW COLUMNS FROM granjas")->fetchAll(\PDO::FETCH_COLUMN);
    echo "recevet_explotacion (granjas): " . (in_array('recevet_explotacion', $colsG) ? 'OK' : 'FALTA - ejecuta el SQL') . "\n";

} catch (\Throwable $e) {
    echo "DB ERROR: " . $e->getMessage() . "\n";
}

// 5. Probar carga del servicio
echo "\nCargando RecevtService...\n";
try {
    $svc = new \App\Services\RecevtService();
    echo "RecevtService: OK\n";
} catch (\Throwable $e) {
    echo "RecevtService ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo '</pre>';
echo '<p style="color:red"><strong>BORRAR ESTE ARCHIVO DESPUÉS DEL DIAGNÓSTICO</strong></p>';
