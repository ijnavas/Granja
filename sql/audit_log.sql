-- Audit log de negocio: registra cambios sobre entidades clave
-- (lotes, silos, recargas, pesajes, movimientos, etc.).
--
-- Convenciones:
--   - datos_antes / datos_despues son JSON serializado (TEXT por
--     compatibilidad con versiones antiguas de MySQL/MariaDB).
--   - accion: 'create' | 'update' | 'delete' | 'ajustar' | 'cerrar' | ...
--   - entidad: nombre lógico ('lote', 'silo', 'recarga', 'pesaje', ...).
--   - No hay FK a usuarios para evitar mismatches de tipo y permitir
--     conservar el log aunque se borre el usuario.

CREATE TABLE IF NOT EXISTS audit_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    accion VARCHAR(32) NOT NULL,
    entidad VARCHAR(48) NOT NULL,
    entidad_id INT UNSIGNED NULL,
    datos_antes TEXT NULL,
    datos_despues TEXT NULL,
    ip VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_entidad (entidad, entidad_id),
    KEY idx_user_date (user_id, created_at),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
