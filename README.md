# SuZuMa — Sistema Web para Supermercado

> Sistema de gestión integral que combina un **POS interno** con una **tienda en línea**, construido en PHP 8 con arquitectura MVC propia.

![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?style=flat&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=flat&logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-4-7952B3?style=flat&logo=bootstrap&logoColor=white)
![Twig](https://img.shields.io/badge/Twig-3-bacb2b?style=flat)
![Eloquent](https://img.shields.io/badge/Eloquent_ORM-9-FF2D20?style=flat)
![License](https://img.shields.io/badge/Licencia-MIT-green?style=flat)

---

## Contenido

- [Características](#características)
- [Stack tecnológico](#stack-tecnológico)
- [Arquitectura](#arquitectura)
- [Módulos del sistema](#módulos-del-sistema)
- [Instalación](#instalación)
- [Configuración del entorno](#configuración-del-entorno)
- [Migraciones de base de datos](#migraciones-de-base-de-datos)
- [Seguridad](#seguridad)
- [Estructura de directorios](#estructura-de-directorios)

---

## Características

### Sistema interno (POS)
- **Punto de Venta** — escáner de código de barras, carrito, pagos en efectivo/tarjeta/transferencia
- **Inventario** — CRUD de productos con imagen, venta por peso, alertas de stock mínimo
- **Corte de Caja** — apertura/cierre de caja con conciliación de efectivo
- **Devoluciones** — parciales o totales con restauración automática de stock
- **Proveedores** — perfiles y órdenes de compra
- **Clientes** — CRM con historial de compras
- **Promociones** — descuentos por porcentaje, precio fijo, 2×1 y cantidad mínima
- **Empleados** — perfiles, turnos y registro de asistencia
- **Reportes** — ventas, utilidades, inventario y empleados (PDF + CSV)
- **Auditoría** — bitácora de acciones por usuario
- **RBAC** — permisos granulares configurables por rol desde la UI

### Tienda en línea (e-commerce)
- Catálogo con búsqueda y filtros por categoría
- Detalle de producto con selector de peso/cantidad
- Carrito persistente en sesión
- Checkout con dirección de entrega
- Cupones de descuento
- Seguimiento de pedido por código
- Registro, login y recuperación de contraseña para clientes
- Reseñas de productos (solo compradores verificados)
- Lista de deseos (wishlist)
- Cuenta del cliente con historial de pedidos

---

## Stack tecnológico

| Capa | Tecnología |
|------|-----------|
| Lenguaje | PHP 8.1+ con `strict_types` |
| Router | [Phroute](https://github.com/mrjgreen/phroute) 2.x |
| Plantillas | [Twig](https://twig.symfony.com/) 3.x |
| ORM | [Eloquent](https://laravel.com/docs/eloquent) (illuminate/database 9) |
| Validación | [Respect/Validation](https://respect-validation.readthedocs.io/) 2.x |
| PDF | [DomPDF](https://github.com/dompdf/dompdf) 2.x |
| Imágenes | [Intervention/Image](https://image.intervention.io/) 2.7 |
| Email | [PHPMailer](https://github.com/PHPMailer/PHPMailer) 7.x |
| Logging | [Monolog](https://github.com/Seldaek/monolog) 2.x |
| Frontend | Bootstrap 4 · jQuery · Chart.js 4 · SweetAlert2 · Font Awesome 5 |
| Servidor | Apache + `mod_rewrite` (AMPPS / XAMPP) |
| Base de datos | MySQL 8 — charset `utf8mb4` |

---

## Arquitectura

El sistema sigue el patrón **MVC con capa Repository**. No usa ningún framework completo — el núcleo (`core/`) conecta paquetes Composer independientes.

```
Petición HTTP
    │
    ▼
Apache (.htaccess) → index.php (bootstrap)
    │
    ▼
Phroute Dispatcher
    │
    ├─ Filtro before: auth / csrf / can:permiso
    │
    ▼
Controller → Repository → Eloquent Model → MySQL
    │
    ▼
Twig → HTML  |  json_encode(ResponseHelper) → AJAX
```

### Ciclo de vida de una petición

1. `session_start()` + parseo de `.env`
2. `ExceptionHandler::register()` — captura global de errores
3. `ServicesContainer` — inicializa config + Eloquent Capsule
4. Definición de constantes (`_BASE_HTTP_`, `_BASE_PATH_`, etc.)
5. Carga de `filters.php` y `routes.php`
6. Phroute despacha → filtros de seguridad → método del Controller
7. GET devuelve `string` (HTML renderizado); POST hace `echo json_encode($rh)`

---

## Módulos del sistema

### Sistema interno

| Ruta | Módulo | Roles con acceso |
|------|--------|-----------------|
| `/home` | Dashboard con métricas y gráficas | Todos |
| `/ventas` | Punto de Venta | Admin, Cajero |
| `/corte-caja` | Corte de caja | Admin, Cajero |
| `/devoluciones` | Devoluciones | Admin, Cajero |
| `/inventario` | Inventario de productos | Admin, Analista |
| `/proveedores` | Proveedores y órdenes de compra | Admin, Analista |
| `/clientes` | Gestión de clientes | Admin, Analista |
| `/promociones` | Motor de descuentos | Admin, Cajero |
| `/pedidos` | Pedidos en línea (kanban) | Admin, Analista, Repartidor |
| `/empleados` | Recursos humanos | Admin |
| `/reportes` | Reportes y exportaciones | Admin, Analista |
| `/configuracion` | Configuración del negocio | Admin |
| `/permisos` | Gestión de permisos RBAC | Admin |
| `/cupones` | Cupones de descuento | Admin |
| `/auditoria` | Bitácora de auditoría | Admin |

### Tienda pública

| Ruta | Descripción |
|------|-------------|
| `/tienda` | Página de inicio |
| `/tienda/catalogo` | Catálogo con filtros |
| `/tienda/producto/{id}` | Detalle del producto |
| `/tienda/checkout` | Carrito y datos de entrega |
| `/tienda/seguimiento` | Estado del pedido |
| `/tienda/cuenta` | Perfil y pedidos del cliente |
| `/tienda/wishlist` | Lista de deseos |

---

## Instalación

### Requisitos previos

- PHP 8.1+ con extensiones: `pdo_mysql`, `openssl`, `gd`, `mbstring`, `fileinfo`
- MySQL 8.0+
- Apache con `mod_rewrite` habilitado
- Composer

### Pasos

```bash
# 1. Clonar el repositorio en la carpeta web del servidor
git clone https://github.com/tu-usuario/suzuma.git /ruta/al/servidor/desweb

# 2. Instalar dependencias de Composer
cd /ruta/al/servidor/desweb
composer install

# 3. Copiar y configurar el entorno
cp .env.example .env
# Editar .env con los valores correctos

# 4. Crear la base de datos
mysql -u root -p -e "CREATE DATABASE supermercado CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 5. Aplicar schema base y migraciones
mysql -u root -p supermercado < DATA_BASE.sql
mysql -u root -p supermercado < migration_corte_caja.sql
mysql -u root -p supermercado < migration_gramajes.sql
mysql -u root -p supermercado < migration_devoluciones.sql
mysql -u root -p supermercado < migration_cupones.sql
mysql -u root -p supermercado < migration_resenas.sql
mysql -u root -p supermercado < migration_wishlist.sql
mysql -u root -p supermercado < migration_rbac.sql

# 6. Crear carpetas con permisos de escritura
mkdir -p cache log public/images/productos
chmod 775 cache log public/images/productos
```

---

## Configuración del entorno

Copia `.env.example` a `.env` y ajusta los valores:

```env
APP_ENV=dev           # dev | prod | stop
APP_SECRET=           # clave de cifrado para la sesión (mínimo 32 chars aleatorios)

DB_HOST=127.0.0.1
DB_DATABASE=supermercado
DB_USERNAME=root
DB_PASSWORD=

SESSION_NAME=application-auth
SESSION_TIME=10       # duración de sesión en horas

MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM=noreply@tudominio.com
```

> **`APP_ENV=stop`** muestra una página de mantenimiento a todos los visitantes.

---

## Migraciones de base de datos

| Archivo | Descripción |
|---------|-------------|
| `DATA_BASE.sql` | Schema base — todas las tablas principales |
| `migration_corte_caja.sql` | Tabla `cortes_caja` |
| `migration_gramajes.sql` | Columnas `venta_por_peso` y `unidad_peso` en productos |
| `migration_devoluciones.sql` | Tablas `devoluciones` y `devolucion_detalles` |
| `migration_cupones.sql` | Tabla `cupones` |
| `migration_resenas.sql` | Tabla `resenas` |
| `migration_wishlist.sql` | Tabla `wishlist` |
| `migration_rbac.sql` | Tablas `permisos` y `rol_permisos` con datos iniciales |

---

## Seguridad

| Capa | Mecanismo |
|------|-----------|
| Sesión | Cookie AES-256-CBC cifrada, ligada a IP + User-Agent |
| CSRF | Token por sesión validado en cada POST |
| Autorización | RBAC con permisos almacenados en BD y caché de 5 min |
| Validación | Respect/Validation en el borde del sistema |
| SQL Injection | Eloquent ORM + Query Builder (sin SQL crudo con entrada de usuario) |
| XSS | Auto-escape de Twig en todas las salidas `{{ }}` |
| Errores | Modo `prod` desactiva `error_reporting`; sin stack traces en respuestas |

### Sistema RBAC

Los permisos se asignan por **rol** (no por usuario individual). El Administrador siempre tiene acceso total y no puede ser restringido. Los cambios se reflejan inmediatamente gracias a la invalidación del caché.

```
Admin (1)     → todos los permisos (bypass de BD)
Cajero (2)    → ventas, corte de caja, devoluciones, promociones, clientes (ver)
Analista (3)  → inventario, proveedores, clientes, pedidos, reportes
Repartidor (4)→ pedidos (ver y gestionar)
```

---

## Estructura de directorios

```
desweb/
├── index.php                  Punto de entrada — bootstrap + routing
├── config.php                 Array de configuración (lee .env)
├── composer.json
├── .env                       Secretos del entorno (no se sube a git)
│
├── core/                      Mini-framework propio
│   ├── Controller.php         Base controller (Twig, render, funciones globales)
│   ├── Auth.php               Sesión cifrada con AES-256-CBC
│   ├── Csrf.php               Generación y validación de token CSRF
│   ├── DbContext.php          Inicialización de Eloquent Capsule
│   ├── ServicesContainer.php  Singleton de config + BD
│   ├── ExceptionHandler.php   Captura global → JSON o HTML
│   └── Log.php                Wrapper de Monolog (archivos diarios)
│
├── app/
│   ├── routes.php             Definición de todas las rutas
│   ├── filters.php            Filtros before (auth, csrf, can:slug)
│   ├── Controllers/           Un controlador por módulo
│   ├── Models/                Modelos Eloquent
│   ├── Repositories/          Toda la lógica de BD (nunca en controllers)
│   ├── Validations/           Clases de validación estáticas
│   ├── Middlewares/           AuthMiddleware, RoleMiddleware (RBAC)
│   ├── Helpers/               ResponseHelper, UrlHelper
│   ├── Services/              CacheService
│   └── Views/                 Plantillas Twig por módulo
│
├── public/                    Assets web (CSS, JS, imágenes)
│   └── images/productos/      Imágenes subidas de productos
│
├── log/                       Archivos de log diarios (gitignored)
├── cache/                     Caché de consultas frecuentes (gitignored)
├── vendor/                    Dependencias de Composer (gitignored)
└── *.sql                      Schema y migraciones
```

---

## Autor

**Noe Cazarez Camargo** — Desarrollador full-stack
Proyecto desarrollado con [Claude Code](https://claude.ai/code) como asistente de ingeniería.

---

## Licencia

Este proyecto está bajo la licencia **MIT**. Consulta el archivo `LICENSE` para más detalles.