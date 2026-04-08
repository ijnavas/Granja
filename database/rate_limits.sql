-- Rate limiting persistente.
-- Cada (`bucket`, `key`) acumula intentos hasta `expires_at`.
CREATE TABLE IF NOT EXISTS rate_limits (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    bucket     VARCHAR(64)  NOT NULL,   -- ej: "login", "forgot", "register"
    `key`      VARCHAR(190) NOT NULL,   -- ej: IP o IP:email (hasheado)
    attempts   INT          NOT NULL DEFAULT 0,
    expires_at DATETIME     NOT NULL,
    UNIQUE KEY uniq_bucket_key (bucket, `key`),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
