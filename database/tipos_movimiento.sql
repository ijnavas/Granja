-- Tipos de movimiento configurables.
--
-- Antes existía un ENUM hardcoded en `movimientos.tipo` con 6 valores fijos.
-- Ahora cada tipo es una fila editable desde Configuración. La lógica de
-- "efectos" (sumar/restar animales, traslados, transiciones de estado,
-- creación de sub-lote RE, etc.) la dispara la columna `categoria`, no el
-- código del tipo: así un usuario puede crear "Venta cebo" o "Baja cebo"
-- como variantes con etiquetas distintas pero comportamiento equivalente.
--
-- Categorías:
--   traslado     → mueve animales entre cuadras (mismo lote)
--   salida       → resta animales del lote/cuadra (genérico)
--   entrada      → suma animales a un lote existente (compra)
--   venta        → resta + datos comerciales (precio, peso canal, destino)
--   baja         → resta + motivo + peso real opcional
--   transicion   → solo cambio de estado animal (lechón → cebo)
--   re_creacion  → crea sub-lote RE a partir de un lote
--   re_consumo   → consume animales de un lote RE (paso a madres)
--
-- es_sistema = 1 → tipos no editables ni borrables (los 6 originales).

CREATE TABLE IF NOT EXISTS tipos_movimiento (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo          VARCHAR(40)  NOT NULL UNIQUE,
    nombre          VARCHAR(100) NOT NULL,
    categoria       ENUM('traslado','salida','entrada','venta','baja','transicion','re_creacion','re_consumo') NOT NULL,
    es_sistema      TINYINT(1)   NOT NULL DEFAULT 0,
    activo          TINYINT(1)   NOT NULL DEFAULT 1,
    orden           INT UNSIGNED NOT NULL DEFAULT 0,
    color           VARCHAR(7)   NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_activo_orden (activo, orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed: tipos por defecto (todos editables/borrables por el usuario).
-- Para tipos personalizados con lógica de traslado, transición, etc.,
-- el usuario puede crear los suyos desde Configuración → Movimientos.
INSERT IGNORE INTO tipos_movimiento (codigo, nombre, categoria, es_sistema, activo, orden, color) VALUES
    ('salida_madres',      'Salida de madres',    'salida', 0, 1, 110, '#b45309'),
    ('venta_transicion',   'Venta transición',    'venta',  0, 1, 120, '#0e7490'),
    ('venta_reposicion',   'Venta reposición',    'venta',  0, 1, 130, '#0e7490'),
    ('venta_cebo',         'Venta cebo',          'venta',  0, 1, 140, '#0e7490'),
    ('venta_desvieje',     'Venta desvieje',      'venta',  0, 1, 150, '#0e7490'),
    ('compra_lechon',      'Compra lechón',       'entrada',0, 1, 160, '#15803d'),
    ('compra_reposicion',  'Compra reposición',   'entrada',0, 1, 170, '#15803d'),
    ('baja_transicion',    'Baja transición',     'baja',   0, 1, 180, '#dc2626'),
    ('baja_reposicion',    'Baja reposición',     'baja',   0, 1, 190, '#dc2626'),
    ('baja_cebo',          'Baja cebo',           'baja',   0, 1, 200, '#dc2626');

-- Cambiar el ENUM original a VARCHAR para aceptar tipos custom.
-- (Si la tabla movimientos no tiene aún el ENUM, este ALTER es no-op seguro
-- porque MariaDB/MySQL aceptan VARCHAR como destino de ENUM con datos compatibles.)
ALTER TABLE movimientos
    MODIFY COLUMN tipo VARCHAR(40) NOT NULL;

-- Peso real opcional para bajas (peso individual medio observado).
-- Si la columna ya existe, ignorar el error de "Duplicate column" al ejecutar.
ALTER TABLE movimientos
    ADD COLUMN peso_real_kg DECIMAL(8,3) NULL DEFAULT NULL AFTER peso_canal_kg;
