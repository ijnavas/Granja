-- Configuración por usuario para umbrales y avisos de movimientos.
--
-- Una fila por usuario. Si no existe, se asumen los defaults del modelo.
--
-- Campos:
--   dias_advertencia_movimiento → si la fecha del movimiento dista
--     más de N días respecto a hoy, advertir al usuario.
--   pct_desviacion_peso_tabla → si el peso introducido en venta/baja
--     se aleja más del N% del peso esperado por tabla, advertir.

CREATE TABLE IF NOT EXISTS configuracion_granja (
    usuario_id                       INT          NOT NULL PRIMARY KEY,
    dias_advertencia_movimiento      INT UNSIGNED NOT NULL DEFAULT 7,
    pct_desviacion_peso_tabla        INT UNSIGNED NOT NULL DEFAULT 15,
    updated_at                       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
