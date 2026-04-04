CREATE TABLE IF NOT EXISTS inventarios (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    fecha       DATE         NOT NULL,
    nombre      VARCHAR(120) NULL,
    usuario_id  INT          NOT NULL,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_fecha (fecha),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inventario_lineas (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    inventario_id   INT          NOT NULL,
    lote_id         INT          NOT NULL,
    cuadra_id       INT          NULL,
    nave_id         INT          NULL,
    granja_id       INT          NULL,
    estado_animal   VARCHAR(50)  NULL,
    num_animales    INT          NOT NULL DEFAULT 0,
    peso_kg         DECIMAL(10,3) NULL,
    peso_total_kg   DECIMAL(12,3) NULL,
    coste_eur       DECIMAL(10,2) NULL,
    valor_total_eur DECIMAL(12,2) NULL,
    semana_tabla    INT          NULL,
    FOREIGN KEY (inventario_id) REFERENCES inventarios(id) ON DELETE CASCADE,
    FOREIGN KEY (lote_id)       REFERENCES lotes(id),
    INDEX idx_inventario (inventario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
