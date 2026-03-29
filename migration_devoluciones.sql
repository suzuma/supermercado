-- ============================================================
-- Módulo de Devoluciones
-- Autor: Noe Cazarez Camargo
-- Fecha: 2026-03-29
-- ============================================================

-- 1. Columna para rastrear cantidad ya devuelta en cada línea de venta
ALTER TABLE venta_detalles
    ADD COLUMN cantidad_devuelta DECIMAL(10,3) NOT NULL DEFAULT 0.000
        COMMENT 'Cantidad ya procesada en devoluciones';

-- 2. Tabla principal de devoluciones
CREATE TABLE devoluciones (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    venta_id        INT UNSIGNED NOT NULL,
    usuario_id      INT UNSIGNED NOT NULL,
    motivo          VARCHAR(255) NULL,
    total_devuelto  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (venta_id)   REFERENCES ventas(id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Detalle de artículos devueltos
CREATE TABLE devolucion_detalles (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    devolucion_id       INT UNSIGNED NOT NULL,
    venta_detalle_id    INT UNSIGNED NOT NULL,
    producto_id         INT UNSIGNED NOT NULL,
    cantidad            DECIMAL(10,3) NOT NULL,
    precio_unitario     DECIMAL(10,2) NOT NULL,
    subtotal            DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (devolucion_id)    REFERENCES devoluciones(id),
    FOREIGN KEY (venta_detalle_id) REFERENCES venta_detalles(id),
    FOREIGN KEY (producto_id)      REFERENCES productos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;