-- Borra los 6 tipos de movimiento que se sembraban como "es_sistema=1"
-- en la primera migración. Los usuarios crearán los suyos desde
-- Configuración → Movimientos.
--
-- IMPORTANTE: los movimientos históricos en la tabla `movimientos`
-- conservan su columna `tipo` con los códigos antiguos (venta, baja,
-- traslado_cuadra, entrada_*). El código PHP los maneja con un
-- fallback de tipos "legacy" para que sigan siendo editables.
DELETE FROM tipos_movimiento WHERE es_sistema = 1;
