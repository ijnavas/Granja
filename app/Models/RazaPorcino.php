<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class RazaPorcino
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function allParaUsuario(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM razas_porcino
            WHERE (usuario_id IS NULL OR usuario_id = :uid) AND activa = 1
            ORDER BY usuario_id IS NULL DESC, nombre
        ");
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Devuelve el sufijo corto para el código del lote (ej: "IB", "DU")
     */
    public function sufijoCodigo(int $razaId): ?string
    {
        $stmt = $this->db->prepare("SELECT nombre, porcentaje FROM razas_porcino WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $razaId]);
        $raza = $stmt->fetch();
        if (!$raza) return null;

        // Generar siglas automáticas: primeras 2 letras en mayúscula
        $palabras = explode(' ', $raza['nombre']);
        $siglas   = '';
        foreach ($palabras as $p) {
            if (strlen($p) >= 2) $siglas .= strtoupper(substr($p, 0, 2));
            if (strlen($siglas) >= 2) break;
        }
        return substr($siglas, 0, 2);
    }

    public function create(int $userId, string $nombre, ?string $porcentaje): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO razas_porcino (usuario_id, nombre, porcentaje)
            VALUES (:usuario_id, :nombre, :porcentaje)
        ");
        $stmt->execute([
            'usuario_id' => $userId,
            'nombre'     => capitalizar($nombre),
            'porcentaje' => $porcentaje ?: null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function delete(int $id, int $userId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE razas_porcino SET activa = 0
            WHERE id = :id AND usuario_id = :uid
        ");
        return $stmt->execute(['id' => $id, 'uid' => $userId]);
    }
}
