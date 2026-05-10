<?php
declare(strict_types=1);

namespace App\Core;

use App\Models\Organizacion;

/**
 * Contexto de organización activa del request actual.
 *
 * Toda query de "datos del cliente" (granjas, lotes, inventarios...)
 * se filtra por la org activa, no por el usuario. El usuario sigue
 * siendo el "actor" que registra cambios (audit), pero la pertenencia
 * de los datos es de la organización.
 *
 * El org_id se guarda en sesión al login y al cambiar de org.
 */
final class OrgContext
{
    /**
     * Devuelve el org_id activo del request (0 si el usuario aún no
     * pertenece a ninguna organización). Usa cache de sesión y, si está
     * vacío, intenta resolverlo con ensureOrgForUsuario (sólo migra usuarios
     * legacy; los usuarios nuevos sin invitación aceptada devuelven 0).
     */
    public static function id(): int
    {
        $orgId = (int) (Session::get('current_org_id') ?? 0);
        if ($orgId > 0) return $orgId;

        $userId = (int) (Session::get('usuario_id') ?? 0);
        if ($userId <= 0) return 0;

        $userName = (string) (Session::get('usuario_nombre') ?? 'Usuario');
        $orgModel = new Organizacion();
        $orgId    = $orgModel->ensureOrgForUsuario($userId, $userName);
        if (!$orgId) return 0;

        $rol = $orgModel->rolEnOrg($userId, (int)$orgId) ?? 'operario';
        Session::set('current_org_id',  (int)$orgId);
        Session::set('current_org_rol', $rol);
        return (int)$orgId;
    }

    /** Rol del usuario actual en la org activa. */
    public static function rol(): string
    {
        $rol = Session::get('current_org_rol');
        if ($rol) return (string) $rol;

        // Lazy: forzar id() para inicializar
        self::id();
        return (string) (Session::get('current_org_rol') ?? 'operario');
    }

    public static function esOwner(): bool { return self::rol() === 'owner'; }
    public static function esAdmin(): bool { return in_array(self::rol(), ['owner', 'admin'], true); }
    public static function esOperarioOSuperior(): bool { return in_array(self::rol(), ['owner', 'admin', 'operario'], true); }
    public static function esLector(): bool { return self::rol() === 'lector'; }

    /** Cambia la org activa (debe pertenecer al usuario). */
    public static function cambiar(int $newOrgId): bool
    {
        $userId = (int) (Session::get('usuario_id') ?? 0);
        if ($userId <= 0) return false;

        $rol = (new Organizacion())->rolEnOrg($userId, $newOrgId);
        if (!$rol) return false;

        Session::set('current_org_id',  $newOrgId);
        Session::set('current_org_rol', $rol);
        return true;
    }

    /**
     * IDs de granjas visibles para el usuario actual en la org activa.
     *
     * Devuelve:
     *   - null  → ver todas (owner/admin, o operario/lector sin restricción)
     *   - array → lista de granja_id permitidos (operario/lector restringido)
     */
    public static function granjasVisibles(): ?array
    {
        // Cache por request: el resultado no cambia dentro del mismo proceso.
        static $cache = null;
        if ($cache !== null) {
            return $cache === 'all' ? null : $cache;
        }

        if (self::esAdmin()) {
            $cache = 'all';
            return null;
        }

        $userId = (int) (Session::get('usuario_id') ?? 0);
        $orgId  = self::id();
        if ($userId <= 0 || $orgId <= 0) {
            $cache = 'all';
            return null;
        }

        $stmt = Database::getInstance()->prepare("
            SELECT granja_id FROM granja_miembros
            WHERE usuario_id = :uid AND organizacion_id = :oid
        ");
        $stmt->execute(['uid' => $userId, 'oid' => $orgId]);
        $ids = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        if (empty($ids)) {
            $cache = 'all';
            return null;
        }

        $cache = array_map('intval', $ids);
        return $cache;
    }

    /**
     * Devuelve un fragmento SQL " AND <colExpr> IN (1,2,3) " (con espacios)
     * que se puede concatenar en queries para restringir por granja.
     * Si el usuario ve todas, devuelve "" (sin filtro).
     * Los IDs se castean a int — seguro contra inyección.
     */
    public static function granjaFilterSql(string $colExpr = 'g.id'): string
    {
        $ids = self::granjasVisibles();
        if ($ids === null) return '';
        if (empty($ids))  return ' AND 1=0 ';
        $list = implode(',', array_map('intval', $ids));
        return " AND $colExpr IN ($list) ";
    }

    /** ¿Puede el usuario actual acceder a esta granja? */
    public static function puedeVerGranja(int $granjaId): bool
    {
        $ids = self::granjasVisibles();
        if ($ids === null) return true;
        return in_array($granjaId, $ids, true);
    }
}
