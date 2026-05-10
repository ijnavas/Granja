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
    /** Devuelve el org_id activo del request (lazy-init si falta). */
    public static function id(): int
    {
        $orgId = (int) (Session::get('current_org_id') ?? 0);
        if ($orgId > 0) return $orgId;

        // Lazy init: si el usuario tiene alguna org, usar la primera.
        // Si no, crear una personal.
        $userId = (int) (Session::get('usuario_id') ?? 0);
        if ($userId <= 0) return 0;

        $userName = (string) (Session::get('usuario_nombre') ?? 'Usuario');
        $orgModel = new Organizacion();
        $orgId    = $orgModel->ensureOrgForUsuario($userId, $userName);

        $rol = $orgModel->rolEnOrg($userId, $orgId) ?? 'operario';
        Session::set('current_org_id',  $orgId);
        Session::set('current_org_rol', $rol);
        return $orgId;
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
}
