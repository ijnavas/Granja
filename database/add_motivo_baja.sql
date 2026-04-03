-- Añade columna motivo_baja a movimientos para registrar la causa de la baja (enfermedad, sacrificio, etc.)
ALTER TABLE movimientos
    ADD COLUMN motivo_baja VARCHAR(50) NULL DEFAULT NULL AFTER tipo_venta;
