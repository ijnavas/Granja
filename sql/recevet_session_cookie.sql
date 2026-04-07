-- Añade la columna de cookie de sesión de Recevet a la tabla usuarios
-- Ejecutar una sola vez en producción

ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS recevet_session_cookie TEXT NULL AFTER recevet_password_enc;
