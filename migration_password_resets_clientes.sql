CREATE TABLE IF NOT EXISTS `password_resets_clientes` (
    `email`      VARCHAR(191) NOT NULL,
    `token`      CHAR(64)     NOT NULL,
    `expires_at` DATETIME     NOT NULL,
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`token`),
    INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
