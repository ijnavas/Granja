-- Añade peso_real_kg a inventario_lineas: distingue entre el peso individual
-- estimado (peso_kg) y el peso medio realmente medido en algún pesaje del lote.
-- Si NULL, significa que el lote nunca se pesó hasta esa fecha.
ALTER TABLE inventario_lineas
    ADD COLUMN peso_real_kg DECIMAL(10,3) NULL DEFAULT NULL AFTER peso_kg;
