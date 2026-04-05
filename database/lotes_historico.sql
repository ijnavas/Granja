-- Campos para el histórico de lotes
ALTER TABLE lotes
    ADD COLUMN IF NOT EXISTS fecha_cierre DATE NULL AFTER fecha_entrada,
    ADD COLUMN IF NOT EXISTS num_animales_entrada INT UNSIGNED NULL AFTER num_animales;

-- Rellenar num_animales_entrada para lotes ya cerrados (mejor estimación: num_animales actual)
UPDATE lotes SET num_animales_entrada = num_animales WHERE num_animales_entrada IS NULL AND estado = 'cerrado';

-- Guardar num_animales en num_animales_entrada al crear lotes nuevos (trigger opcional)
-- Se puede omitir y rellenar desde el controller al crear el lote.
