# CLAUDE.md — SuZuMa Supermarket Web System

## Project overview

PHP 8 MVC web application for supermarket management. Combines an internal POS system with an e-commerce storefront. Single-developer project running on AMPPS (Apache + MySQL).

**Author:** Noe Cazarez Camargo
**Timezone:** America/Hermosillo (UTC-7, no DST)
**Currency:** MXN (configurable to USD via `configuracion` table)

## 1. Reglas de Oro (Core Rules)
1. **Consulta de Skills**: Antes de actuar, verifica la carpeta `.claude/skills/`.
2. **Idioma**: Interacciones, comentarios y commits estrictamente en **Español**.
3. **Flujo de Trabajo (Pipeline)**:
    - **Planificar**: Crear `task_plan.md` (Skill: planning-with-files).
    - **Auditar**: Ejecutar A11Y, Web Vitals y Contraste de Color.
    - **Testear**: Aplicar reglas de PHPUnit antes de confirmar.
    - **Persistir**: Git con Conventional Commits (Skill: git-manager).

## 2. Estándares de Programación (PHP 8.1+)

### Calidad y Tipado (Type Safety)
- **Strict Types**: Todos los archivos inician con `declare(strict_types=1);`.
- **Tipado Fuerte**: Obligatorio en argumentos, propiedades de clase y retornos.
- **Naming**: PSR-12 (PascalCase para clases, camelCase para métodos/variables).
- **Constructores**: Usar propiedades promovidas de PHP 8 siempre que sea posible.

### Política de Comentarios (Clean Code)
- **Auto-documentación**: Nombres de variables descriptivos que eliminen la necesidad de comentarios.
- **Prohibiciones**: No repetir lo que hace el código. Prohibido dejar código comentado (borrarlo, está en Git).
- **El "Por qué"**: Los comentarios solo explican decisiones de negocio o limitaciones técnicas.
---

## Skills Activos (Toolbelt)
- `git-workflow-manager`: Historial profesional y auditoría de seguridad.
- `aria-web-helper`: Semántica y roles para lectores de pantalla.
- `keyboard-nav-master`: Operabilidad total del POS sin mouse.
- `color-contrast-checker`: Legibilidad bajo estándares WCAG 2.1.
- `lazy-loading-master`: Estrategias de carga diferida en Twig/jQuery.
- `web-vitals-monitor`: Monitoreo de estabilidad visual y velocidad.

## Running the project

Served via AMPPS at `http://localhost/desweb/`. No CLI commands — this is a web-only PHP application.

- **Web server:** Apache with `mod_rewrite` (`.htaccess` rewrites everything to `index.php`)
- **Database:** MySQL, database name `supermercado`, charset `utf8mb4`
- **PHP entry point:** `index.php` bootstraps everything

### Database setup
```bash
mysql -u root supermercado < DATA_BASE.sql
mysql -u root supermercado < migration_corte_caja.sql
mysql -u root supermercado < migration_gramajes.sql
mysql -u root supermercado < migration_devoluciones.sql
```

### Environment
Set in `.env`:
```
APP_ENV=dev           # dev | prod | stop
APP_SECRET=           # encryption key for sessions
DB_HOST=127.0.0.1
DB_DATABASE=supermercado
DB_USERNAME=root
DB_PASSWORD=
SESSION_NAME=application-auth
SESSION_TIME=10
```

---

## Architecture

**Stack:** PHP 8 · Phroute router · Twig 3 · Eloquent ORM (illuminate/database 9) · Monolog 2 · Respect/Validation 2 · DomPDF 2 · Intervention/Image 2.7 · Chart.js 4.4 · Bootstrap 4 · jQuery

**Pattern:** MVC with Repository layer
```
Request → index.php → Phroute router → Filter (auth/csrf) → Controller → Repository → Model → DB
                                                                         ↓
                                                               Twig view rendered
```

**Directory layout:**
```
app/
  Controllers/   — one file per module
  Models/        — Eloquent models
  Repositories/  — all DB queries (never query DB in controllers)
  Validations/   — static validation classes using Respect/Validation
  Views/         — Twig templates, organized by module
    partials/    — new_layout.twig, new_menu.twig, tienda_layout.twig
    errors/      — 404.php, 500.php
  Helpers/       — ResponseHelper, UrlHelper
  Middlewares/   — AuthMiddleware, RoleMiddleware
  filters.php    — route filter definitions
  routes.php     — all route definitions
core/            — framework kernel (Controller, Auth, Csrf, Log, DbContext, ExceptionHandler)
public/          — CSS, JS, images (web-accessible)
  images/productos/ — uploaded product images
log/             — Monolog log files (daily: {level}-YYYYMMDD.log)
```

