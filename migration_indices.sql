-- =====================================================
-- MIGRACIÓN: Índices de rendimiento
-- Fecha: 2026-03-30
-- =====================================================

-- ── ventas ────────────────────────────────────────────
-- Reportes filtran por fecha y estado constantemente
ALTER TABLE `ventas`
    ADD INDEX `idx_ventas_estado`     (`estado`),
    ADD INDEX `idx_ventas_created_at` (`created_at`),
    ADD INDEX `idx_ventas_corte_id`   (`corte_id`);

-- ── pedidos ───────────────────────────────────────────
-- Dashboard, kanban y filtros por fecha
ALTER TABLE `pedidos`
    ADD INDEX `idx_pedidos_estado`     (`estado`),
    ADD INDEX `idx_pedidos_created_at` (`created_at`);

-- ── productos ─────────────────────────────────────────
-- scopeActivos: WHERE activo = 1
-- scopeStockBajo: WHERE activo = 1 AND stock <= stock_minimo
ALTER TABLE `productos`
    ADD INDEX `idx_productos_activo`      (`activo`),
    ADD INDEX `idx_productos_stock_bajo`  (`activo`, `stock`, `stock_minimo`);

-- ── clientes ──────────────────────────────────────────
-- scopeActivos: WHERE activo = 1
ALTER TABLE `clientes`
    ADD INDEX `idx_clientes_activo` (`activo`);

-- ── empleados ─────────────────────────────────────────
-- scopeActivos: WHERE activo = 1
ALTER TABLE `empleados`
    ADD INDEX `idx_empleados_activo` (`activo`);

-- ── promociones ───────────────────────────────────────
-- scopeVigentes: WHERE activo=1 AND fecha_inicio<=HOY AND fecha_fin>=HOY
ALTER TABLE `promociones`
    ADD INDEX `idx_promociones_vigentes` (`activo`, `fecha_inicio`, `fecha_fin`);

-- ── cortes_caja ───────────────────────────────────────
-- Historial filtra por fecha
ALTER TABLE `cortes_caja`
    ADD INDEX `idx_cortes_created_at` (`created_at`);

-- ── ordenes_compra ────────────────────────────────────
-- Filtros por estado y fecha
ALTER TABLE `ordenes_compra`
    ADD INDEX `idx_ordenes_estado`     (`estado`),
    ADD INDEX `idx_ordenes_created_at` (`created_at`);