-- Histórico de calibraciones manuales (taras) de silos.
--
-- Cada fila es un evento 'set' dentro del timeline de replay del silo:
-- fija el stock a `stock_kg` en `fecha`, sobrescribiendo el estado anterior.
-- El modelo (`Silo::rebuildStockAt`) las trata junto con las recargas para
-- reconstruir el stock real en cualquier fecha.
--
-- Uso típico: el usuario detecta pérdida de pienso (mojado, fuga, rotura
-- de saco…) y tara el silo al valor real medido, anotando el motivo.
--
-- Convivencia con la calibración legacy: la columna
-- `silos.stock_actual_kg` + `silos.stock_base_fecha` sigue siendo válida
-- como calibración inicial (la que se introduce al crear el silo). Las
-- taras posteriores van a esta tabla. El replay las considera todas.

CREATE TABLE IF NOT EXISTS silo_calibraciones (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    silo_id     INT UNSIGNED NOT NULL,
    fecha       DATE NOT NULL,
    stock_kg    DECIMAL(12,2) NOT NULL,
    motivo      VARCHAR(255) NULL,
    usuario_id  INT UNSIGNED NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_silo_fecha (silo_id, fecha),
    CONSTRAINT fk_silo_calib_silo
        FOREIGN KEY (silo_id) REFERENCES silos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