---

## Adding a new module — full recipe

**1.** SQL migration file (`migration_<module>.sql`)

**2.** Model (`app/Models/MyModel.php`)
```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class MyModel extends Model {
    protected $table = 'my_table';
    protected $fillable = ['col1', 'col2'];
    public function relation() { return $this->belongsTo(OtherModel::class); }
    public function scopeActivos($query) { return $query->where('activo', 1); }
}
```

**3.** Repository (`app/Repositories/MyRepository.php`)
```php
<?php
namespace App\Repositories;
use App\Helpers\ResponseHelper;
use App\Models\MyModel;
use Core\{Auth, Log};
use Exception;
use Illuminate\Database\Capsule\Manager as Capsule;

class MyRepository {
    private $model;
    public function __construct() { $this->model = new MyModel(); }

    // READ → return array
    public function listar(): array {
        try {
            $datos = $this->model->with('relation')->get();
            return ['datos' => $datos, 'total' => $datos->count()];
        } catch (Exception $e) {
            Log::error(MyRepository::class, $e->getMessage());
            return ['datos' => collect(), 'total' => 0];
        }
    }

    // WRITE → return ResponseHelper, always wrap in transaction
    public function registrar(array $data): ResponseHelper {
        $rh = new ResponseHelper();
        try {
            Capsule::transaction(function () use ($data, &$rh) {
                $m = new MyModel();
                $m->col1 = $data['col1'];
                $m->save();
                $rh->setResponse(true, 'Guardado correctamente');
                $rh->result = ['id' => $m->id];
            });
        } catch (Exception $e) {
            Log::error(MyRepository::class, $e->getMessage());
            $rh->setResponse(false, 'No se pudo guardar');
        }
        return $rh;
    }
}
```

**4.** Controller (`app/Controllers/MyController.php`)
```php
<?php
namespace App\Controllers;
use App\Helpers\ResponseHelper;
use App\Repositories\MyRepository;
use Core\Controller;

class MyController extends Controller {
    private $repo;
    public function __construct() {
        parent::__construct();           // ALWAYS first — sets up Twig
        $this->repo = new MyRepository();
    }

    // GET → return $this->render(...)
    public function getIndex() {
        $data = $this->repo->listar();
        return $this->render('mymodule/index.twig', array_merge($data, ['title' => 'Mi módulo']));
    }

    // POST → echo json_encode($rh), NO return statement
    public function postGuardar() {
        $rh = new ResponseHelper();
        $col1 = trim($_POST['col1'] ?? '');
        if (empty($col1)) {
            echo json_encode($rh->setResponse(false, 'El campo es requerido'));
            return;
        }
        echo json_encode($this->repo->registrar(['col1' => $col1]));
    }
}
```

**5.** Routes — add inside the `auth` group in `app/routes.php`:
```php
$router->get('/mymodule',          ['App\\Controllers\\MyController', 'getIndex']);
$router->get('/mymodule/{id}',     ['App\\Controllers\\MyController', 'getDetalle']);  // $id passed as arg
$router->post('/mymodule/guardar', ['App\\Controllers\\MyController', 'postGuardar']);
```

**6.** View (`app/Views/mymodule/index.twig`)
```twig
{% extends "partials/new_layout.twig" %}
{% block content %}
<div class="sz-dashboard">
    <div class="sz-dash-header">
        <h1 class="sz-dash-title">Mi módulo</h1>
    </div>
</div>
{% endblock %}
{% block scripts %}<script>...</script>{% endblock %}
```

**7.** Add link to `app/Views/partials/new_menu.twig` inside the appropriate role block.

---

## Routing

```php
// All admin routes go inside the auth group
$router->group(['before' => 'auth'], function($router) {
    $router->get('/path',         ['App\\Controllers\\Ctrl', 'getMethod']);
    $router->post('/path/action', ['App\\Controllers\\Ctrl', 'postMethod']);
});

// Public routes (storefront) use csrf filter
$router->group(['before' => 'csrf'], function($router) { ... });
```

