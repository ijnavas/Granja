-- Bloque 7 #14 — Account lockout tras N intentos fallidos de login
--
-- Añade contadores persistentes a la tabla usuarios. A diferencia del
-- rate limiter (IP+email en tabla rate_limits), esto bloquea la CUENTA
-- aunque el atacante rote de IP.
--
-- Umbral propuesto en código: 10 intentos fallidos → 30 min de bloqueo.

ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS failed_login_attempts INT NOT NULL DEFAULT 0 AFTER activo,
    ADD COLUMN IF NOT EXISTS locked_until DATETIME NULL DEFAULT NULL AFTER failed_login_attempts,
    ADD COLUMN IF NOT EXISTS last_failed_login_at DATETIME NULL DEFAULT NULL AFTER locked_until;
