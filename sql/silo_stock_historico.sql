-- Histórico diario de stock por silo.
--
-- Persistimos un snapshot al día del estado calculado por rebuildStockAt,
-- junto con el consumo estimado de ese día y el número de animales activos
-- en naves abastecidas por el silo. Esto permite:
--   1. Gráficas históricas sin recalcular el replay completo cada vez.
--   2. Auditar discrepancias entre lo teórico y lo físico (si hay recalibración).
--   3. Detectar anomalías (consumo cero, saltos bruscos) post-hoc.
--
-- El UNIQUE (silo_id, fecha) garantiza idempotencia: si el cron se ejecuta
-- dos veces el mismo día (o se lanza manualmente para backfill), el segundo
-- INSERT hace UPDATE sin duplicar.

CREATE TABLE IF NOT EXISTS silo_stock_historico (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    silo_id         INT NOT NULL,
    fecha           DATE NOT NULL,
    stock_kg        DECIMAL(10,2) NOT NULL,
    consumo_dia_kg  DECIMAL(10,2) NOT NULL DEFAULT 0,
    num_animales    INT NOT NULL DEFAULT 0,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_silo_fecha (silo_id, fecha),
    KEY idx_fecha (fecha),
    CONSTRAINT fk_silo_stock_hist_silo FOREIGN KEY (silo_id)
        REFERENCES silos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
