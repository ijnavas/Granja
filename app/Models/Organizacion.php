<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * Modelo SaaS multi-organización. Un usuario puede pertenecer a varias
 * organizaciones; en cada una tiene un rol independiente.
 */
class Organizacion
{
    private PDO $db;

    public const ROLES = ['owner', 'admin', 'operario', 'lector'];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM organizaciones WHERE id = :id AND activa = 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function create(string $nombre): int
    {
        $stmt = $this->db->prepare("INSERT INTO organizaciones (nombre) VALUES (:n)");
        $stmt->execute(['n' => $nombre]);
        return (int) $this->db->lastInsertId();
    }

    /** Lista las organizaciones a las que pertenece un usuario, con su rol. */
    public function deUsuario(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT o.id, o.nombre, o.plan, ou.rol
            FROM organizaciones o
            JOIN organizacion_usuarios ou ON ou.organizacion_id = o.id
            WHERE ou.usuario_id = :uid AND o.activa = 1
            ORDER BY o.nombre
        ");
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }

    public function rolEnOrg(int $userId, int $orgId): ?string
    {
        $stmt = $this->db->prepare("
            SELECT rol FROM organizacion_usuarios WHERE usuario_id = :uid AND organizacion_id = :oid
        ");
        $stmt->execute(['uid' => $userId, 'oid' => $orgId]);
        $r = $stmt->fetchColumn();
        return $r ?: null;
    }

    public function vincular(int $orgId, int $userId, string $rol = 'operario', ?int $invitadoPor = null): void
    {
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO organizacion_usuarios (organizacion_id, usuario_id, rol, invitado_por)
            VALUES (:o, :u, :r, :p)
        ");
        $stmt->execute(['o' => $orgId, 'u' => $userId, 'r' => $rol, 'p' => $invitadoPor]);
    }

    public function desvincular(int $orgId, int $userId): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM organizacion_usuarios WHERE organizacion_id = :o AND usuario_id = :u AND rol != 'owner'
        ");
        $stmt->execute(['o' => $orgId, 'u' => $userId]);
        return $stmt->rowCount() > 0;
    }

    public function cambiarRol(int $orgId, int $userId, string $rol): bool
    {
        if (!in_array($rol, self::ROLES, true)) return false;
        $stmt = $this->db->prepare("
            UPDATE organizacion_usuarios SET rol = :r
            WHERE organizacion_id = :o AND usuario_id = :u AND rol != 'owner'
        ");
        $stmt->execute(['o' => $orgId, 'u' => $userId, 'r' => $rol]);
        return $stmt->rowCount() > 0;
    }

    /** Miembros de una organización con sus datos básicos. */
    public function miembros(int $orgId): array
    {
        $stmt = $this->db->prepare("
            SELECT u.id, u.nombre, u.email, ou.rol, ou.created_at
            FROM organizacion_usuarios ou
            JOIN usuarios u ON ou.usuario_id = u.id
            WHERE ou.organizacion_id = :o
            ORDER BY FIELD(ou.rol, 'owner','admin','operario','lector'), u.nombre
        ");
        $stmt->execute(['o' => $orgId]);
        return $stmt->fetchAll();
    }

    /**
     * IDs de granjas asignadas explícitamente a un usuario en una org.
     * Devuelve [] si no hay restricción (ve todas) o lista de IDs.
     */
    public function granjasAsignadas(int $userId, int $orgId): array
    {
        $stmt = $this->db->prepare("
            SELECT granja_id FROM granja_miembros
            WHERE usuario_id = :u AND organizacion_id = :o
        ");
        $stmt->execute(['u' => $userId, 'o' => $orgId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Reemplaza las granjas asignadas a un usuario en una org.
     * Si $granjaIds está vacío, deja al usuario "sin restricción" (ve todas).
     */
    public function setGranjasAsignadas(int $userId, int $orgId, array $granjaIds): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->prepare("DELETE FROM granja_miembros WHERE usuario_id = :u AND organizacion_id = :o")
                     ->execute(['u' => $userId, 'o' => $orgId]);
            if (!empty($granjaIds)) {
                $stmt = $this->db->prepare("
                    INSERT IGNORE INTO granja_miembros (granja_id, usuario_id, organizacion_id)
                    VALUES (:g, :u, :o)
                ");
                foreach ($granjaIds as $g) {
                    $stmt->execute(['g' => (int)$g, 'u' => $userId, 'o' => $orgId]);
                }
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Garantiza que el usuario tenga al menos una organización. Si no
     * tiene, crea una "personal" (su nombre + ' (personal)') donde es
     * owner, y migra sus granjas/inventarios/etiquetas/razas/tablas a
     * esa org. Devuelve el org_id que el usuario tendrá como activa.
     */
    public function ensureOrgForUsuario(int $userId, string $userName): int
    {
        // ¿Ya tiene alguna?
        $stmt = $this->db->prepare("SELECT organizacion_id FROM organizacion_usuarios WHERE usuario_id = :u ORDER BY rol = 'owner' DESC, organizacion_id ASC LIMIT 1");
        $stmt->execute(['u' => $userId]);
        $orgId = $stmt->fetchColumn();
        if ($orgId) return (int)$orgId;

        // Crear org personal
        $orgId = $this->create("Org de {$userName}");
        $this->vincular($orgId, $userId, 'owner');

        // Migrar datos legacy: backfill organizacion_id de las tablas que
        // hasta ahora filtraban por usuario_id.
        $tablas = ['granjas', 'inventarios', 'razas_porcino', 'tablas_crecimiento', 'etiquetas'];
        foreach ($tablas as $t) {
            try {
                $this->db->prepare("UPDATE {$t} SET organizacion_id = :o WHERE usuario_id = :u AND organizacion_id IS NULL")
                         ->execute(['o' => $orgId, 'u' => $userId]);
            } catch (\Throwable $e) { /* tabla puede no tener la columna aún */ }
        }
        return (int)$orgId;
    }
}
