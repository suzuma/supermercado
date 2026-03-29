-- ============================================================
-- ESQUEMA COMPLETO — Supermercado Web
-- autor: Noe Cazarez Camargo
-- ============================================================

CREATE DATABASE IF NOT EXISTS supermercado
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE supermercado;

-- ── Roles ────────────────────────────────────────────────────
CREATE TABLE roles (
                       id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                       nombre     VARCHAR(50) NOT NULL,
                       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                       updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ── Usuarios (sistema) ────────────────────────────────────────
CREATE TABLE usuarios (
                          id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                          rol_id     INT UNSIGNED NOT NULL,
                          nombre     VARCHAR(80) NOT NULL,
                          apellido   VARCHAR(80) NOT NULL,
                          email      VARCHAR(120) NOT NULL UNIQUE,
                          password   VARCHAR(40) NOT NULL,
                          activo     TINYINT(1) DEFAULT 1,
                          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                          FOREIGN KEY (rol_id) REFERENCES roles(id)
);

-- ── Empleados ─────────────────────────────────────────────────
CREATE TABLE empleados (
                           id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                           usuario_id   INT UNSIGNED NOT NULL UNIQUE,
                           puesto       VARCHAR(80) NOT NULL,
                           salario      DECIMAL(10,2) DEFAULT 0.00,
                           fecha_ingreso DATE NOT NULL,
                           turno        ENUM('matutino','vespertino','nocturno') DEFAULT 'matutino',
                           activo       TINYINT(1) DEFAULT 1,
                           created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                           updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                           FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- ── Categorías ────────────────────────────────────────────────
CREATE TABLE categorias (
                            id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                            nombre      VARCHAR(80) NOT NULL,
                            descripcion TEXT,
                            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ── Proveedores ───────────────────────────────────────────────
CREATE TABLE proveedores (
                             id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                             nombre     VARCHAR(120) NOT NULL,
                             contacto   VARCHAR(120),
                             telefono   VARCHAR(20),
                             email      VARCHAR(120),
                             direccion  TEXT,
                             activo     TINYINT(1) DEFAULT 1,
                             created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                             updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ── Productos ─────────────────────────────────────────────────
CREATE TABLE productos (
                           id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                           categoria_id   INT UNSIGNED NOT NULL,
                           proveedor_id   INT UNSIGNED,
                           nombre         VARCHAR(150) NOT NULL,
                           descripcion    TEXT,
                           precio_compra  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                           precio_venta   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                           stock          INT NOT NULL DEFAULT 0,
                           stock_minimo   INT NOT NULL DEFAULT 5,
                           codigo_barras  VARCHAR(50) UNIQUE,
                           imagen         VARCHAR(255),
                           activo         TINYINT(1) DEFAULT 1,
                           created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                           updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                           FOREIGN KEY (categoria_id) REFERENCES categorias(id),
                           FOREIGN KEY (proveedor_id) REFERENCES proveedores(id)
);

-- ── Clientes (tienda en línea) ────────────────────────────────
CREATE TABLE clientes (
                          id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                          nombre     VARCHAR(80) NOT NULL,
                          apellido   VARCHAR(80) NOT NULL,
                          email      VARCHAR(120) NOT NULL UNIQUE,
                          telefono   VARCHAR(20),
                          direccion  TEXT,
                          password   VARCHAR(40) NOT NULL,
                          activo     TINYINT(1) DEFAULT 1,
                          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ── Ventas (punto de venta presencial) ───────────────────────
CREATE TABLE ventas (
                        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        usuario_id  INT UNSIGNED NOT NULL,
                        cliente_id  INT UNSIGNED,
                        total       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                        tipo        ENUM('efectivo','tarjeta','transferencia') DEFAULT 'efectivo',
                        estado      ENUM('completada','cancelada','pendiente') DEFAULT 'completada',
                        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
                        FOREIGN KEY (cliente_id) REFERENCES clientes(id)
);

CREATE TABLE venta_detalles (
                                id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                                venta_id        INT UNSIGNED NOT NULL,
                                producto_id     INT UNSIGNED NOT NULL,
                                cantidad        INT NOT NULL DEFAULT 1,
                                precio_unitario DECIMAL(10,2) NOT NULL,
                                subtotal        DECIMAL(10,2) NOT NULL,
                                FOREIGN KEY (venta_id)    REFERENCES ventas(id),
                                FOREIGN KEY (producto_id) REFERENCES productos(id)
);

-- ── Pedidos (venta en línea) ──────────────────────────────────
CREATE TABLE pedidos (
                         id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                         cliente_id        INT UNSIGNED NOT NULL,
                         usuario_id        INT UNSIGNED,
                         total             DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                         estado            ENUM('pendiente','confirmado','enviado','entregado','cancelado') DEFAULT 'pendiente',
                         direccion_entrega TEXT NOT NULL,
                         fecha_entrega     TIMESTAMP NULL,
                         created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                         updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                         FOREIGN KEY (cliente_id) REFERENCES clientes(id),
                         FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

CREATE TABLE pedido_detalles (
                                 id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                                 pedido_id       INT UNSIGNED NOT NULL,
                                 producto_id     INT UNSIGNED NOT NULL,
                                 cantidad        INT NOT NULL DEFAULT 1,
                                 precio_unitario DECIMAL(10,2) NOT NULL,
                                 subtotal        DECIMAL(10,2) NOT NULL,
                                 FOREIGN KEY (pedido_id)   REFERENCES pedidos(id),
                                 FOREIGN KEY (producto_id) REFERENCES productos(id)
);

-- ── Órdenes de compra (proveedores) ──────────────────────────
CREATE TABLE ordenes_compra (
                                id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                                proveedor_id   INT UNSIGNED NOT NULL,
                                usuario_id     INT UNSIGNED NOT NULL,
                                total          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                                estado         ENUM('pendiente','recibida','cancelada') DEFAULT 'pendiente',
                                fecha_entrega  TIMESTAMP NULL,
                                created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                                FOREIGN KEY (proveedor_id) REFERENCES proveedores(id),
                                FOREIGN KEY (usuario_id)   REFERENCES usuarios(id)
);

CREATE TABLE orden_compra_detalles (
                                       id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                                       orden_id        INT UNSIGNED NOT NULL,
                                       producto_id     INT UNSIGNED NOT NULL,
                                       cantidad        INT NOT NULL DEFAULT 1,
                                       precio_unitario DECIMAL(10,2) NOT NULL,
                                       subtotal        DECIMAL(10,2) NOT NULL,
                                       FOREIGN KEY (orden_id)    REFERENCES ordenes_compra(id),
                                       FOREIGN KEY (producto_id) REFERENCES productos(id)
);


CREATE TABLE asistencias (
                             id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                             empleado_id  INT UNSIGNED NOT NULL,
                             fecha        DATE NOT NULL,
                             hora_entrada TIME,
                             hora_salida  TIME,
                             observacion  TEXT,
                             registrado_por INT UNSIGNED,
                             created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                             updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                             FOREIGN KEY (empleado_id)    REFERENCES empleados(id),
                             FOREIGN KEY (registrado_por) REFERENCES usuarios(id),
                             UNIQUE KEY uq_empleado_fecha (empleado_id, fecha)
);



-- ============================================================
-- DATOS DE PRUEBA — Supermercado Web
-- ============================================================

USE supermercado;

-- ── Roles (ya existen, solo verificar) ───────────────────────
-- 1 Administrador, 2 Cajero, 3 Almacenista, 4 Repartidor, 5 Cliente

-- ── Usuarios adicionales ──────────────────────────────────────
INSERT INTO usuarios (rol_id, nombre, apellido, email, password, activo) VALUES
                                                                             (2, 'Carlos',   'Mendoza',   'cajero@supermercado.com',      SHA1('cajero123'),   1),
                                                                             (3, 'Patricia', 'Ruiz',      'almacen@supermercado.com',     SHA1('almacen123'),  1),
                                                                             (4, 'Miguel',   'Torres',    'repartidor@supermercado.com',  SHA1('reparto123'),  1),
                                                                             (2, 'Laura',    'Sánchez',   'cajero2@supermercado.com',     SHA1('cajero123'),   1),
                                                                             (3, 'Roberto',  'Jiménez',   'almacen2@supermercado.com',    SHA1('almacen123'),  1);

-- ── Empleados ─────────────────────────────────────────────────
INSERT INTO empleados (usuario_id, puesto, salario, fecha_ingreso, turno, activo) VALUES
                                                                                      (2, 'Cajero',       8500.00,  '2022-03-15', 'matutino',   1),
                                                                                      (3, 'Almacenista',  7800.00,  '2021-08-01', 'vespertino', 1),
                                                                                      (4, 'Repartidor',   7200.00,  '2023-01-10', 'matutino',   1),
                                                                                      (5, 'Cajera',       8500.00,  '2022-11-20', 'vespertino', 1),
                                                                                      (6, 'Almacenista',  7800.00,  '2023-05-05', 'nocturno',   1);

-- ── Categorías (ya existen, agregar más) ──────────────────────
INSERT INTO categorias (nombre, descripcion) VALUES
                                                 ('Congelados',       'Productos congelados y refrigerados'),
                                                 ('Snacks',           'Botanas y dulces'),
                                                 ('Higiene personal', 'Cuidado e higiene personal');

-- ── Proveedores ───────────────────────────────────────────────
INSERT INTO proveedores (nombre, contacto, telefono, email, direccion, activo) VALUES
                                                                                   ('Distribuidora Norte S.A.',  'Juan Pérez',    '662-100-2000', 'ventas@disnorte.com',   'Calle Industrial 45, Hermosillo', 1),
                                                                                   ('Lácteos del Valle',         'María López',   '662-200-3000', 'pedidos@lacval.com',    'Blvd. Ley 890, Hermosillo',       1),
                                                                                   ('Abarrotes Mayoristas',      'Pedro Gómez',   '662-300-4000', 'compras@abamay.com',    'Av. Reforma 123, Hermosillo',     1),
                                                                                   ('Carnes Premium',            'Ana Rodríguez', '662-400-5000', 'ventas@carnprem.com',   'Periférico Norte 567, Hermosillo',1),
                                                                                   ('Bebidas y Más',             'Luis Morales',  '662-500-6000', 'pedidos@bebymas.com',   'Calle 5 de Mayo 22, Hermosillo',  1);

-- ── Productos ─────────────────────────────────────────────────
INSERT INTO productos (categoria_id, proveedor_id, nombre, descripcion, precio_compra, precio_venta, stock, stock_minimo, codigo_barras, activo) VALUES
                                                                                                                                                     -- Frutas y verduras (cat 1)
                                                                                                                                                     (1, 1, 'Manzana roja kg',        'Manzana roja fresca por kilogramo',    12.00,  22.00,  80,  20, '7501000001001', 1),
                                                                                                                                                     (1, 1, 'Plátano kg',             'Plátano tabasco por kilogramo',         8.00,  15.00,  60,  15, '7501000001002', 1),
                                                                                                                                                     (1, 1, 'Jitomate bola kg',       'Jitomate bola fresco',                  9.00,  18.00,  50,  15, '7501000001003', 1),
                                                                                                                                                     (1, 1, 'Cebolla blanca kg',      'Cebolla blanca fresca',                 7.00,  14.00,  45,  10, '7501000001004', 1),
                                                                                                                                                     (1, 1, 'Papa blanca kg',         'Papa blanca para cocinar',              6.50,  13.00,  70,  20, '7501000001005', 1),
                                                                                                                                                     (1, 1, 'Zanahoria kg',           'Zanahoria fresca',                      5.00,  10.00,  40,  10, '7501000001006', 1),
                                                                                                                                                     (1, 1, 'Limón kg',               'Limón sin semilla',                    10.00,  20.00,   3,  10, '7501000001007', 1),  -- stock bajo
                                                                                                                                                     -- Lácteos (cat 2)
                                                                                                                                                     (2, 2, 'Leche entera 1L',        'Leche entera Lala 1 litro',            14.00,  22.00, 120,  30, '7501055300014', 1),
                                                                                                                                                     (2, 2, 'Leche light 1L',         'Leche deslactosada 1 litro',           15.00,  24.00,  60,  20, '7501055300015', 1),
                                                                                                                                                     (2, 2, 'Yogurt natural 1kg',     'Yogurt natural sin azúcar',            22.00,  36.00,  45,  15, '7501055300016', 1),
                                                                                                                                                     (2, 2, 'Queso fresco 400g',      'Queso fresco artesanal',               28.00,  45.00,  30,  10, '7501055300017', 1),
                                                                                                                                                     (2, 2, 'Mantequilla 90g',        'Mantequilla con sal',                  18.00,  28.00,  40,  10, '7501055300018', 1),
                                                                                                                                                     (2, 2, 'Crema 250ml',            'Crema ácida entera',                   12.00,  20.00,   4,  10, '7501055300019', 1),  -- stock bajo
                                                                                                                                                     -- Carnes (cat 3)
                                                                                                                                                     (3, 4, 'Pechuga de pollo kg',    'Pechuga de pollo sin hueso',           65.00, 105.00,  25,  10, '7501000003001', 1),
                                                                                                                                                     (3, 4, 'Carne molida kg',        'Carne molida de res',                  80.00, 130.00,  20,  10, '7501000003002', 1),
                                                                                                                                                     (3, 4, 'Chuleta de cerdo kg',    'Chuleta de cerdo fresca',              70.00, 115.00,  15,  8,  '7501000003003', 1),
                                                                                                                                                     (3, 4, 'Milanesa de res kg',     'Milanesa de res cortada fina',         90.00, 145.00,  12,  8,  '7501000003004', 1),
                                                                                                                                                     -- Panadería (cat 4)
                                                                                                                                                     (4, 3, 'Pan blanco bimbo',       'Pan blanco Bimbo grande',              28.00,  42.00,  35,  15, '7501000200007', 1),
                                                                                                                                                     (4, 3, 'Pan integral bimbo',     'Pan integral Bimbo grande',            32.00,  48.00,  25,  10, '7501000200008', 1),
                                                                                                                                                     (4, 3, 'Tortillas maíz 1kg',     'Tortillas de maíz 1 kilogramo',       18.00,  28.00,  50,  20, '7501000200009', 1),
                                                                                                                                                     -- Bebidas (cat 5)
                                                                                                                                                     (5, 5, 'Coca Cola 2L',           'Refresco Coca Cola 2 litros',          22.00,  34.00, 100,  30, '7501000563007', 1),
                                                                                                                                                     (5, 5, 'Agua natural 1.5L',      'Agua purificada Ciel 1.5L',            8.00,  14.00,  80,  25, '7501000563008', 1),
                                                                                                                                                     (5, 5, 'Jugo naranja 1L',        'Jugo de naranja natural Del Valle',    22.00,  35.00,  40,  15, '7501000563009', 1),
                                                                                                                                                     (5, 5, 'Cerveza Victoria 355ml', 'Cerveza Victoria lata 355ml',          14.00,  22.00,  60,  20, '7501000563010', 1),
                                                                                                                                                     (5, 5, 'Leche soya 1L',          'Bebida de soya sin lactosa',           20.00,  32.00,   2,  10, '7501000563011', 1),  -- stock bajo
                                                                                                                                                     -- Limpieza (cat 6)
                                                                                                                                                     (6, 3, 'Jabón Roma 500g',        'Jabón Roma para ropa 500g',            12.00,  20.00,  45,  15, '7501022800010', 1),
                                                                                                                                                     (6, 3, 'Detergente Ariel 1kg',   'Detergente Ariel con aroma 1kg',       52.00,  82.00,  30,  10, '7501022800011', 1),
                                                                                                                                                     (6, 3, 'Cloro Cloralex 1L',      'Cloro líquido Cloralex 1 litro',       14.00,  22.00,  40,  15, '7501022800012', 1),
                                                                                                                                                     (6, 3, 'Suavitel 1L',            'Suavizante de telas 1 litro',          28.00,  44.00,  25,  10, '7501022800013', 1),
                                                                                                                                                     -- Abarrotes (cat 7)
                                                                                                                                                     (7, 3, 'Arroz Morelos 1kg',      'Arroz blanco grano largo 1kg',         16.00,  26.00,  60,  20, '7501000700001', 1),
                                                                                                                                                     (7, 3, 'Frijol negro 1kg',       'Frijol negro seco 1kg',                22.00,  35.00,  50,  15, '7501000700002', 1),
                                                                                                                                                     (7, 3, 'Aceite Nutrioli 1L',     'Aceite vegetal Nutrioli 1 litro',      38.00,  58.00,  35,  12, '7501000700003', 1),
                                                                                                                                                     (7, 3, 'Azúcar estándar 1kg',    'Azúcar blanca estándar 1kg',           18.00,  28.00,  55,  20, '7501000700004', 1),
                                                                                                                                                     (7, 3, 'Sal La Fina 1kg',        'Sal de mesa La Fina 1kg',               8.00,  14.00,  40,  15, '7501000700005', 1),
                                                                                                                                                     (7, 3, 'Atún Dolores 140g',      'Atún en agua Dolores 140g',            16.00,  26.00,  70,  20, '7501000700006', 1),
                                                                                                                                                     -- Snacks (cat 8)
                                                                                                                                                     (8, 3, 'Sabritas original 45g',  'Papas Sabritas sabor original',        10.00,  17.00,  80,  25, '7501030470003', 1),
                                                                                                                                                     (8, 3, 'Doritos nacho 65g',      'Tostitos Doritos sabor nacho',         12.00,  20.00,  70,  20, '7501030470004', 1),
                                                                                                                                                     (8, 3, 'Gansito marinela',       'Pastelito Gansito Marinela',            9.00,  15.00,  60,  20, '7501000145001', 1),
                                                                                                                                                     -- Congelados (cat 8)
                                                                                                                                                     (9, 4, 'Pizza Marinela 450g',    'Pizza Marinela 4 quesos congelada',    55.00,  85.00,  20,  8,  '7500435052012', 1),
                                                                                                                                                     (9, 4, 'Helado Nestle 1L',       'Helado Nestle sabor vainilla 1L',      45.00,  72.00,  15,  8,  '7500435052013', 1),
                                                                                                                                                     -- Higiene (cat 9)
                                                                                                                                                     (9, 3, 'Shampoo Head Shoulders', 'Shampoo anticaspa 375ml',              55.00,  88.00,  25,  10, '7500435001001', 1),
                                                                                                                                                     (9, 3, 'Jabón Dove 90g',         'Jabón de tocador Dove 90g',            14.00,  22.00,  40,  15, '7500435001002', 1),
                                                                                                                                                     (9, 3, 'Pasta dental Colgate',   'Pasta dental Colgate triple acción',   28.00,  44.00,   0,  10, '7500435001003', 1);  -- agotado

-- ── Clientes ──────────────────────────────────────────────────
INSERT INTO clientes (nombre, apellido, email, telefono, direccion, password, activo) VALUES
                                                                                          ('Ana',      'García',    'ana.garcia@gmail.com',    '662-111-2222', 'Calle Hidalgo 123, Hermosillo',     SHA1('cliente123'), 1),
                                                                                          ('Luis',     'Martínez',  'luis.mtz@hotmail.com',    '662-222-3333', 'Blvd. Solidaridad 456, Hermosillo', SHA1('cliente123'), 1),
                                                                                          ('Sofía',    'Hernández', 'sofia.hdz@gmail.com',     '662-333-4444', 'Calle Rosales 789, Hermosillo',     SHA1('cliente123'), 1),
                                                                                          ('Jorge',    'López',     'jorge.lopez@yahoo.com',   '662-444-5555', 'Av. Reforma 321, Hermosillo',       SHA1('cliente123'), 1),
                                                                                          ('Gabriela', 'Ramírez',   'gaby.ramirez@gmail.com',  '662-555-6666', 'Periférico Pte 654, Hermosillo',    SHA1('cliente123'), 1);

-- ── Ventas de los últimos 7 días ──────────────────────────────
INSERT INTO ventas (usuario_id, cliente_id, total, tipo, estado, created_at) VALUES
                                                                                 -- Hoy
                                                                                 (1, NULL, 156.00, 'efectivo',      'completada', NOW()),
                                                                                 (2, 1,    342.50, 'tarjeta',       'completada', NOW()),
                                                                                 (2, NULL, 89.00,  'efectivo',      'completada', NOW()),
                                                                                 (2, 2,    245.00, 'transferencia', 'completada', NOW()),
                                                                                 (5, NULL, 178.50, 'efectivo',      'completada', NOW()),
                                                                                 (5, 3,    520.00, 'tarjeta',       'completada', NOW()),
                                                                                 -- Ayer
                                                                                 (1, 1,    380.00, 'efectivo',      'completada', DATE_SUB(NOW(), INTERVAL 1 DAY)),
                                                                                 (2, NULL, 210.50, 'tarjeta',       'completada', DATE_SUB(NOW(), INTERVAL 1 DAY)),
                                                                                 (2, 4,    145.00, 'efectivo',      'completada', DATE_SUB(NOW(), INTERVAL 1 DAY)),
                                                                                 (5, NULL, 480.00, 'transferencia', 'completada', DATE_SUB(NOW(), INTERVAL 1 DAY)),
                                                                                 -- Hace 2 días
                                                                                 (1, 2,    290.00, 'tarjeta',       'completada', DATE_SUB(NOW(), INTERVAL 2 DAY)),
                                                                                 (2, NULL, 175.50, 'efectivo',      'completada', DATE_SUB(NOW(), INTERVAL 2 DAY)),
                                                                                 (5, 5,    630.00, 'tarjeta',       'completada', DATE_SUB(NOW(), INTERVAL 2 DAY)),
                                                                                 -- Hace 3 días
                                                                                 (1, 3,    420.00, 'efectivo',      'completada', DATE_SUB(NOW(), INTERVAL 3 DAY)),
                                                                                 (2, NULL, 195.00, 'tarjeta',       'completada', DATE_SUB(NOW(), INTERVAL 3 DAY)),
                                                                                 (5, 1,    550.00, 'efectivo',      'completada', DATE_SUB(NOW(), INTERVAL 3 DAY)),
                                                                                 -- Hace 4 días
                                                                                 (1, NULL, 310.00, 'tarjeta',       'completada', DATE_SUB(NOW(), INTERVAL 4 DAY)),
                                                                                 (2, 4,    440.50, 'efectivo',      'completada', DATE_SUB(NOW(), INTERVAL 4 DAY)),
                                                                                 -- Hace 5 días
                                                                                 (1, 5,    280.00, 'efectivo',      'completada', DATE_SUB(NOW(), INTERVAL 5 DAY)),
                                                                                 (2, NULL, 390.00, 'tarjeta',       'completada', DATE_SUB(NOW(), INTERVAL 5 DAY)),
                                                                                 -- Hace 6 días
                                                                                 (1, 2,    510.00, 'efectivo',      'completada', DATE_SUB(NOW(), INTERVAL 6 DAY)),
                                                                                 (2, NULL, 220.00, 'tarjeta',       'completada', DATE_SUB(NOW(), INTERVAL 6 DAY));

-- ── Detalles de ventas de hoy ─────────────────────────────────
INSERT INTO venta_detalles (venta_id, producto_id, cantidad, precio_unitario, subtotal) VALUES
                                                                                            -- Venta 1
                                                                                            (1, 8,  3, 22.00, 66.00),
                                                                                            (1, 21, 2, 34.00, 68.00),
                                                                                            (1, 30, 1, 22.00, 22.00),
                                                                                            -- Venta 2
                                                                                            (2, 14, 2, 105.00, 210.00),
                                                                                            (2, 31, 2,  26.00,  52.00),
                                                                                            (2, 22,  4,  14.00,  56.00),
                                                                                            (2, 37,  2,  17.00,  34.00) -- ajustado si no llega a 342.50 exacto
;

-- ── Pedidos en línea ──────────────────────────────────────────
INSERT INTO pedidos (cliente_id, usuario_id, total, estado, direccion_entrega, fecha_entrega, created_at) VALUES
                                                                                                              (1, NULL, 420.00, 'pendiente',   'Calle Hidalgo 123, Hermosillo',     NULL,                              NOW()),
                                                                                                              (2, 4,    280.50, 'confirmado',  'Blvd. Solidaridad 456, Hermosillo', DATE_ADD(NOW(), INTERVAL 2 HOUR),  NOW()),
                                                                                                              (3, 4,    156.00, 'enviado',     'Calle Rosales 789, Hermosillo',     DATE_ADD(NOW(), INTERVAL 1 HOUR),  DATE_SUB(NOW(), INTERVAL 2 HOUR)),
                                                                                                              (4, NULL, 890.00, 'pendiente',   'Av. Reforma 321, Hermosillo',       NULL,                              DATE_SUB(NOW(), INTERVAL 1 HOUR)),
                                                                                                              (5, 4,    340.00, 'entregado',   'Periférico Pte 654, Hermosillo',    DATE_SUB(NOW(), INTERVAL 3 HOUR),  DATE_SUB(NOW(), INTERVAL 5 HOUR)),
                                                                                                              (1, 4,    215.00, 'confirmado',  'Calle Hidalgo 123, Hermosillo',     DATE_ADD(NOW(), INTERVAL 3 HOUR),  DATE_SUB(NOW(), INTERVAL 1 DAY)),
                                                                                                              (2, NULL, 560.00, 'pendiente',   'Blvd. Solidaridad 456, Hermosillo', NULL,                              DATE_SUB(NOW(), INTERVAL 1 DAY));

-- ── Detalles de pedidos ───────────────────────────────────────
INSERT INTO pedido_detalles (pedido_id, producto_id, cantidad, precio_unitario, subtotal) VALUES
                                                                                              (1, 8,  4, 22.00, 88.00),
                                                                                              (1, 15, 2, 130.00, 260.00),
                                                                                              (1, 31, 2, 26.00,  52.00),
                                                                                              (1, 22, 2, 14.00,  28.00),  -- ajustado
                                                                                              (2, 9,  3, 24.00,  72.00),
                                                                                              (2, 19, 2, 42.00,  84.00),
                                                                                              (2, 36, 3, 26.00,  78.00),
                                                                                              (3, 23, 3, 35.00, 105.00),
                                                                                              (3, 30, 1, 22.00,  22.00),
                                                                                              (3, 32, 1, 35.00,  35.00);

-- ── Órdenes de compra ─────────────────────────────────────────
INSERT INTO ordenes_compra (proveedor_id, usuario_id, total, estado, fecha_entrega) VALUES
                                                                                        (2, 1, 1800.00, 'pendiente',  DATE_ADD(NOW(), INTERVAL 3 DAY)),
                                                                                        (1, 1, 2400.00, 'recibida',   DATE_SUB(NOW(), INTERVAL 2 DAY)),
                                                                                        (4, 1, 3200.00, 'pendiente',  DATE_ADD(NOW(), INTERVAL 5 DAY)),
                                                                                        (3, 1, 1500.00, 'recibida',   DATE_SUB(NOW(), INTERVAL 5 DAY));

INSERT INTO orden_compra_detalles (orden_id, producto_id, cantidad, precio_unitario, subtotal) VALUES
                                                                                                   (1, 8,  50, 14.00, 700.00),
                                                                                                   (1, 9,  30, 15.00, 450.00),
                                                                                                   (1, 10, 20, 22.00, 440.00),
                                                                                                   (1, 13, 10, 21.00, 210.00),
                                                                                                   (2, 1,  40, 12.00, 480.00),
                                                                                                   (2, 3,  50,  9.00, 450.00),
                                                                                                   (2, 5,  60,  6.50, 390.00),
                                                                                                   (2, 6,  60,  5.00, 300.00),
                                                                                                   (2, 7,  60, 10.00, 600.00),
                                                                                                   (3, 14, 20, 65.00, 1300.00),
                                                                                                   (3, 15, 15, 80.00, 1200.00),
                                                                                                   (4, 31, 30, 16.00, 480.00),
                                                                                                   (4, 32, 20, 22.00, 440.00),
                                                                                                   (4, 35, 20,  8.00, 160.00);

-- ── Asistencias (últimos 7 días) ──────────────────────────────
INSERT INTO asistencias (empleado_id, fecha, hora_entrada, hora_salida, registrado_por) VALUES
                                                                                            (1, CURDATE(),                           '08:00:00', '16:00:00', 1),
                                                                                            (1, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '08:05:00', '16:00:00', 1),
                                                                                            (1, DATE_SUB(CURDATE(), INTERVAL 2 DAY), '08:00:00', '16:00:00', 1),
                                                                                            (1, DATE_SUB(CURDATE(), INTERVAL 3 DAY), '08:15:00', '16:00:00', 1),
                                                                                            (1, DATE_SUB(CURDATE(), INTERVAL 4 DAY), '08:00:00', '16:00:00', 1),
                                                                                            (2, CURDATE(),                           '14:00:00', '22:00:00', 1),
                                                                                            (2, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '14:00:00', '22:00:00', 1),
                                                                                            (2, DATE_SUB(CURDATE(), INTERVAL 2 DAY), '14:00:00', '22:00:00', 1),
                                                                                            (3, CURDATE(),                           '08:00:00', '16:00:00', 1),
                                                                                            (3, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '08:00:00', '16:00:00', 1),
                                                                                            (4, CURDATE(),                           '14:00:00', '22:00:00', 1),
                                                                                            (4, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '14:10:00', '22:00:00', 1),
                                                                                            (5, DATE_SUB(CURDATE(), INTERVAL 1 DAY), '22:00:00', '06:00:00', 1),
                                                                                            (5, DATE_SUB(CURDATE(), INTERVAL 2 DAY), '22:00:00', '06:00:00', 1);

ALTER TABLE proveedores
    ADD COLUMN rfc VARCHAR(13) NULL AFTER email;

-- Actualizar datos de prueba con RFC
UPDATE proveedores SET rfc = 'DNO220315AB1' WHERE id = 1;
UPDATE proveedores SET rfc = 'LVA180901CD2' WHERE id = 2;
UPDATE proveedores SET rfc = 'AMA150620EF3' WHERE id = 3;
UPDATE proveedores SET rfc = 'CPR190412GH4' WHERE id = 4;
UPDATE proveedores SET rfc = 'BYM210830IJ5' WHERE id = 5;


CREATE TABLE IF NOT EXISTS configuracion (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    clave      VARCHAR(50) NOT NULL UNIQUE,
    valor      TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Valores iniciales
INSERT INTO configuracion (clave, valor) VALUES
                                             ('negocio_nombre',    'Supermercado Web'),
                                             ('negocio_direccion', 'Calle Principal 123, Hermosillo, Sonora'),
                                             ('negocio_telefono',  '662-000-0000'),
                                             ('negocio_email',     'contacto@supermercado.com'),
                                             ('negocio_rfc',       'SUP000000AA1'),
                                             ('negocio_logo',      'logo_super.png'),
                                             ('timezone',          'America/Hermosillo'),
                                             ('moneda',            'MXN'),
                                             ('ticket_mensaje',    '¡Gracias por su compra!');

ALTER TABLE clientes
    ADD COLUMN fecha_nacimiento DATE NULL AFTER direccion,
    ADD COLUMN rfc VARCHAR(13) NULL AFTER fecha_nacimiento;



ALTER TABLE ventas
    ADD COLUMN descuento DECIMAL(5,2) DEFAULT 0.00 AFTER total,
    ADD COLUMN subtotal  DECIMAL(10,2) DEFAULT 0.00 AFTER total;




CREATE TABLE IF NOT EXISTS promociones (
                                           id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                                           producto_id   INT UNSIGNED NOT NULL,
                                           nombre        VARCHAR(120) NOT NULL,
                                           tipo          ENUM('porcentaje', 'precio_fijo', '2x1', 'cantidad_minima') NOT NULL,
                                           valor         DECIMAL(10,2) NOT NULL DEFAULT 0.00,  -- % o precio fijo
                                           cantidad_min  INT DEFAULT 1,                         -- para tipo cantidad_minima
                                           fecha_inicio  DATE NOT NULL,
                                           fecha_fin     DATE NOT NULL,
                                           activo        TINYINT(1) DEFAULT 1,
                                           created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                           updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                                           FOREIGN KEY (producto_id) REFERENCES productos(id)
);

-- Datos de prueba
INSERT INTO promociones (producto_id, nombre, tipo, valor, cantidad_min, fecha_inicio, fecha_fin) VALUES
                                                                                                      (1,  '20% off Manzana roja',     'porcentaje',    20.00, 1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 7 DAY)),
                                                                                                      (8,  'Leche a precio especial',  'precio_fijo',   18.00, 1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
                                                                                                      (21, '2x1 Coca Cola',            '2x1',            0.00, 2, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 DAY)),
                                                                                                      (31, '10% off Arroz x3',         'cantidad_minima',10.00, 3, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 10 DAY));



ALTER TABLE venta_detalles
    ADD COLUMN precio_original DECIMAL(10,2) DEFAULT 0.00 AFTER precio_unitario,
    ADD COLUMN descuento_promo DECIMAL(10,2) DEFAULT 0.00 AFTER precio_original,
    ADD COLUMN promo_id        INT UNSIGNED NULL AFTER descuento_promo,
    ADD COLUMN promo_desc      VARCHAR(100) NULL AFTER promo_id;

ALTER TABLE usuarios MODIFY password VARCHAR(255) NOT NULL;