**Route filters:**
| Filter | What it does |
|--------|-------------|
| `auth` | Login required + CSRF validation on POST |
| `csrf` | CSRF validation on POST only (no login required) |
| `isAdmin` | `rol_id === 1` required, redirects to home otherwise |
| `isSeller` | `rol_id` 1 or 2 required |
| `isAnalyst` | `rol_id` 1 or 3 required |

---

## Twig reference

### Custom filters
| Filter | Example | Returns |
|--------|---------|---------|
| `url` | `'ventas/historial' | url` | `_BASE_HTTP_ . route` |
| `public` | `'images/logo.png' | public` | URL to `/public/` asset |
| `padLeft(n)` | `venta.id | padLeft(5)` | Zero-padded string |

### Custom functions
| Function | Returns |
|----------|---------|
| `user()` | stdClass with `id`, `rol_id`, `nombre`, `apellido`, `email` |
| `isAdmin()` | `true` if `rol_id === 1` |
| `isSeller()` | `true` if `rol_id` is 1 or 2 |
| `isAnalyst()` | `true` if `rol_id` is 1 or 3 |
| `csrf_token()` | 64-char hex token from `$_SESSION['_csrf_token']` |

### Auto-injected `_cfg` array (available in every view)
```twig
{{ _cfg.simbolo }}          {# $ or USD $ #}
{{ _cfg.moneda }}           {# MXN or USD #}
{{ _cfg.negocio_nombre }}
{{ _cfg.negocio_tel }}
{{ _cfg.ticket_mensaje }}
{{ _cfg.negocio_logo }}
```

### `_route` variable
Always available — the first segment of the current URI. Used for active nav states:
```twig
class="sz-nav-item {% if _route == 'ventas' %}sz-nav-item--active{% endif %}"
```

### JS helper (defined in layout)
```javascript
base_url('ventas/historial')  // Returns full URL string
```

### ⚠️ Twig filter limitations
`|max` and `|min` **do not exist** in Twig 3. Use a loop:
```twig
{# WRONG: #}  {{ values|max }}
{# CORRECT: #}
{% set maxVal = values[0] %}
{% for v in values %}{% if v > maxVal %}{% set maxVal = v %}{% endif %}{% endfor %}
```

---

## CSRF

CSRF is validated automatically by the `auth` and `csrf` route filters — **no manual checking needed in POST handlers**.

For HTML forms:
```twig
<input type="hidden" name="_csrf_token" value="{{ csrf_token() }}">
```

For AJAX, send token in `_csrf_token` POST field or `X-CSRF-TOKEN` header.

On invalid token: responds with JSON `{"response":false,"message":"Solicitud inválida..."}` and HTTP 419.

---

## Authentication

```php
Core\Auth::getCurrentUser()     // stdClass: id, rol_id, nombre, apellido, email
Core\Auth::isLoggedIn()         // bool
Core\Auth::signIn(array $data)  // Sets encrypted cookie
Core\Auth::destroy()            // Clears cookie
```

Session stored in AES-256-CBC encrypted cookie. Key derived from `APP_SECRET + client_ip + user_agent`.

**Client/storefront session** is separate — uses `$_SESSION['cliente_id']`, `$_SESSION['cliente_nombre']`.

---

## ResponseHelper

Every POST endpoint returns this class as JSON:

```php
$rh = new ResponseHelper();
// Success
$rh->setResponse(true, 'Operación exitosa');
$rh->result = ['id' => 123];    // any additional data
$rh->href   = 'ventas';         // optional redirect URL (JS can use this)
echo json_encode($rh);

// Error
echo json_encode($rh->setResponse(false, 'Descripción del error'));

// Validation errors
echo json_encode($rh->setErrors(['campo' => 'mensaje de error']));
```

JSON shape: `{result, response, message, href, function, filter, validations}`

JavaScript: check `res.response` (bool), read `res.message`, use `res.result`.

---

## Stock management

```php
// Always inside Capsule::transaction()
Producto::where('id', $id)->decrement('stock', $cantidad);  // On sale
Producto::where('id', $id)->increment('stock', $cantidad);  // On cancellation / return
```

`stock` is `DECIMAL` not INT — products can be sold by weight (fractional quantities).

---

## Database — key reference

### Role IDs
| ID | Name |
|----|------|
| 1 | Admin |
| 2 | Cajero/Vendedor |
| 3 | Almacén/Analista |
| 4 | Repartidor |
| 5 | Cliente (e-commerce only) |

