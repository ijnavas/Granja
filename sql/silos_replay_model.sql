-- Migración al modelo "replay" de stock de silos
--
-- Cambio de semántica:
--   ANTES: (silos.stock_actual_kg, silos.stock_base_fecha) era el estado
--          consolidado (valor + fecha de la última recarga). Las recargas
--          actualizaban este estado acumulativamente.
--   AHORA: (silos.stock_actual_kg, silos.stock_base_fecha) es la CALIBRACIÓN
--          manual — el estado físico conocido en una fecha concreta. Las
--          recargas viven en silo_recargas y se replican cronológicamente al
--          leer el stock (ver Silo::rebuildStockAt).
--
-- Para que el replay sea correcto tras el cambio, hay que "rebobinar" la
-- calibración de los silos que tienen recargas:
--   - calibracion_fecha = fecha_de_la_PRIMERA_recarga
--   - calibracion_kg    = 0
-- De esta forma el replay arranca en 0 en esa fecha y procesa todas las
-- recargas en orden. Los silos SIN recargas se dejan como están (su
-- stock_actual_kg era ya una calibración manual pura).
--
-- IMPORTANTE: esto RESETEA cualquier calibración manual previa que tuvieran
-- los silos con recargas. Si alguno tenía un estado pre-recarga significativo
-- (ej: "cuando creé el silo ya había 3000 kg dentro") ese valor se pierde.
-- En el estado actual del sistema (post-bug de consolidación rota) los valores
-- en silos.stock_actual_kg no eran confiables de todas formas, así que esta
-- limpieza es lo correcto.

UPDATE silos s
JOIN (
    SELECT silo_id, MIN(fecha) AS primera_fecha
    FROM silo_recargas
    GROUP BY silo_id
) r ON r.silo_id = s.id
SET s.stock_actual_kg  = 0,
    s.stock_base_fecha = r.primera_fecha;
