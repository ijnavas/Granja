-- Configuración por usuario para umbrales y avisos de movimientos.
--
-- Una fila por usuario. Si no existe, se asumen los defaults del modelo.
--
-- Nota: sin FK a usuarios para evitar el clásico error 150 "Foreign key
-- constraint is incorrectly formed" cuando hay mismatch de tipo/collation
-- entre tablas (mismo criterio que audit_log).

CREATE TABLE IF NOT EXISTS configuracion_granja (
    usuario_id                       INT          NOT NULL PRIMARY KEY,
    dias_advertencia_movimiento      INT UNSIGNED NOT NULL DEFAULT 7,
    pct_desviacion_peso_tabla        INT UNSIGNED NOT NULL DEFAULT 15,
    updated_at                       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