### Enum values
| Table.Column | Values |
|---|---|
| `ventas.tipo` | `efectivo`, `tarjeta`, `transferencia` |
| `ventas.estado` | `completada`, `cancelada`, `pendiente` |
| `pedidos.estado` | `pendiente`, `confirmado`, `enviado`, `entregado`, `cancelado` |
| `ordenes_compra.estado` | `pendiente`, `recibida`, `cancelada` |
| `empleados.turno` | `matutino`, `vespertino`, `nocturno` |
| `promociones.tipo` | `porcentaje`, `precio_fijo`, `2x1`, `cantidad_minima` |
| `productos.unidad_peso` | `g`, `kg`, `lb` |

### Tables added via migrations (not in DATA_BASE.sql)
- `cortes_caja` — cash register open/close records
- `devoluciones` — return transactions
- `devolucion_detalles` — return line items
- `venta_detalles.cantidad_devuelta DECIMAL(10,3)` — tracks returned quantity per line
- `productos.venta_por_peso TINYINT(1)` — marks weight-based products
- `productos.unidad_peso VARCHAR(10)` — weight unit
- `ventas.corte_id INT` — links sale to a cash register cut
- `venta_detalles.cantidad` is `DECIMAL(10,3)` (supports fractions)

### Notable column rules
- `venta_detalles` — no timestamps (`$timestamps = false`)
- `devolucion_detalles` — no timestamps
- `configuracion` — key-value store. Read: `Configuracion::get('clave', 'default')`. Write: `Configuracion::set('clave', 'valor')`
- `productos.imagen` — filename only, no path. Build URL with `|public` filter.

---

## Eloquent model scopes

| Model | Scope | SQL equivalent |
|-------|-------|---------------|
| Producto | `scopeActivos` | `WHERE activo = 1` |
| Producto | `scopeStockBajo` | `WHERE stock <= stock_minimo` |
| Usuario | `scopeActivos` | `WHERE activo = 1` |
| Cliente | `scopeActivos` | `WHERE activo = 1` |
| Empleado | `scopeActivos` | `WHERE activo = 1` |
| Proveedor | `scopeActivos` | `WHERE activo = 1` |
| Promocion | `scopeVigentes` | `WHERE activo=1 AND fecha_inicio<=TODAY AND fecha_fin>=TODAY` |

---

## Logging

```php
Log::error(ClassName::class, $e->getMessage());
Log::info(ClassName::class, 'mensaje');
Log::warning(ClassName::class, 'mensaje');
Log::critical(ClassName::class, 'mensaje');
Log::debug(ClassName::class, 'mensaje');
```

Files: `/log/{level}-YYYYMMDD.log`

---

## Constants

```php
_BASE_HTTP_   // 'http://localhost/desweb/'
_BASE_PATH_   // '/Applications/AMPPS/www/desweb/'
_APP_PATH_    // '/Applications/AMPPS/www/desweb/app/'
_LOG_PATH_    // '/Applications/AMPPS/www/desweb/log/'
_CACHE_PATH_  // '/Applications/AMPPS/www/desweb/cache/'
_CURRENT_URI_ // current request path (e.g. '/ventas/historial')
```

---

## File uploads

- Storage: `public/images/productos/`
- Naming: `producto_[md5_hash].png`
- Library: `Intervention\Image` (resize + encode)
- DB field: `productos.imagen` (filename only)
- Template: `{{ ('images/productos/' ~ producto.imagen)|public }}`

---

## Current modules

| Route prefix | Controller | Access |
|---|---|---|
| `/home` | HomeController | All roles |
| `/ventas` | VentasController | Admin, Cajero |
| `/corte-caja` | CorteCajaController | Admin, Cajero |
| `/devoluciones` | DevolucionesController | Admin, Cajero |
| `/inventario` | InventarioController | Admin, Analista |
| `/proveedores` | ProveedoresController | Admin, Analista |
| `/clientes` | ClientesController | Admin, Analista |
| `/promociones` | PromocionesController | Admin, Cajero |
| `/pedidos` | PedidosController | Admin, Analista |
| `/empleados` | EmpleadoController | Admin |
| `/reportes` | ReportesController | Admin, Analista |
| `/configuracion` | ConfiguracionController | Admin |
| `/tienda` | TiendaController | Public |
| `/auth` | AuthController | Public |