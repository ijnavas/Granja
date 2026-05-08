-- Asegurar que el tipo "Traslado cuadra" existe como tipo de sistema
-- (es_sistema=1, no borrable). Es fundamental para mover animales de
-- una cuadra a otra dentro del mismo lote.
INSERT IGNORE INTO tipos_movimiento (codigo, nombre, categoria, es_sistema, activo, orden, color) VALUES
    ('traslado_cuadra', 'Traslado cuadra', 'traslado', 1, 1, 8, '#1d4ed8');

-- Si ya existía como es_sistema=0 (creado manualmente por el usuario)
-- o estaba inactivo, lo promovemos a sistema y lo activamos.
UPDATE tipos_movimiento
   SET es_sistema = 1, activo = 1, categoria = 'traslado'
 WHERE codigo = 'traslado_cuadra';
