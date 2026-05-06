-- Motivos de baja configurables.
--
-- Antes estaban hardcoded en el formulario y en el escáner. Ahora son
-- editables desde Configuración → Avisos.
CREATE TABLE IF NOT EXISTS motivos_baja (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo      VARCHAR(40)  NOT NULL UNIQUE,
    nombre      VARCHAR(100) NOT NULL,
    activo      TINYINT(1)   NOT NULL DEFAULT 1,
    orden       INT UNSIGNED NOT NULL DEFAULT 0,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_activo_orden (activo, orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed con los motivos que ya se usaban en la app
INSERT IGNORE INTO motivos_baja (codigo, nombre, activo, orden) VALUES
    ('enfermedad',    'Enfermedad',    1, 10),
    ('sacrificio',    'Sacrificio',    1, 20),
    ('canibalismo',   'Canibalismo',   1, 30),
    ('aplastamiento', 'Aplastamiento', 1, 40),
    ('otro',          'Otro',          1, 50);
