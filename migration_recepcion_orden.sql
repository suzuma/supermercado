-- =====================================================
-- MIGRACIÓN: fecha_recepcion en órdenes de compra
-- Fecha: 2026-03-30
-- =====================================================

ALTER TABLE `ordenes_compra`
    ADD COLUMN `fecha_recepcion` TIMESTAMP NULL DEFAULT NULL
        AFTER `fecha_entrega`;