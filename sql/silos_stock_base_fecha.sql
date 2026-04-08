-- ─────────────────────────────────────────────────────────────────────
-- Migración: stock real dinámico de silos
-- ─────────────────────────────────────────────────────────────────────
-- A partir de ahora `silos.stock_actual_kg` representa el stock conocido
-- en la última calibración (última recarga, ajuste manual o creación
-- del silo). El stock real se calcula al vuelo restando el consumo
-- acumulado desde `stock_base_fecha` hasta hoy.
--
-- `stock_base_fecha` es la fecha desde la que se debe descontar consumo.
-- Se actualiza cada vez que se toca `stock_actual_kg` (recargas, edición
-- del formulario, etc.) para que el descuento parta del último punto
-- conocido y no se duplique.
-- ─────────────────────────────────────────────────────────────────────

ALTER TABLE silos
    ADD COLUMN IF NOT EXISTS stock_base_fecha DATE NULL AFTER stock_minimo_kg;

-- Backfill: para cada silo, partir de la fecha más reciente entre la
-- última recarga y la fecha de creación del silo. Es lo que mejor
-- representa el "estado conocido" antes de aplicar la nueva lógica.
UPDATE silos s
LEFT JOIN (
    SELECT silo_id, MAX(fecha) AS ultima_recarga
    FROM silo_recargas
    GROUP BY silo_id
) r ON r.silo_id = s.id
SET s.stock_base_fecha = COALESCE(r.ultima_recarga, DATE(s.created_at), CURDATE())
WHERE s.stock_base_fecha IS NULL;
