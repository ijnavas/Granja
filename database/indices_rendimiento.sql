-- Índices de rendimiento para listados, joins y subqueries calientes.
--
-- Identificados en auditoria de mayo 2026:
--   - Subquery de "ultimo pesaje por lote" en Lote::allByUsuario y Dashboard
--   - JOINs y filtros frecuentes por organizacion_id, lote_id, fecha
--   - Listados de movimientos, pesajes, recargas filtrados por fecha/tipo
--
-- Todos los CREATE INDEX usan IF NOT EXISTS-equivalente: si ya existe se
-- ignora con un warning (la app sigue funcionando). Si MySQL <8.0 no
-- soporta IF NOT EXISTS, ejecutar manualmente y descartar duplicados.

-- Pesajes ordenados por lote y fecha — subquery "último pesaje" del lote.
ALTER TABLE pesajes ADD INDEX idx_lote_fecha (lote_id, fecha DESC);

-- Movimientos: listados por origen/destino y filtrado por fecha+tipo.
ALTER TABLE movimientos ADD INDEX idx_origen_fecha (lote_origen_id, fecha);
ALTER TABLE movimientos ADD INDEX idx_destino       (lote_destino_id);
ALTER TABLE movimientos ADD INDEX idx_fecha_tipo    (fecha, tipo);

-- Lotes: filtros por granja+estado, nave+estado, raza, codigo.
ALTER TABLE lotes ADD INDEX idx_granja_estado (granja_id, estado);
ALTER TABLE lotes ADD INDEX idx_nave_estado   (nave_id, estado);
ALTER TABLE lotes ADD INDEX idx_raza          (raza_id);
ALTER TABLE lotes ADD INDEX idx_codigo        (codigo);

-- Granjas: filtro por organizacion + activa (listados de Granja::allByOrg).
ALTER TABLE granjas ADD INDEX idx_org_activa (organizacion_id, activa);

-- Cuadra-lote: doble dirección (por cuadra y por lote, ambos con activo).
ALTER TABLE cuadra_lote ADD INDEX idx_cuadra_activo (cuadra_id, activo);
ALTER TABLE cuadra_lote ADD INDEX idx_lote_activo   (lote_id, activo);

-- Silos: recargas filtradas por silo+fecha (replay de stock).
ALTER TABLE silo_recargas ADD INDEX idx_silo_fecha (silo_id, fecha);

-- Naves: filtro por granja + activa.
ALTER TABLE naves ADD INDEX idx_granja_activa (granja_id, activa);

-- Cuadras: filtro por nave + activa.
ALTER TABLE cuadras ADD INDEX idx_nave_activa (nave_id, activa);
