<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class ConfiguracionGranja
{
    private PDO $db;

    public const DEFAULT_DIAS_AVISO   = 7;
    public const DEFAULT_PCT_DESVIO   = 15;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function get(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM configuracion_granja WHERE usuario_id = :uid");
        $stmt->execute(['uid' => $userId]);
        $row = $stmt->fetch();
        return [
            'usuario_id'                  => $userId,
            'dias_advertencia_movimiento' => (int)($row['dias_advertencia_movimiento'] ?? self::DEFAULT_DIAS_AVISO),
            'pct_desviacion_peso_tabla'   => (int)($row['pct_desviacion_peso_tabla']   ?? self::DEFAULT_PCT_DESVIO),
        ];
    }

    public function save(int $userId, int $dias, int $pct): void
    {
        // Clamps razonables
        $dias = max(0, min(365, $dias));
        $pct  = max(0, min(100, $pct));

        $stmt = $this->db->prepare("
            INSERT INTO configuracion_granja (usuario_id, dias_advertencia_movimiento, pct_desviacion_peso_tabla)
            VALUES (:uid, :d, :p)
            ON DUPLICATE KEY UPDATE
                dias_advertencia_movimiento = :d2,
                pct_desviacion_peso_tabla   = :p2
        ");
        $stmt->execute(['uid' => $userId, 'd' => $dias, 'p' => $pct, 'd2' => $dias, 'p2' => $pct]);
    }
}
