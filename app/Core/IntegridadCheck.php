<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Comprobaciones de integridad: bloquean operaciones que romperían
 * la "foto histórica" de inventarios u otras snapshots ya guardadas.
 *
 * Patrón uniforme: cada método devuelve un array de filas (vacío si OK,
 * con datos si la operación debe bloquearse). El controller decide qué
 * mensaje mostrar y a dónde redirigir.
 */
final class IntegridadCheck
{
    /**
     * Inventarios que dependen de un lote a partir de cierta fecha.
     * Si no es vacío → bloquear modificar/borrar el evento (movimiento,
     * pesaje, etc.) que afecta a ese lote en esa fecha.
     */
    public static function inventariosDeLoteDesde(int $loteId, string $fechaDesde, int $userId): array
    {
        $stmt = Database::getInstance()->prepare("
            SELECT DISTINCT i.id, i.fecha, i.nombre
            FROM inventarios i
            JOIN inventario_lineas il ON il.inventario_id = i.id
            WHERE i.usuario_id = :uid
              AND i.fecha >= :fecha
              AND il.lote_id = :lote
            ORDER BY i.fecha
        ");
        $stmt->execute(['uid' => $userId, 'fecha' => $fechaDesde, 'lote' => $loteId]);
        return $stmt->fetchAll();
    }

    /**
     * Inventarios que en cualquier fecha contienen un lote dado.
     * Útil cuando un cambio (p.ej. fecha_nacimiento del lote) afecta
     * a cualquier inventario, no solo posteriores.
     */
    public static function inventariosDeLote(int $loteId, int $userId): array
    {
        $stmt = Database::getInstance()->prepare("
            SELECT DISTINCT i.id, i.fecha, i.nombre
            FROM inventarios i
            JOIN inventario_lineas il ON il.inventario_id = i.id
            WHERE i.usuario_id = :uid AND il.lote_id = :lote
            ORDER BY i.fecha
        ");
        $stmt->execute(['uid' => $userId, 'lote' => $loteId]);
        return $stmt->fetchAll();
    }

    /**
     * Asignaciones cuadra_lote activas en una cuadra. Si no es vacío,
     * la cuadra no debe poder borrarse hasta vaciarla.
     */
    public static function lotesActivosEnCuadra(int $cuadraId): array
    {
        $stmt = Database::getInstance()->prepare("
            SELECT cl.lote_id, cl.num_animales, l.codigo
            FROM cuadra_lote cl
            JOIN lotes l ON l.id = cl.lote_id
            WHERE cl.cuadra_id = :c AND cl.activo = 1 AND cl.num_animales > 0
            ORDER BY l.codigo
        ");
        $stmt->execute(['c' => $cuadraId]);
        return $stmt->fetchAll();
    }

    /**
     * Lotes activos asignados a CUALQUIER cuadra de una nave. Si no
     * está vacío, la nave no debe poder borrarse hasta vaciarla.
     */
    public static function lotesActivosEnNave(int $naveId): array
    {
        $stmt = Database::getInstance()->prepare("
            SELECT DISTINCT l.id, l.codigo, c.nombre AS cuadra
            FROM cuadra_lote cl
            JOIN cuadras c ON cl.cuadra_id = c.id
            JOIN lotes l   ON cl.lote_id   = l.id
            WHERE c.nave_id = :n AND cl.activo = 1 AND cl.num_animales > 0
            ORDER BY c.nombre, l.codigo
        ");
        $stmt->execute(['n' => $naveId]);
        return $stmt->fetchAll();
    }

    /**
     * Inventarios cuyas líneas dependen de una tabla de crecimiento dada.
     * Se llega vía: inventario_lineas → lote → raza → tabla_raza → tabla.
     *
     * Cambiar peso/coste en la tabla altera retroactivamente lo que se vería
     * para los lotes que la usan, contradiciendo lo congelado en estos
     * inventarios.
     */
    public static function inventariosDeTabla(int $tablaId, int $userId): array
    {
        $stmt = Database::getInstance()->prepare("
            SELECT DISTINCT i.id, i.fecha, i.nombre
            FROM inventarios i
            JOIN inventario_lineas il ON il.inventario_id = i.id
            JOIN lotes l              ON il.lote_id       = l.id
            JOIN tabla_raza tr        ON tr.raza_id       = l.raza_id
            WHERE i.usuario_id = :uid AND tr.tabla_id = :tabla
            ORDER BY i.fecha
        ");
        $stmt->execute(['uid' => $userId, 'tabla' => $tablaId]);
        return $stmt->fetchAll();
    }

    /** Mensaje legible para una lista de inventarios. */
    public static function mensajeInventarios(array $invs, string $accion = 'modificar/eliminar'): string
    {
        $nombres = array_map(function($i) {
            $label = !empty($i['nombre']) ? $i['nombre'] : ('Inventario #' . $i['id']);
            return $label . ' (' . date('d/m/Y', strtotime($i['fecha'])) . ')';
        }, $invs);
        return 'No se puede ' . $accion . ': hay ' . count($invs)
            . ' inventario(s) que dependen de este registro. Borra primero: '
            . implode(', ', $nombres) . '.';
    }
}
