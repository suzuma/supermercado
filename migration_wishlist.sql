CREATE TABLE IF NOT EXISTS `wishlist` (
    `cliente_id`  INT UNSIGNED NOT NULL,
    `producto_id` INT UNSIGNED NOT NULL,
    `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`cliente_id`, `producto_id`),
    CONSTRAINT `fk_wishlist_cliente`  FOREIGN KEY (`cliente_id`)  REFERENCES `clientes`(`id`)  ON DELETE CASCADE,
    CONSTRAINT `fk_wishlist_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;