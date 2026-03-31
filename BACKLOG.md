# Backlog — SuZuMa

Funcionalidades identificadas en el análisis del sistema (2026-03-30), priorizadas por impacto/esfuerzo.

---

## P0 — Inmediato (seguridad, bajo esfuerzo)

| # | Tarea | Estado |
|---|-------|--------|
| S1 | Aplicar filtro `can:slug` a todas las rutas en `routes.php` | ✅ Listo |
| S2 | Agregar índices en BD a columnas críticas | ✅ Listo |
| S3 | Validar MIME real en upload de logo (no solo extensión) | ✅ Listo |

## P1 — Crítico (rendimiento/seguridad)

| # | Tarea | Estado |
|---|-------|--------|
| R1 | Resolver N+1 en módulo de inventario — eager loading de promociones | ✅ Listo |
| R2 | Migrar contraseñas a bcrypt (`password_hash`) — usuarios sistema y clientes | ✅ Listo |
| R3 | Cambiar `unserialize()` a JSON en `core/Auth.php` para sesión de admin | ✅ Listo |
| R4 | Arreglar dominio y SameSite de cookie para producción en `core/Auth.php` | ✅ Listo |

## P2 — Funcionalidades de negocio (impacto directo en operación)

| # | Funcionalidad | Estado |
|---|---------------|--------|
| F1 | **Recepción de órdenes de compra** — al marcar como "recibida", actualizar stock y precio de compra automáticamente | ✅ Listo |
| F2 | **Caducidades completo** — baja automática de vencidos, descuento por proximidad a vencimiento, reporte de merma | ✅ Listo |
| F3 | **Programa de puntos / lealtad** — puntos por compra canjeables como descuento | ✅ Listo |
| F4 | **Vista simplificada para repartidor** — panel mobile-first con solo sus pedidos y botón "entregado" | ✅ Listo |
| F5 | **Facturación electrónica (CFDI)** — XML con sello SAT para ventas a empresas | ⬜ Pendiente |

## P3 — Mejoras de UX y dashboard

| # | Mejora | Estado |
|---|--------|--------|
| U1 | **Dashboard con más métricas** — tasa devoluciones, producto más vendido del día, promedio de ticket, cajero con más ventas | ⬜ Pendiente |
| U2 | **Impresión directa de ticket** — con `jsPrint` + impresora térmica, sin abrir PDF en nueva pestaña | ⬜ Pendiente |
| U3 | **Autocompletado de CP/colonia en checkout** — API de colonias por código postal mexicano | ⬜ Pendiente |
| U4 | **Notificaciones en tiempo real** — stock bajo y pedidos pendientes con Server-Sent Events (SSE) | ⬜ Pendiente |

## P4 — Calidad de código

| # | Tarea | Estado |
|---|-------|--------|
| Q1 | Extraer JavaScript de `caja.twig` a archivo `.js` externo | ⬜ Pendiente |
| Q2 | Crear `PromocionService` para centralizar cálculo de precios con descuento | ⬜ Pendiente |
| Q3 | Agregar paginación a `ClienteRepository::buscar()` y `ProveedorRepository::listar()` | ⬜ Pendiente |
| Q4 | Agregar PHPUnit — cubrir al menos repositorios de ventas y devoluciones | ⬜ Pendiente |
| Q5 | Commitear `composer.lock` al repositorio | ⬜ Pendiente |