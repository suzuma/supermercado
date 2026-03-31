-- =====================================================
-- MIGRACIÓN: Sistema RBAC (Control de Acceso por Rol)
-- Fecha: 2026-03-30
-- =====================================================

CREATE TABLE IF NOT EXISTS `permisos` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `slug`        VARCHAR(60)  NOT NULL UNIQUE,
    `descripcion` VARCHAR(120) NOT NULL,
    `modulo`      VARCHAR(50)  NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `rol_permisos` (
    `rol_id`     INT UNSIGNED NOT NULL,
    `permiso_id` INT NOT NULL,
    PRIMARY KEY (`rol_id`, `permiso_id`),
    CONSTRAINT `fk_rp_rol`     FOREIGN KEY (`rol_id`)     REFERENCES `roles`(`id`)    ON DELETE CASCADE,
    CONSTRAINT `fk_rp_permiso` FOREIGN KEY (`permiso_id`) REFERENCES `permisos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Catálogo de permisos ────────────────────────────────────
INSERT INTO `permisos` (`slug`, `descripcion`, `modulo`) VALUES
-- Ventas
('ventas.ver',          'Ver módulo de ventas',           'ventas'),
('ventas.registrar',    'Registrar nuevas ventas',        'ventas'),
('ventas.cancelar',     'Cancelar ventas',                'ventas'),
-- Inventario
('inventario.ver',      'Ver inventario',                 'inventario'),
('inventario.editar',   'Agregar / editar productos',     'inventario'),
-- Proveedores
('proveedores.ver',     'Ver proveedores',                'proveedores'),
('proveedores.editar',  'Crear / editar proveedores',     'proveedores'),
-- Clientes
('clientes.ver',        'Ver clientes',                   'clientes'),
('clientes.editar',     'Crear / editar clientes',        'clientes'),
-- Pedidos en línea
('pedidos.ver',         'Ver pedidos en línea',           'pedidos'),
('pedidos.gestionar',   'Cambiar estado / asignar pedidos','pedidos'),
-- Reportes
('reportes.ver',        'Ver reportes',                   'reportes'),
-- Empleados
('empleados.ver',       'Ver empleados',                  'empleados'),
('empleados.editar',    'Crear / editar empleados',       'empleados'),
-- Corte de caja
('corte_caja.ver',      'Ver cortes de caja',             'corte_caja'),
('corte_caja.registrar','Registrar corte de caja',        'corte_caja'),
-- Devoluciones
('devoluciones.ver',      'Ver devoluciones',             'devoluciones'),
('devoluciones.registrar','Registrar devoluciones',       'devoluciones'),
-- Promociones
('promociones.ver',     'Ver promociones',                'promociones'),
('promociones.editar',  'Crear / editar promociones',     'promociones'),
-- Cupones
('cupones.ver',         'Ver cupones',                    'cupones'),
('cupones.editar',      'Crear / editar cupones',         'cupones'),
-- Configuración
('configuracion.ver',   'Ver configuración del sistema',  'configuracion'),
('configuracion.editar','Editar configuración del sistema','configuracion'),
-- Auditoría
('auditoria.ver',       'Ver bitácora de auditoría',      'auditoria');

-- ─── Permisos por defecto para cada rol ─────────────────────
-- Admin (1): todos los permisos
INSERT INTO `rol_permisos` (`rol_id`, `permiso_id`)
SELECT 1, id FROM `permisos`;

-- Cajero (2)
INSERT INTO `rol_permisos` (`rol_id`, `permiso_id`)
SELECT 2, id FROM `permisos` WHERE `slug` IN (
    'ventas.ver', 'ventas.registrar', 'ventas.cancelar',
    'corte_caja.ver', 'corte_caja.registrar',
    'devoluciones.ver', 'devoluciones.registrar',
    'promociones.ver',
    'clientes.ver',
    'pedidos.ver'
);

-- Analista / Almacén (3)
INSERT INTO `rol_permisos` (`rol_id`, `permiso_id`)
SELECT 3, id FROM `permisos` WHERE `slug` IN (
    'inventario.ver', 'inventario.editar',
    'proveedores.ver', 'proveedores.editar',
    'clientes.ver', 'clientes.editar',
    'pedidos.ver', 'pedidos.gestionar',
    'reportes.ver'
);

-- Repartidor (4)
INSERT INTO `rol_permisos` (`rol_id`, `permiso_id`)
SELECT 4, id FROM `permisos` WHERE `slug` IN (
    'pedidos.ver', 'pedidos.gestionar'
);
