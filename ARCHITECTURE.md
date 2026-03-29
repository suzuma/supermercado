# ARCHITECTURE.md — SuZuMa Supermarket Web System

## Table of contents

1. [Overview](#overview)
2. [Request lifecycle](#request-lifecycle)
3. [Directory structure](#directory-structure)
4. [Core layer](#core-layer)
5. [App layer](#app-layer)
6. [Data layer](#data-layer)
7. [View layer](#view-layer)
8. [Security model](#security-model)
9. [Module map](#module-map)
10. [Model relationship graph](#model-relationship-graph)

---

## Overview

SuZuMa is a custom PHP 8 MVC application built on a lightweight in-house framework. It serves two distinct audiences through a single codebase:

- **Internal system** — POS (point-of-sale), inventory, employees, reports (requires login)
- **Public storefront** — e-commerce catalog, cart, orders (public access)

There is no off-the-shelf framework — the routing, auth, templating, and DI wiring are assembled from individual Composer packages glued together in `core/`.

```
Browser
  │
  ▼
Apache (.htaccess mod_rewrite)
  │  all requests → index.php
  ▼
index.php  ── bootstrap ──► ServicesContainer (config + DB)
  │
  ▼
Phroute Dispatcher
  │  matches URI to Controller::method
  ▼
Filter chain (auth / csrf / role)
  │
  ▼
Controller  ──► Repository  ──► Eloquent Model  ──► MySQL
  │
  ▼
Twig renderer  ──► HTML response
```

---

## Request lifecycle

Step-by-step for every HTTP request:

```
1. session_start()

2. .env parsed  →  $_ENV populated

3. ExceptionHandler::register()
   └─ sets global exception handler + shutdown handler

4. ServicesContainer::setConfig( config.php )
   └─ stores config array globally (singleton)

5. ServicesContainer::initializeDbContext()
   └─ DbContext::initialize()
      └─ Capsule::addConnection() + setAsGlobal() + bootEloquent()

6. Constants defined
   _BASE_HTTP_  _BASE_PATH_  _APP_PATH_  _LOG_PATH_  _CACHE_PATH_  _CURRENT_URI_

7. RouteCollector created
   └─ filters.php  →  registers 'auth', 'csrf', 'isAdmin', 'isSeller', 'isAnalyst'
   └─ routes.php   →  registers all route → controller mappings

8. Dispatcher::dispatch( METHOD, URI )
   └─ before-filter executes (e.g. 'auth')
      ├─ AuthMiddleware::isLoggedIn()  → redirect to /auth if false
      └─ Csrf::validateOrFail()       → HTTP 419 JSON if POST token invalid

9. Controller::__construct()
   └─ parent::__construct()  →  Twig environment + filters + functions registered
   └─ Repository instances created

10. Controller method executes
    ├─ GET  → Repository read → $this->render('view.twig', $data)  → string returned
    └─ POST → validate input → Repository write (transaction) → echo json_encode($rh)

11. Response echoed by Dispatcher
```

---

## Directory structure

```
desweb/
├── index.php                  Entry point — bootstrap + routing
├── config.php                 Returns config array (reads from .env)
├── composer.json
├── .env                       Environment secrets (not committed)
│
├── core/                      In-house micro-framework
│   ├── Controller.php         Base controller (Twig setup, render)
│   ├── Auth.php               Cookie-based auth (AES-256-CBC)
│   ├── Csrf.php               CSRF token generation + validation
│   ├── DbContext.php          Eloquent Capsule initializer
│   ├── ServicesContainer.php  Global config + DB singleton
│   ├── ExceptionHandler.php   Global exception → JSON or HTML
│   └── Log.php                Monolog wrapper (daily files)
│
├── app/
│   ├── routes.php             All route definitions
│   ├── filters.php            Phroute before-filters (middleware)
│   │
│   ├── Controllers/           One class per module (15 total)
│   ├── Models/                Eloquent models (19 total)
│   ├── Repositories/          All DB queries (13 total)
│   ├── Validations/           Input validation (3 classes)
│   ├── Middlewares/           AuthMiddleware, RoleMiddleware
│   ├── Helpers/               ResponseHelper, UrlHelper
│   └── Views/                 Twig templates per module
│
├── public/                    Web-accessible static assets
│   ├── css/                   Custom styles
│   ├── js/                    Custom scripts + jQuery
│   ├── bootstrap4/
│   ├── fontawesome590/
│   ├── bower_components/
│   └── images/
│       └── productos/         Uploaded product images
│
├── log/                       Daily log files (gitignored)
├── vendor/                    Composer dependencies
│
└── *.sql                      Schema + migration files
    ├── DATA_BASE.sql
    ├── migration_corte_caja.sql
    ├── migration_gramajes.sql
    └── migration_devoluciones.sql
```

---

## Core layer

The `core/` directory is the mini-framework that all application code depends on. None of these classes know about business logic.

### ServicesContainer

Global state holder. The only shared mutable state in the system.

```php
namespace Core;
class ServicesContainer {
    private static array  $config;
    private static bool   $dbContext = false;

    public static function setConfig(array $value): void
    public static function getConfig(): array
    public static function initializeDbContext(): void  // idempotent — checks flag
}
```

Called once in `index.php` before routing. All other classes read config via `ServicesContainer::getConfig()`.

---

### Controller (base class)

Every application controller extends this. Its only job is Twig setup and the `render()` method.

```php
namespace Core;
class Controller {
    protected $provider;  // Twig\Environment

    public function __construct()
    protected function render(string $view, array $data = []): string
}
```

**What `__construct` registers in Twig:**

| Type | Name | Maps to |
|------|------|---------|
| Filter | `url` | `UrlHelper::base($route)` |
| Filter | `public` | `UrlHelper::public($route)` |
| Filter | `padLeft` | `str_pad($input, $zeros, '0', STR_PAD_LEFT)` |
| Function | `user` | `Auth::getCurrentUser()` |
| Function | `isAdmin` | `RoleMiddleware::isAdmin()` |
| Function | `isSeller` | `RoleMiddleware::isSeller()` |
| Function | `isAnalyst` | `RoleMiddleware::isAnalyst()` |
| Function | `csrf_token` | `Csrf::token()` |

**What `render()` auto-injects into every view:**

```php
$data['_route'] = first segment of _CURRENT_URI_   // for active nav states
$data['_cfg']   = [
    'moneda'         => Configuracion::get('moneda', 'MXN'),
    'simbolo'        => 'USD $' | '$',
    'negocio_nombre' => Configuracion::get('negocio_nombre'),
    'negocio_tel'    => Configuracion::get('negocio_telefono'),
    'ticket_mensaje' => Configuracion::get('ticket_mensaje'),
    'negocio_logo'   => Configuracion::get('negocio_logo'),
]
```

---

### Auth

Handles admin session via an encrypted HTTP-only cookie. Completely separate from the client/storefront session (which uses `$_SESSION`).

```php
namespace Core;
class Auth {
    public static function signIn(array $data): void
    public static function destroy(): void
    public static function getCurrentUser(): \stdClass
    public static function isLoggedIn(): bool
}
```

**Encryption flow:**

```
signIn($data)
  │
  ├─ Key = MD5( secret + client_ip + user_agent + hostname )
  ├─ IV  = openssl_random_pseudo_bytes(16)
  ├─ Ciphertext = openssl_encrypt( serialize($data), 'AES-256-CBC', SHA256(key), OPENSSL_RAW_DATA, IV )
  ├─ Stored = base64( IV . ciphertext )
  └─ setcookie( SESSION_NAME, stored, +86400s, httponly=true, samesite=None )

getCurrentUser()
  │
  ├─ base64_decode( $_COOKIE[SESSION_NAME] )
  ├─ Extract IV (first 16 bytes)
  ├─ openssl_decrypt( remainder, 'AES-256-CBC', SHA256(key), OPENSSL_RAW_DATA, IV )
  └─ return unserialize( plaintext )  →  stdClass
```

The key changes per-client (IP + UA bound), so stolen cookies from another machine are useless.

---

### Csrf

Token stored server-side in `$_SESSION['_csrf_token']`. Validated on every POST in protected routes.

```php
namespace Core;
class Csrf {
    public static function token(): string          // generates if missing
    public static function validate(string $token): bool
    public static function validateOrFail(): void   // HTTP 419 JSON on failure
}
```

Token sources accepted by `validateOrFail()`:
1. `$_SERVER['HTTP_X_CSRF_TOKEN']` (AJAX header — set globally via `$.ajaxSetup`)
2. `$_POST['_csrf_token']` (hidden form field)

---

### DbContext

Thin wrapper that boots Eloquent's Capsule once.

```php
namespace Core;
class DbContext {
    public static function initialize(): void
    // → new Capsule → addConnection($config['database']) → setAsGlobal() → bootEloquent()
}
```

After this call, Eloquent models work anywhere via `Model::query()`, and raw queries via `Capsule::table()` or `DB::table()`.

---

### ExceptionHandler

Catches all unhandled exceptions globally. Distinguishes AJAX from normal requests to return the right format.

```php
namespace Core;
class ExceptionHandler {
    public static function register(): void
    public static function handle(Throwable $e): void
    public static function handleShutdown(): void
}
```

Response matrix:

| Exception | HTTP status | Format |
|-----------|-------------|--------|
| `HttpRouteNotFoundException` | 404 | JSON or `errors/404.php` |
| `HttpMethodNotAllowedException` | 405 | JSON or `errors/404.php` |
| Any other | 500 | JSON or `errors/500.php` |

AJAX detection: checks `$_SERVER['HTTP_X_REQUESTED_WITH']`, CSRF header, or `Accept: application/json`.

---

### Log

Static wrapper around Monolog. Each log level writes to its own daily file.

```php
namespace Core;
class Log {
    public static function error(string $name, string $message): void
    public static function warning(string $name, string $message): void
    public static function info(string $name, string $message): void
    public static function critical(string $name, string $message): void
    public static function debug(string $name, string $message): void
}
// Files: /log/{level}-YYYYMMDD.log
```

Usage convention throughout the codebase:
```php
} catch (Exception $e) {
    Log::error(ClassName::class, $e->getMessage());
}
```

---

## App layer

### Routing and filters

`app/routes.php` and `app/filters.php` are loaded directly by `index.php`. They have no classes — just procedural calls to `$router`.

**Filter execution order for a protected POST:**
```
HTTP POST /ventas/registrar
  │
  ├─ [before: auth]
  │    ├─ AuthMiddleware::isLoggedIn()   → redirect /auth if false
  │    └─ Csrf::validateOrFail()         → HTTP 419 if token invalid
  │
  └─ VentasController::postRegistrar()
```

---

### ResponseHelper

The single shape for all POST responses. Both success and error use the same structure so JavaScript only needs to check `res.response`.

```php
class ResponseHelper {
    public $result      = null;     // any payload (array, object, scalar)
    public $response    = false;    // bool — true = success
    public $message     = '...';   // user-visible message
    public $href        = null;     // optional redirect hint for JS
    public $function    = null;     // optional JS callback name
    public $filter      = null;
    public $validations = [];       // field → message for form errors

    public function setResponse(bool $r, string $m = ''): self
    public function setErrors(array $errors): self   // sets response=false + validations
}
```

---

### Validations

Static classes using Respect/Validation. They **do not return** — they call `exit(json_encode($rh))` on failure, which terminates the request immediately. On success, execution simply continues.

```php
// Usage in controller:
ProductoValidation::validar($_POST);  // exits here if invalid
// ... rest of controller only runs if valid
```

Three validation classes:
- `ProductoValidation` — validates product form fields
- `EmpleadoValidation` — validates employee form (password only required for new records)
- `ProveedorValidation` — validates supplier form fields

---

### Middlewares

```php
// AuthMiddleware
public static function isLoggedIn(): void
// → Auth::isLoggedIn() → UrlHelper::redirect('auth') if false

// RoleMiddleware
public static function isAdmin(): bool    // rol_id === 1
public static function isSeller(): bool   // rol_id === 1 or 2
public static function isAnalyst(): bool  // rol_id === 1 or 3
```

---

### Controllers

All 15 controllers follow the same structure:

```php
class XxxController extends Core\Controller {
    private $repo;               // one or more repositories

    public function __construct() {
        parent::__construct();   // MUST be first — sets up Twig
        $this->repo = new XxxRepository();
    }

    // GET methods: return $this->render(...)
    public function getIndex(): string { ... }

    // POST methods: echo json_encode($rh), no return
    public function postGuardar(): void { ... }
}
```

**Controller inventory:**

| Controller | GET methods | POST methods |
|---|---|---|
| HomeController | getIndex | — |
| VentasController | getIndex, getHistorial, getTicket | postRegistrar, postCancelar, postBuscarProducto, postBuscarCliente |
| CorteCajaController | getIndex, getHistorial, getDetalle | postRegistrar |
| DevolucionesController | getIndex, getHistorial, getRecibo | postBuscarVenta, postRegistrar |
| InventarioController | getIndex, getFormulario | postGuardar, postDesactivar |
| ProveedoresController | getIndex, getFormulario, getOrdenes, getNuevaOrden, getDetalleOrden | postGuardar, postDesactivar, postGuardarOrden, postEstadoOrden |
| ClientesController | getIndex, getFormulario, getPerfil | postGuardar, postDesactivar |
| PromocionesController | getIndex, getFormulario, getPromoProducto | postGuardar, postDesactivar, postCalcular |
| PedidosController | getIndex, getDetalle, getOrden | postEstado, postAsignar, postCancelar |
| EmpleadoController | getIndex, getFormulario | postGuardar, postDesactivar, postAsistencia |
| ReportesController | getIndex, getUtilidades, getPdfVentas, getPdfInventario, getPdfEmpleados, getCsvVentas, getCsvInventario, getCsvUtilidades | — |
| ConfiguracionController | getIndex | postNegocio, postPassword, postUsuario, postDesactivarUsuario |
| TiendaController | getIndex, getCatalogo, getProducto, getCheckout, getConfirmacion, getSeguimiento, getLogin, getLogout, getRegistro | postPedido, postLogin, postRegistro |
| AuthController | getSignin | postSignin, getSignout |
| TestController | getIndex | — |

---

## Data layer

### Repository pattern

Every module has a repository that owns all SQL/ORM logic. Controllers never touch the DB directly.

```
Controller
    │
    ├─ calls repo.listar()     → returns array {datos, total, pagina, total_pages}
    ├─ calls repo.obtener(id)  → returns Model instance
    └─ calls repo.registrar()  → returns ResponseHelper
```

**Repository inventory (13 classes):**

| Repository | Key responsibilities |
|---|---|
| VentaRepository | POS sales, stock decrement, week stats |
| DevolucionesRepository | Returns, stock restore, partial return tracking |
| ProductoRepository | CRUD, stock alerts, barcode search |
| PromocionRepository | Active promotions, price calculation |
| PedidoRepository | Online orders, status transitions |
| CorteCajaRepository | Register open/close, reconciliation |
| ReporteRepository | Aggregated queries (revenue, profit, inventory, employees, returns) |
| ClienteRepository | Customer CRUD, purchase history |
| EmpleadoRepository | Employee CRUD, attendance |
| ProveedorRepository | Supplier CRUD, purchase orders |
| CategoriaRepository | Category CRUD |
| UsuarioRepository | User management |
| RolRepository | Role listing |

**Transaction pattern (all write operations):**

```php
Capsule::transaction(function () use ($data, &$rh) {
    // 1. Create main record
    $model->save();
    // 2. Create related records
    foreach ($items as $item) { ... }
    // 3. Side effects (stock, etc.)
    Producto::where('id', $id)->decrement('stock', $cantidad);
    // 4. Signal success
    $rh->setResponse(true, 'OK');
    $rh->result = ['id' => $model->id];
});
// Any exception → full rollback, $rh stays false
```

---

### Models (19 classes)

```
Rol ─────────────── Usuario ─────────────────── Empleado
                       │                            │
                       │                         Asistencia
                       │
                    Venta ──────────────────── VentaDetalle
                       │                            │
                    Cliente                      Producto ──── Categoria
                       │                            │              │
                    Pedido ─── PedidoDetalle      Proveedor ── OrdenCompra
                                                    │               │
                                               Promocion    OrdenCompraDetalle

                 Devolucion ──── DevolucionDetalle
                     │
                   Venta (FK)

                 Configuracion  (key-value, no relations)
                 CorteCaja      (belongs to Usuario, has many Ventas via corte_id)
```

**Common patterns across all models:**

| Pattern | Models that use it |
|---|---|
| `scopeActivos` | Producto, Usuario, Cliente, Empleado, Proveedor |
| `scopeStockBajo` | Producto |
| `scopeVigentes` | Promocion |
| `$timestamps = false` | VentaDetalle, DevolucionDetalle, PedidoDetalle, OrdenCompraDetalle |
| `getNombreCompletoAttribute` | Usuario, Cliente |
| Business logic method | Promocion (`calcularPrecio`) |
| Static get/set | Configuracion |

---

## View layer

### Layout hierarchy

```
new_layout.twig          ← all internal (admin) pages
  └── {% block content %}
  └── {% block scripts %}   ← loaded AFTER global JS vars

tienda_layout.twig       ← all public storefront pages
  └── {% block content %}
  └── {% block scripts %}

errors/404.php           ← plain PHP, no Twig
errors/500.php
```

### Script load order in `new_layout.twig`

```html
<head>
  <!-- CSS only -->
</head>
<body>
  <!-- content block renders here -->

  <!-- vendor JS -->
  jquery · bootstrap · riot · jquery.form · moment · easy-autocomplete

  <!-- global vars (defined BEFORE block scripts) -->
  <script>
    const _BASE_URL_ = '...';
    const _SIMBOLO_  = '...';
    function base_url(url) { ... }
    function redirect(url)  { ... }
    $.ajaxSetup({ headers: { 'X-CSRF-Token': ... } });
  </script>

  <!-- page-specific scripts (can safely use base_url, _SIMBOLO_) -->
  {% block scripts %}{% endblock %}
</body>
```

### View directory map

```
Views/
├── partials/
│   ├── new_layout.twig       base layout (admin)
│   ├── new_menu.twig         sidebar navigation (role-aware)
│   └── tienda_layout.twig    base layout (storefront)
├── home/         index.twig
├── ventas/       caja.twig · historial.twig · ticket.twig · corte.twig · corte_detalle.twig · cortes.twig
├── devoluciones/ index.twig · historial.twig · recibo.twig
├── inventario/   index.twig · formulario.twig
├── proveedores/  index.twig · formulario.twig · ordenes.twig · nueva_orden.twig · detalle_orden.twig
├── clientes/     index.twig · formulario.twig · perfil.twig
├── empleados/    index.twig · formulario.twig
├── pedidos/      index.twig · detalle.twig · orden.twig
├── promociones/  index.twig · formulario.twig
├── reportes/     index.twig · utilidades.twig · pdf_ventas.twig · pdf_inventario.twig · pdf_empleados.twig
├── configuracion/index.twig
├── tienda/       index.twig · catalogo.twig · producto.twig · checkout.twig · confirmacion.twig · login.twig · registro.twig · seguimiento.twig
├── auth/         login.twig
└── errors/       404.php · 500.php
```

---

## Security model

```
Layer               Mechanism
─────────────────── ──────────────────────────────────────────────
Network             Apache HTTPS (prod), HTTP-only cookie flag
Session             AES-256-CBC encrypted cookie, IP+UA bound key
CSRF                Per-session token, hash_equals comparison
Authorization       Role ID check on every protected route
Input               Respect/Validation on form submissions
SQL injection       Eloquent ORM + Capsule query builder (no raw user input in queries)
XSS                 Twig auto-escaping on all {{ }} output
Error leakage       prod mode disables error_reporting; JSON errors omit stack traces
```

### Auth decision tree for every request

```
Incoming request
    │
    ├─ Is route in 'auth' group?
    │       │
    │       ├─ NO  → is it in 'csrf' group?
    │       │              │
    │       │              ├─ NO  → execute freely
    │       │              └─ YES → validate CSRF (POST only)
    │       │
    │       └─ YES
    │              │
    │              ├─ Auth::isLoggedIn() false?  → redirect /auth
    │              │
    │              ├─ Is POST?  → Csrf::validateOrFail()
    │              │
    │              └─ Execute controller
    │                      │
    │                      └─ Route has 'isAdmin'/'isSeller'/'isAnalyst' filter?
    │                               → check rol_id, redirect home if unauthorized
    │
    └─ Error thrown?  → ExceptionHandler::handle()
                              │
                              ├─ AJAX → JSON {"response":false, "message":"..."}
                              └─ HTML → errors/404.php or errors/500.php
```

---

## Module map

How the 15 modules map to the two user journeys:

```
INTERNAL SYSTEM (requires login)
──────────────────────────────────────────────────────────────
/home           Dashboard: metrics, charts, stock alerts
/ventas         POS: barcode scan, cart, promotions, payment
/corte-caja     Cash register: open, close, reconciliation
/devoluciones   Returns: partial/full, stock restore, receipt
/inventario     Products: CRUD, images, weight-based config
/proveedores    Suppliers: profiles + purchase orders
/clientes       Customer: CRM, purchase history
/promociones    Discount engine: %, fixed, 2x1, min-qty
/pedidos        Online order management + delivery assign
/empleados      HR: profiles, shifts, attendance tracking
/reportes       Analytics: revenue, profit, inventory, returns (PDF + CSV)
/configuracion  Business settings, users, currency, logo

PUBLIC STOREFRONT (no login required)
──────────────────────────────────────────────────────────────
/tienda              Landing page
/tienda/catalogo     Product grid with filters + search
/tienda/producto/id  Product detail + weight picker
/tienda/checkout     Cart review + address
/tienda/confirmacion Order confirmation + tracking code
/tienda/seguimiento  Order status lookup
/tienda/login        Customer login
/tienda/registro     Customer registration

AUTH
──────────────────────────────────────────────────────────────
/auth/signin    Staff login form
/auth/signout   Destroy session cookie
```

---

## Model relationship graph

```
roles
 └─ usuarios (rol_id)
      ├─ empleados (usuario_id)
      │    └─ asistencias (empleado_id)
      ├─ ventas (usuario_id)
      │    ├─ venta_detalles (venta_id)
      │    │    └─ productos (producto_id)
      │    └─ clientes (cliente_id) [optional]
      ├─ devoluciones (usuario_id)
      │    ├─ ventas (venta_id)
      │    └─ devolucion_detalles (devolucion_id)
      │         ├─ venta_detalles (venta_detalle_id)
      │         └─ productos (producto_id)
      ├─ ordenes_compra (usuario_id)
      │    ├─ proveedores (proveedor_id)
      │    └─ orden_compra_detalles (orden_id)
      │         └─ productos (producto_id)
      └─ cortes_caja (usuario_id)
           └─ ventas (corte_id) [optional]

clientes
 ├─ ventas (cliente_id)
 └─ pedidos (cliente_id)
      └─ pedido_detalles (pedido_id)
           └─ productos (producto_id)

productos (categoria_id, proveedor_id)
 ├─ categorias
 ├─ proveedores
 └─ promociones (producto_id)

configuracion  (standalone key-value, no FK relations)
```