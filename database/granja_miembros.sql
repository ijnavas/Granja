-- Permisos por granja para miembros de una organización.
--
-- Reglas:
--   - owner / admin de la org → ven TODAS las granjas de la org (sin filtro).
--   - operario / lector → si tienen entradas en granja_miembros, sólo ven
--     esas granjas. Si no tienen entradas, ven TODAS por defecto
--     (compatibilidad con miembros existentes).
--
-- Para "restringir" a un operario a determinadas granjas, basta con insertar
-- una fila por cada granja que sí debe ver. Para "abrir" de nuevo a todas,
-- borrar todas las filas suyas en esta tabla.

CREATE TABLE IF NOT EXISTS granja_miembros (
    granja_id        INT UNSIGNED NOT NULL,
    usuario_id       INT          NOT NULL,
    organizacion_id  INT UNSIGNED NOT NULL,
    created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (granja_id, usuario_id),
    KEY idx_usuario_org (usuario_id, organizacion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
