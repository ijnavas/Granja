-- Amplía el ENUM de categorías para añadir 'traslado_lote' y 'destete'.
-- Necesario antes de insertar los nuevos tipos.
ALTER TABLE tipos_movimiento
    MODIFY COLUMN categoria ENUM(
        'traslado','traslado_lote','salida','entrada','venta','baja',
        'transicion','re_creacion','re_consumo','destete'
    ) NOT NULL;

-- Tipo "Destete" (es_sistema=1, no borrable). Es la creación de un
-- lote a partir del destete de lechones. La pantalla redirige al
-- formulario de nuevo lote.
INSERT IGNORE INTO tipos_movimiento (codigo, nombre, categoria, es_sistema, activo, orden, color) VALUES
    ('destete',       'Destete (alta de lote)', 'destete',       1, 1,  5, '#15803d'),
    ('traslado_lote', 'Traslado entre lotes',   'traslado_lote', 0, 1, 15, '#1d4ed8');
