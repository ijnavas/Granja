-- Las tablas tipos_movimiento y motivos_baja se crearon con
-- utf8mb4_unicode_ci pero el resto de la BD (incluida movimientos)
-- usa utf8mb4_general_ci. Cualquier JOIN entre ellas falla con
-- "Illegal mix of collations". Esto las alinea.
ALTER TABLE tipos_movimiento  CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE motivos_baja      CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
