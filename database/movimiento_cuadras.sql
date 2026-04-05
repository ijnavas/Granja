CREATE TABLE IF NOT EXISTS movimiento_cuadras (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    movimiento_id INT UNSIGNED NOT NULL,
    cuadra_id   INT UNSIGNED NOT NULL,
    num_animales INT UNSIGNED NOT NULL,
    INDEX (movimiento_id)
);
