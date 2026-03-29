-- ── Migración: Fechas de caducidad en productos ──────────────────────────
-- Ejecutar: mysql -u root supermercado < migration_caducidad.sql

ALTER TABLE productos
    ADD COLUMN fecha_caducidad DATE NULL DEFAULT NULL
    AFTER unidad_peso;