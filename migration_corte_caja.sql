-- Corte de caja
-- Ejecutar antes de usar el módulo

ALTER TABLE ventas
    ADD COLUMN corte_id INT NULL DEFAULT NULL AFTER estado;

CREATE TABLE cortes_caja (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id          INT UNSIGNED NOT NULL,
    fondo_inicial       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_efectivo      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_tarjeta       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_transferencia DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total_ventas        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    num_ventas          INT           NOT NULL DEFAULT 0,
    efectivo_esperado   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    efectivo_contado    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    diferencia          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    observaciones       TEXT          NULL,
    created_at          TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_corte_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;