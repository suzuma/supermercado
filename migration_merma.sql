-- =====================================================
-- MIGRACIÓN: Tabla de merma y config de descuentos por caducidad
-- Fecha: 2026-03-30
-- =====================================================

CREATE TABLE IF NOT EXISTS `merma` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `producto_id` INT UNSIGNED NOT NULL,
    `cantidad`    DECIMAL(10,3) NOT NULL,
    `motivo`      ENUM('vencimiento','daño','otro') NOT NULL DEFAULT 'vencimiento',
    `notas`       TEXT NULL,
    `usuario_id`  INT UNSIGNED NULL,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_merma_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos`(`id`),
    CONSTRAINT `fk_merma_usuario`  FOREIGN KEY (`usuario_id`)  REFERENCES `usuarios`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Descuento automático por proximidad a vencimiento (porcentaje)
INSERT INTO `configuracion` (`clave`, `valor`) VALUES
    ('descuento_proximo', '15'),
    ('descuento_critico', '30')
ON DUPLICATE KEY UPDATE `valor` = VALUES(`valor`);