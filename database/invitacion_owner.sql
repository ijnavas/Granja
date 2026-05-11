-- Permitir invitar como 'owner' (creacion de nuevo cliente con su admin
-- propietario). Antes solo se podia owner si la org se creaba via
-- ensureOrgForUsuario (legacy migration). Para crear un cliente nuevo
-- desde el super-usuario se necesita extender el enum.
ALTER TABLE invitaciones_organizacion
    MODIFY rol ENUM('owner','admin','operario','lector') NOT NULL DEFAULT 'operario';
