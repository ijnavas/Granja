<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Generador de CSV descargable compatible con Excel (BOM UTF-8, separador ;).
 *
 * Uso:
 *   CsvExport::download('lotes_2026-04-10.csv', $cabeceras, $filas);
 */
class CsvExport
{
    /**
     * Envía un CSV como descarga y termina la ejecución.
     *
     * @param string   $filename  Nombre del archivo descargado
     * @param string[] $headers   Cabeceras de columna
     * @param array[]  $rows      Array de arrays (cada sub-array es una fila)
     */
    public static function download(string $filename, array $headers, array $rows): never
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Cache-Control: no-store, no-cache');

        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF"); // BOM UTF-8 para Excel

        fputcsv($out, $headers, ';');
        foreach ($rows as $row) {
            fputcsv($out, $row, ';');
        }

        fclose($out);
        exit;
    }

    /**
     * Formatea un número para CSV español (coma decimal, sin separador de miles).
     */
    public static function num(float|int|null $value, int $decimals = 2): string
    {
        if ($value === null || $value === 0.0) return '';
        return number_format((float)$value, $decimals, ',', '');
    }
}
