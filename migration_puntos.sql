-- =====================================================
-- MIGRACIÓN: Programa de puntos / lealtad
-- Fecha: 2026-03-30
-- =====================================================

-- Saldo actual de puntos por cliente
ALTER TABLE `clientes`
    ADD COLUMN `puntos` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `rfc`;

-- Puntos canjeados en cada pedido (para poder revertir si se cancela)
ALTER TABLE `pedidos`
    ADD COLUMN `puntos_usados` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `descuento`;

-- Historial de transacciones de puntos
CREATE TABLE IF NOT EXISTS `puntos_transacciones` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `cliente_id`  INT UNSIGNED NOT NULL,
    `pedido_id`   INT UNSIGNED NULL,
    `tipo`        ENUM('ganado','canjeado','revertido','ajuste') NOT NULL,
    `puntos`      INT NOT NULL,
    `descripcion` VARCHAR(255) NOT NULL,
    `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_pt_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes`(`id`),
    CONSTRAINT `fk_pt_pedido`  FOREIGN KEY (`pedido_id`)  REFERENCES `pedidos`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configuración del programa de puntos
INSERT INTO `configuracion` (`clave`, `valor`) VALUES
    ('puntos_por_peso',      '10'),   -- $10 gastados = 1 punto
    ('valor_punto',          '0.50'), -- 1 punto = $0.50 de descuento
    ('minimo_puntos_canje',  '50')    -- mínimo de puntos para poder canjear
ON DUPLICATE KEY UPDATE `valor` = VALUES(`valor`);