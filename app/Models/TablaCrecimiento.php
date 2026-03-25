<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class TablaCrecimiento
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function allByUsuario(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT t.*,
                   COUNT(DISTINCT l.semana) AS num_semanas,
                   GROUP_CONCAT(r.nombre ORDER BY r.nombre SEPARATOR ', ') AS razas
            FROM tablas_crecimiento t
            LEFT JOIN tablas_crecimiento_lineas l ON l.tabla_id = t.id
            LEFT JOIN tabla_raza tr ON tr.tabla_id = t.id
            LEFT JOIN razas_porcino r ON tr.raza_id = r.id
            WHERE t.usuario_id = :uid AND t.activa = 1
            GROUP BY t.id
            ORDER BY t.nombre
        ");
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }

    public function find(int $id, int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM tablas_crecimiento WHERE id = :id AND usuario_id = :uid AND activa = 1
        ");
        $stmt->execute(['id' => $id, 'uid' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function lineas(int $tablaId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM tablas_crecimiento_lineas
            WHERE tabla_id = :id ORDER BY semana
        ");
        $stmt->execute(['id' => $tablaId]);
        return $stmt->fetchAll();
    }

    public function razasAsignadas(int $tablaId): array
    {
        $stmt = $this->db->prepare("
            SELECT raza_id FROM tabla_raza WHERE tabla_id = :id
        ");
        $stmt->execute(['id' => $tablaId]);
        return array_column($stmt->fetchAll(), 'raza_id');
    }

    public function create(int $userId, string $nombre, ?string $descripcion): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO tablas_crecimiento (usuario_id, nombre, descripcion)
            VALUES (:uid, :nombre, :descripcion)
        ");
        $stmt->execute(['uid' => $userId, 'nombre' => $nombre, 'descripcion' => $descripcion]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, string $nombre, ?string $descripcion): bool
    {
        $stmt = $this->db->prepare("
            UPDATE tablas_crecimiento SET nombre = :nombre, descripcion = :descripcion WHERE id = :id
        ");
        return $stmt->execute(['nombre' => $nombre, 'descripcion' => $descripcion, 'id' => $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE tablas_crecimiento SET activa = 0 WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    // ── Líneas ────────────────────────────────────────────────────
    public function upsertLinea(int $tablaId, int $semana, float $peso, ?int $consumo, ?float $coste): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO tablas_crecimiento_lineas (tabla_id, semana, peso_kg, consumo_acumulado_g, coste_eur)
            VALUES (:tabla_id, :semana, :peso_kg, :consumo, :coste)
            ON DUPLICATE KEY UPDATE
                peso_kg             = VALUES(peso_kg),
                consumo_acumulado_g = VALUES(consumo_acumulado_g),
                coste_eur           = VALUES(coste_eur)
        ");
        $stmt->execute([
            'tabla_id' => $tablaId,
            'semana'   => $semana,
            'peso_kg'  => $peso,
            'consumo'  => $consumo,
            'coste'    => $coste,
        ]);
    }

    public function deleteLinea(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM tablas_crecimiento_lineas WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function deleteTodasLineas(int $tablaId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM tablas_crecimiento_lineas WHERE tabla_id = :id");
        return $stmt->execute(['id' => $tablaId]);
    }

    // ── Razas asignadas ───────────────────────────────────────────
    public function syncRazas(int $tablaId, array $razaIds): void
    {
        $this->db->prepare("DELETE FROM tabla_raza WHERE tabla_id = :id")->execute(['id' => $tablaId]);
        if (empty($razaIds)) return;
        $stmt = $this->db->prepare("INSERT IGNORE INTO tabla_raza (tabla_id, raza_id) VALUES (:tid, :rid)");
        foreach ($razaIds as $rid) {
            $stmt->execute(['tid' => $tablaId, 'rid' => (int)$rid]);
        }
    }

    // ── Consulta por semana para un lote ─────────────────────────
    public function lineaPorSemana(int $tablaId, int $semana): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM tablas_crecimiento_lineas
            WHERE tabla_id = :tabla_id AND semana = :semana LIMIT 1
        ");
        $stmt->execute(['tabla_id' => $tablaId, 'semana' => $semana]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}