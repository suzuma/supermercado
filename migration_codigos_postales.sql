CREATE TABLE IF NOT EXISTS `codigos_postales` (
    `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `cp`        CHAR(5)      NOT NULL,
    `colonia`   VARCHAR(120) NOT NULL,
    `municipio` VARCHAR(100) NOT NULL DEFAULT '',
    `estado`    VARCHAR(60)  NOT NULL DEFAULT '',
    `ciudad`    VARCHAR(100) NOT NULL DEFAULT '',
    PRIMARY KEY (`id`),
    INDEX `idx_cp` (`cp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;