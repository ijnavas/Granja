<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Etiquetas libres por usuario.
 *
 * Asignables a lotes y movimientos. El usuario las crea ad-hoc desde el
 * formulario (input "tag1, tag2, tag3"). syncForLote / syncForMovimiento
 * resuelven la lista entera: encuentran existentes, crean nuevas y dejan
 * solo las indicadas asociadas.
 */
class Etiqueta
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function allByUsuario(int $userId): array
    {
        // Etiquetas son de la organización (compartidas por miembros)
        $stmt = $this->db->prepare("
            SELECT * FROM etiquetas WHERE organizacion_id = :oid ORDER BY nombre
        ");
        $stmt->execute(['oid' => \App\Core\OrgContext::id()]);
        return $stmt->fetchAll();
    }

    public function findOrCreate(int $userId, string $nombre, ?string $color = null): int
    {
        $nombre = $this->normalizar($nombre);
        if ($nombre === '') return 0;
        $oid = \App\Core\OrgContext::id();

        $stmt = $this->db->prepare("SELECT id FROM etiquetas WHERE organizacion_id = :oid AND nombre = :n");
        $stmt->execute(['oid' => $oid, 'n' => $nombre]);
        $id = $stmt->fetchColumn();
        if ($id) return (int)$id;

        $stmt = $this->db->prepare("
            INSERT INTO etiquetas (usuario_id, organizacion_id, nombre, color) VALUES (:uid, :oid, :n, :c)
        ");
        $stmt->execute([
            'uid' => $userId,
            'oid' => $oid,
            'n'   => $nombre,
            'c'   => $color ?: $this->colorAuto($nombre),
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function delete(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM etiquetas WHERE id = :id AND organizacion_id = :oid");
        return $stmt->execute(['id' => $id, 'oid' => \App\Core\OrgContext::id()]);
    }

    /** Lista de tags (filas completas) asociadas a un lote. */
    public function forLote(int $loteId): array
    {
        $stmt = $this->db->prepare("
            SELECT e.* FROM etiquetas e
            JOIN lote_etiquetas le ON le.etiqueta_id = e.id
            WHERE le.lote_id = :id
            ORDER BY e.nombre
        ");
        $stmt->execute(['id' => $loteId]);
        return $stmt->fetchAll();
    }

    public function forMovimiento(int $movimientoId): array
    {
        $stmt = $this->db->prepare("
            SELECT e.* FROM etiquetas e
            JOIN movimiento_etiquetas me ON me.etiqueta_id = e.id
            WHERE me.movimiento_id = :id
            ORDER BY e.nombre
        ");
        $stmt->execute(['id' => $movimientoId]);
        return $stmt->fetchAll();
    }

    /**
     * Sincroniza la lista de tags asociados a un lote: crea las nuevas,
     * mantiene las existentes y desvincula las que ya no estén.
     *
     * @param string[] $nombres Lista de nombres de tag (no IDs).
     */
    public function syncForLote(int $loteId, int $userId, array $nombres): void
    {
        $ids = $this->resolverIds($userId, $nombres);
        $this->db->prepare("DELETE FROM lote_etiquetas WHERE lote_id = :id")->execute(['id' => $loteId]);
        if (empty($ids)) return;
        $stmt = $this->db->prepare("INSERT IGNORE INTO lote_etiquetas (lote_id, etiqueta_id) VALUES (:l, :e)");
        foreach ($ids as $eid) $stmt->execute(['l' => $loteId, 'e' => $eid]);
    }

    public function syncForMovimiento(int $movimientoId, int $userId, array $nombres): void
    {
        $ids = $this->resolverIds($userId, $nombres);
        $this->db->prepare("DELETE FROM movimiento_etiquetas WHERE movimiento_id = :id")->execute(['id' => $movimientoId]);
        if (empty($ids)) return;
        $stmt = $this->db->prepare("INSERT IGNORE INTO movimiento_etiquetas (movimiento_id, etiqueta_id) VALUES (:m, :e)");
        foreach ($ids as $eid) $stmt->execute(['m' => $movimientoId, 'e' => $eid]);
    }

    /**
     * Devuelve {lote_id => [tags]} para una lista de lote_ids — útil para
     * pintar pills en listados sin N+1 queries.
     */
    public function indexedByLoteIds(array $loteIds): array
    {
        $loteIds = array_values(array_filter(array_map('intval', $loteIds)));
        if (empty($loteIds)) return [];
        $ph = implode(',', array_fill(0, count($loteIds), '?'));
        $stmt = $this->db->prepare("
            SELECT le.lote_id, e.id, e.nombre, e.color
            FROM lote_etiquetas le
            JOIN etiquetas e ON e.id = le.etiqueta_id
            WHERE le.lote_id IN ({$ph})
            ORDER BY e.nombre
        ");
        $stmt->execute($loteIds);
        $out = [];
        while ($r = $stmt->fetch()) {
            $out[(int)$r['lote_id']][] = ['id' => (int)$r['id'], 'nombre' => $r['nombre'], 'color' => $r['color']];
        }
        return $out;
    }

    public function indexedByMovimientoIds(array $movIds): array
    {
        $movIds = array_values(array_filter(array_map('intval', $movIds)));
        if (empty($movIds)) return [];
        $ph = implode(',', array_fill(0, count($movIds), '?'));
        $stmt = $this->db->prepare("
            SELECT me.movimiento_id, e.id, e.nombre, e.color
            FROM movimiento_etiquetas me
            JOIN etiquetas e ON e.id = me.etiqueta_id
            WHERE me.movimiento_id IN ({$ph})
            ORDER BY e.nombre
        ");
        $stmt->execute($movIds);
        $out = [];
        while ($r = $stmt->fetch()) {
            $out[(int)$r['movimiento_id']][] = ['id' => (int)$r['id'], 'nombre' => $r['nombre'], 'color' => $r['color']];
        }
        return $out;
    }

    /** Convierte el string "tag1, tag2, otro" a array de nombres normalizados. */
    public static function parseInput(string $raw): array
    {
        $partes = preg_split('/[,;]+/', $raw) ?: [];
        $partes = array_map(fn($s) => trim((string)$s), $partes);
        $partes = array_filter($partes, fn($s) => $s !== '');
        return array_values(array_unique($partes));
    }

    private function resolverIds(int $userId, array $nombres): array
    {
        $ids = [];
        foreach ($nombres as $n) {
            $eid = $this->findOrCreate($userId, $n);
            if ($eid > 0) $ids[] = $eid;
        }
        return array_values(array_unique($ids));
    }

    private function normalizar(string $n): string
    {
        $n = trim($n);
        if ($n === '') return '';
        // Capitalizar primera, máximo 40
        $n = mb_substr($n, 0, 40, 'UTF-8');
        return mb_convert_case($n, MB_CASE_TITLE, 'UTF-8');
    }

    /** Color reproducible a partir del nombre (paleta acotada). */
    private function colorAuto(string $nombre): string
    {
        $paleta = [
            '#1d4ed8', '#15803d', '#b45309', '#dc2626', '#0e7490',
            '#9d174d', '#6b21a8', '#92400e', '#0369a1', '#166534',
        ];
        $h = abs(crc32($nombre));
        return $paleta[$h % count($paleta)];
    }
}
