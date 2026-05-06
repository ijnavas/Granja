-- Etiquetas / tags libres por usuario, asignables a lotes y a movimientos.
-- El usuario las crea sobre la marcha desde el form (input separado por comas).

CREATE TABLE IF NOT EXISTS etiquetas (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id  INT          NOT NULL,
    nombre      VARCHAR(40)  NOT NULL,
    color       VARCHAR(7)   NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY u_user_nombre (usuario_id, nombre),
    KEY idx_user (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS lote_etiquetas (
    lote_id     INT          NOT NULL,
    etiqueta_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (lote_id, etiqueta_id),
    KEY (etiqueta_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS movimiento_etiquetas (
    movimiento_id INT UNSIGNED NOT NULL,
    etiqueta_id   INT UNSIGNED NOT NULL,
    PRIMARY KEY (movimiento_id, etiqueta_id),
    KEY (etiqueta_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
