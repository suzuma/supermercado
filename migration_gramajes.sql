-- ============================================================
-- Migración: soporte de venta por gramaje / peso
-- ============================================================

USE supermercado;

-- Productos: flag de venta por peso, unidad y stock decimal
ALTER TABLE productos
    ADD COLUMN venta_por_peso TINYINT(1) NOT NULL DEFAULT 0 AFTER activo,
    ADD COLUMN unidad_peso    ENUM('g','kg','lb') NOT NULL DEFAULT 'kg' AFTER venta_por_peso,
    MODIFY COLUMN stock        DECIMAL(10,3) NOT NULL DEFAULT 0.000,
    MODIFY COLUMN stock_minimo DECIMAL(10,3) NOT NULL DEFAULT 5.000;

-- Detalles de venta: cantidad decimal para soportar fracciones
ALTER TABLE venta_detalles
    MODIFY COLUMN cantidad DECIMAL(10,3) NOT NULL DEFAULT 1.000;

-- Detalles de pedido: igual
ALTER TABLE pedido_detalles
    MODIFY COLUMN cantidad DECIMAL(10,3) NOT NULL DEFAULT 1.000;

-- Contraseñas (pendiente de migrations previas de bcrypt)
ALTER TABLE usuarios MODIFY password VARCHAR(255) NOT NULL;
ALTER TABLE clientes MODIFY password VARCHAR(255) NOT NULL;