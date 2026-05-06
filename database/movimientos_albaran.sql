-- Albaran/foto opcional en ventas (peso del camión).
-- Guarda solo la ruta relativa dentro de /uploads/albaranes/. El fichero
-- en sí va al sistema de ficheros del hosting.
ALTER TABLE movimientos
    ADD COLUMN albaran_archivo VARCHAR(255) NULL DEFAULT NULL AFTER observaciones;
