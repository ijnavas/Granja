<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

class Granja
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * @deprecated $userId se ignora por compatibilidad. Usa allByOrg()
     * con \App\Core\OrgContext::id(). Se mantiene la firma para no
     * romper código existente.
     */
    public function allByUsuario(int $userId): array
    {
        return $this->allByOrg(\App\Core\OrgContext::id());
    }

    public function allByOrg(int $orgId): array
    {
        $filter = \App\Core\OrgContext::granjaFilterSql('g.id');
        $stmt = $this->db->prepare("
            SELECT g.*,
                   COUNT(DISTINCT n.id) AS num_naves,
                   COUNT(DISTINCT s.id) AS num_silos
            FROM granjas g
            LEFT JOIN naves n ON n.granja_id = g.id AND n.activa = 1
            LEFT JOIN silos s ON s.granja_id = g.id AND s.activo = 1
            WHERE g.organizacion_id = :oid AND g.activa = 1 $filter
            GROUP BY g.id
            ORDER BY g.nombre
        ");
        $stmt->execute(['oid' => $orgId]);
        return $stmt->fetchAll();
    }

    public function find(int $id, int $userId): ?array
    {
        // userId se ignora; se filtra por org activa + visibilidad de granja.
        if (!\App\Core\OrgContext::puedeVerGranja($id)) return null;
        $stmt = $this->db->prepare("
            SELECT * FROM granjas WHERE id = :id AND organizacion_id = :oid AND activa = 1
        ");
        $stmt->execute(['id' => $id, 'oid' => \App\Core\OrgContext::id()]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        // Inyectar org actual si no viene; usuario_id queda como "creador"
        $data['organizacion_id'] = $data['organizacion_id'] ?? \App\Core\OrgContext::id();
        $stmt = $this->db->prepare("
            INSERT INTO granjas (usuario_id, organizacion_id, nombre, codigo_rega, capacidad_max, especie, direccion, municipio, provincia, codigo_postal, tipo_produccion, latitud, longitud, recevet_explotacion)
            VALUES (:usuario_id, :organizacion_id, :nombre, :codigo_rega, :capacidad_max, :especie, :direccion, :municipio, :provincia, :codigo_postal, :tipo_produccion, :latitud, :longitud, :recevet_explotacion)
        ");
        $stmt->execute($data);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, int $userId, array $data): bool
    {
        // userId se ignora; se filtra por org actual.
        $stmt = $this->db->prepare("
            UPDATE granjas SET
                nombre               = :nombre,
                codigo_rega          = :codigo_rega,
                capacidad_max        = :capacidad_max,
                especie              = :especie,
                direccion            = :direccion,
                municipio            = :municipio,
                provincia            = :provincia,
                codigo_postal        = :codigo_postal,
                tipo_produccion      = :tipo_produccion,
                latitud              = :latitud,
                longitud             = :longitud,
                recevet_explotacion  = :recevet_explotacion
            WHERE id = :id AND organizacion_id = :oid
        ");
        $data['id']  = $id;
        $data['oid'] = \App\Core\OrgContext::id();
        return $stmt->execute($data);
    }

    /** Soft-delete. Si $userId es null, elimina sin filtrar (uso admin). */
    public function delete(int $id, ?int $userId = null): bool
    {
        if ($userId === null) {
            $stmt = $this->db->prepare("UPDATE granjas SET activa = 0 WHERE id = :id");
            return $stmt->execute(['id' => $id]) && $stmt->rowCount() > 0;
        }
        $stmt = $this->db->prepare("
            UPDATE granjas SET activa = 0 WHERE id = :id AND organizacion_id = :oid
        ");
        return $stmt->execute(['id' => $id, 'oid' => \App\Core\OrgContext::id()]) && $stmt->rowCount() > 0;
    }

    public function selectOptions(int $userId): array
    {
        $filter = \App\Core\OrgContext::granjaFilterSql('id');
        $stmt = $this->db->prepare("
            SELECT id, nombre, especie, tipo_produccion FROM granjas
            WHERE organizacion_id = :oid AND activa = 1 $filter ORDER BY nombre
        ");
        $stmt->execute(['oid' => \App\Core\OrgContext::id()]);
        return $stmt->fetchAll();
    }
}