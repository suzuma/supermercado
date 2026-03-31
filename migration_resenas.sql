CREATE TABLE IF NOT EXISTS `resenas` (
    `id`          INT           NOT NULL AUTO_INCREMENT,
    `cliente_id`  INT UNSIGNED  NOT NULL,
    `producto_id` INT UNSIGNED  NOT NULL,
    `calificacion` TINYINT      NOT NULL COMMENT '1 a 5',
    `comentario`  TEXT          DEFAULT NULL,
    `activo`      TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_cliente_producto` (`cliente_id`, `producto_id`),
    INDEX `idx_producto` (`producto_id`),
    CONSTRAINT `fk_resena_cliente`  FOREIGN KEY (`cliente_id`)  REFERENCES `clientes`(`id`)  ON DELETE CASCADE,
    CONSTRAINT `fk_resena_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;