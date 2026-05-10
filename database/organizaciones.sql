-- Modelo SaaS multi-organización.
--
-- Una organización (típicamente: una empresa, explotación o cuenta) agrupa:
--   - varios usuarios con distintos roles
--   - varias granjas
--   - todo lo que cuelga de las granjas (naves, lotes, movimientos, etc.)
--
-- Roles dentro de la organización:
--   owner    → dueño/a, no se puede expulsar; puede eliminar la org
--   admin    → puede invitar/expulsar usuarios, cambiar configuración global
--   operario → operativa diaria (movimientos, pesajes, inventarios)
--   lector   → solo lectura

CREATE TABLE IF NOT EXISTS organizaciones (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(120) NOT NULL,
    plan        ENUM('free','pro','enterprise') NOT NULL DEFAULT 'free',
    activa      TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_activa (activa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Pivote usuarios ↔ organizaciones con rol específico.
CREATE TABLE IF NOT EXISTS organizacion_usuarios (
    organizacion_id INT UNSIGNED NOT NULL,
    usuario_id      INT          NOT NULL,
    rol             ENUM('owner','admin','operario','lector') NOT NULL DEFAULT 'operario',
    invitado_por    INT          NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (organizacion_id, usuario_id),
    KEY idx_user (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Invitaciones pendientes (token-based, expiran en 7 días).
CREATE TABLE IF NOT EXISTS invitaciones_organizacion (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organizacion_id INT UNSIGNED NOT NULL,
    email           VARCHAR(190) NOT NULL,
    rol             ENUM('admin','operario','lector') NOT NULL DEFAULT 'operario',
    token           VARCHAR(64)  NOT NULL UNIQUE,
    invitado_por    INT          NOT NULL,
    expira_at       DATETIME     NOT NULL,
    aceptado_at     DATETIME     NULL,
    created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_token (token),
    KEY idx_email (email),
    KEY idx_org   (organizacion_id, aceptado_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Añadir organizacion_id a las tablas que actualmente filtran por usuario_id
-- como "dueño de la cadena de datos".
ALTER TABLE granjas              ADD COLUMN organizacion_id INT UNSIGNED NULL AFTER usuario_id, ADD KEY idx_org (organizacion_id);
ALTER TABLE inventarios          ADD COLUMN organizacion_id INT UNSIGNED NULL AFTER usuario_id, ADD KEY idx_org (organizacion_id);
ALTER TABLE razas_porcino        ADD COLUMN organizacion_id INT UNSIGNED NULL AFTER usuario_id, ADD KEY idx_org (organizacion_id);
ALTER TABLE tablas_crecimiento   ADD COLUMN organizacion_id INT UNSIGNED NULL AFTER usuario_id, ADD KEY idx_org (organizacion_id);
ALTER TABLE configuracion_granja ADD COLUMN organizacion_id INT UNSIGNED NULL, ADD KEY idx_org (organizacion_id);
ALTER TABLE etiquetas            ADD COLUMN organizacion_id INT UNSIGNED NULL AFTER usuario_id, ADD KEY idx_org (organizacion_id);
