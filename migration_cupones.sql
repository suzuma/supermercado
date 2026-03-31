CREATE TABLE IF NOT EXISTS `cupones` (
    `id`          INT           NOT NULL AUTO_INCREMENT,
    `codigo`      VARCHAR(50)   NOT NULL,
    `descripcion` VARCHAR(150)  NOT NULL DEFAULT '',
    `tipo`        ENUM('porcentaje','monto_fijo') NOT NULL,
    `valor`       DECIMAL(10,2) NOT NULL,
    `monto_minimo` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `usos_max`    INT           DEFAULT NULL COMMENT 'NULL = sin límite',
    `usos_actual` INT           NOT NULL DEFAULT 0,
    `fecha_inicio` DATE         DEFAULT NULL,
    `fecha_fin`   DATE          DEFAULT NULL,
    `activo`      TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `pedidos`
    ADD COLUMN `cupon_id`  INT           DEFAULT NULL AFTER `direccion_entrega`,
    ADD COLUMN `descuento` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `cupon_id`,
    ADD CONSTRAINT `fk_pedido_cupon` FOREIGN KEY (`cupon_id`) REFERENCES `cupones`(`id`) ON DELETE SET NULL;
