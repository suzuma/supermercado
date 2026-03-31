<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\{Categoria, Producto, Pedido, PedidoDetalle, Cliente, CodigoPostal};
use App\Repositories\{ProductoRepository, CategoriaRepository, PedidoRepository, PromocionRepository, CuponRepository, ResenaRepository, WishlistRepository, ClienteRepository, PuntosRepository};
use App\Helpers\{ResponseHelper, MailHelper};
use App\Models\Configuracion;
use Core\{Controller, Log};
use Illuminate\Database\Capsule\Manager as Capsule;

class TiendaController extends Controller
{
    private ProductoRepository $productoRepo;
    private CategoriaRepository $categoriaRepo;
    private PromocionRepository $promoRepo;

    public function __construct()
    {
        parent::__construct();
        $this->productoRepo  = new ProductoRepository();
        $this->categoriaRepo = new CategoriaRepository();
        $this->promoRepo     = new PromocionRepository();
    }

    // ── Helper: adjuntar promoción vigente a colección ────────
    private function adjuntarPromociones($productos): void
    {
        foreach ($productos as $producto) {
            $promo = $this->promoRepo->obtenerDeProducto($producto->id);
            if ($promo) {
                $calculo = $promo->calcularPrecio((float)$producto->precio_venta, 1);
                $producto->promo = [
                    'id'           => $promo->id,
                    'nombre'       => $promo->nombre,
                    'tipo'         => $promo->tipo,
                    'valor'        => $promo->valor,
                    'cantidad_min' => $promo->cantidad_min,
                    'precio_final' => $calculo['precio_final'],
                    'descripcion'  => $calculo['descripcion'],
                ];
            } else {
                $producto->promo = null;
            }
        }
    }

    // ── Página principal ──────────────────────────────────────
    public function getIndex()
    {
        $destacados = Producto::with('categoria')
            ->where('activo', 1)
            ->where('stock', '>', 0)
            ->orderByDesc('created_at')
            ->take(8)
            ->get();

        $this->adjuntarPromociones($destacados);

        $categorias = Categoria::withCount(['productos' => function ($q) {
            $q->where('activo', 1)->where('stock', '>', 0);
        }])->having('productos_count', '>', 0)->get();

        return $this->render('tienda/index.twig', [
            'title'        => 'Tienda en línea',
            'menu'         => false,
            'destacados'   => $destacados,
            'categorias'   => $categorias,
            'wishlist_ids' => $this->wishlistIds(),
        ]);
    }

    // ── Catálogo ──────────────────────────────────────────────
    public function getCatalogo()
    {
        $categoriaId = $_GET['categoria'] ?? null;
        $busqueda    = $_GET['busqueda'] ?? null;
        $pagina      = (int)($_GET['pagina'] ?? 1);
        $orden       = $_GET['orden'] ?? 'recientes';

        $query = Producto::with('categoria')
            ->where('activo', 1)
            ->where('stock', '>', 0);

        if ($categoriaId) {
            $query->where('categoria_id', $categoriaId);
        }

        if ($busqueda) {
            $query->where(function ($q) use ($busqueda) {
                $q->where('nombre',       'like', "%$busqueda%")
                    ->orWhere('descripcion','like', "%$busqueda%");
            });
        }

        match($orden) {
            'precio_asc'  => $query->orderBy('precio_venta'),
            'precio_desc' => $query->orderByDesc('precio_venta'),
            'nombre'      => $query->orderBy('nombre'),
            default       => $query->orderByDesc('created_at'),
        };

        $limite    = 12;
        $total     = $query->count();
        $offset    = ($pagina - 1) * $limite;
        $productos = $query->skip($offset)->take($limite)->get();

        $this->adjuntarPromociones($productos);

        $categorias = Categoria::withCount(['productos' => function ($q) {
            $q->where('activo', 1)->where('stock', '>', 0);
        }])->having('productos_count', '>', 0)->get();

        $categoriaActual = $categoriaId ? Categoria::find($categoriaId) : null;

        return $this->render('tienda/catalogo.twig', [
            'title'            => 'Catálogo',
            'menu'             => false,
            'productos'        => $productos,
            'categorias'       => $categorias,
            'categoria_actual' => $categoriaActual,
            'busqueda'         => $busqueda,
            'orden'            => $orden,
            'pagina'           => $pagina,
            'total'            => $total,
            'total_pages'      => (int)ceil($total / $limite),
            'wishlist_ids'     => $this->wishlistIds(),
        ]);
    }

    // ── Detalle del producto ──────────────────────────────────
    public function getProducto(int $id)
    {
        $producto = Producto::with('categoria')->findOrFail($id);

        // Adjuntar promo al producto principal
        $promo = $this->promoRepo->obtenerDeProducto($producto->id);
        if ($promo) {
            $calculo = $promo->calcularPrecio((float)$producto->precio_venta, 1);
            $producto->promo = [
                'id'           => $promo->id,
                'nombre'       => $promo->nombre,
                'tipo'         => $promo->tipo,
                'valor'        => $promo->valor,
                'cantidad_min' => $promo->cantidad_min,
                'precio_final' => $calculo['precio_final'],
                'descripcion'  => $calculo['descripcion'],
            ];
        } else {
            $producto->promo = null;
        }

        $relacionados = Producto::with('categoria')
            ->where('categoria_id', $producto->categoria_id)
            ->where('id', '!=', $id)
            ->where('activo', 1)
            ->where('stock', '>', 0)
            ->take(4)
            ->get();

        $this->adjuntarPromociones($relacionados);

        $wishlistIds  = $this->wishlistIds();
        $resenaRepo   = new ResenaRepository();
        $resenas      = $resenaRepo->listarDeProducto($producto->id);
        $clienteId    = $this->clienteLoggedIn() ? (int)$_SESSION['cliente_id'] : null;
        $puedeResenar = $clienteId
            && !$resenaRepo->yaReseno($clienteId, $producto->id)
            && $resenaRepo->clienteComproProducto($clienteId, $producto->id);

        return $this->render('tienda/producto.twig', [
            'title'          => $producto->nombre,
            'menu'           => false,
            'producto'       => $producto,
            'relacionados'   => $relacionados,
            'resenas'        => $resenas['resenas'],
            'promedio'       => $resenas['promedio'],
            'total_resenas'  => $resenas['total'],
            'puede_resenar'  => $puedeResenar,
            'ya_reseno'      => $clienteId && $resenaRepo->yaReseno($clienteId, $producto->id),
            'wishlist_ids'   => $wishlistIds,
        ]);
    }

    // ── Checkout ──────────────────────────────────────────────
    public function getCheckout()
    {
        if (!$this->clienteLoggedIn()) {
            header('Location: ' . _BASE_HTTP_ . 'tienda/login?redirect=checkout');
            exit;
        }

        $cliente     = $this->getClienteActual();
        $puntosRepo  = new PuntosRepository();
        $cfgPuntos   = $puntosRepo->config();
        $saldoPuntos = $puntosRepo->saldo($cliente->id);

        return $this->render('tienda/checkout.twig', [
            'title'        => 'Finalizar compra',
            'menu'         => false,
            'cliente'      => $cliente,
            'saldo_puntos' => $saldoPuntos,
            'cfg_puntos'   => $cfgPuntos,
        ]);
    }

    // ── Procesar pedido con promociones (Ajax) ────────────────
    public function postPedido()
    {
        $rh = new ResponseHelper();

        try {
            if (!$this->clienteLoggedIn()) {
                echo json_encode($rh->setResponse(false, 'Debes iniciar sesión para realizar un pedido'));
                return;
            }

            $items         = json_decode($_POST['items'] ?? '[]', true);
            $direccion     = trim($_POST['direccion'] ?? '');
            $cuponCodigo   = trim($_POST['cupon_codigo'] ?? '');
            $puntosACanjear = max(0, (int)($_POST['puntos_canjear'] ?? 0));

            if (empty($items) || !is_array($items)) {
                echo json_encode($rh->setResponse(false, 'El carrito está vacío'));
                return;
            }

            foreach ($items as $item) {
                if (empty($item['id'])      || (int)$item['id'] <= 0
                    || empty($item['cantidad']) || (float)$item['cantidad'] <= 0
                    || !isset($item['precio'])  || (float)$item['precio'] < 0) {
                    echo json_encode($rh->setResponse(false, 'Los datos del carrito son inválidos'));
                    return;
                }
            }

            if (empty($direccion)) {
                echo json_encode($rh->setResponse(false, 'La dirección de entrega es requerida'));
                return;
            }

            // Cargar precios reales desde BD en una sola query y validar antes de procesar.
            // Nunca se confía en el precio enviado por el cliente — solo se usa como referencia
            // para detectar si el carrito está desactualizado.
            $ids        = array_map(fn($i) => (int)$i['id'], $items);
            $productosDb = $this->productoRepo->obtenerMultiples($ids)->keyBy('id');

            $total = 0;
            foreach ($items as &$item) {
                $producto = $productosDb->get((int)$item['id']);

                if (!$producto || !$producto->activo) {
                    echo json_encode($rh->setResponse(false,
                        'Un producto de tu carrito ya no está disponible. Actualiza tu carrito e intenta de nuevo'
                    ));
                    return;
                }

                if ($producto->stock <= 0) {
                    echo json_encode($rh->setResponse(false,
                        "\"{$producto->nombre}\" está agotado. Retíralo del carrito para continuar"
                    ));
                    return;
                }

                $precioDb = (float)$producto->precio_venta;
                $promo    = $this->promoRepo->obtenerDeProducto((int)$item['id']);

                // Calcular precio final real (con promo si aplica) para comparar con el carrito
                $precioFinalDb = $promo
                    ? (float)$promo->calcularPrecio($precioDb, (float)$item['cantidad'])['precio_final']
                    : $precioDb;

                if (abs($precioFinalDb - (float)$item['precio']) > 0.01) {
                    $simbolo = \App\Models\Configuracion::get('moneda', 'MXN') === 'USD' ? 'USD $' : '$';
                    echo json_encode($rh->setResponse(false,
                        "El precio de \"{$producto->nombre}\" cambió a {$simbolo}" . number_format($precioFinalDb, 2) . '. Recarga la página para ver los precios actualizados'
                    ));
                    return;
                }

                // A partir de aquí solo se usan precios de BD
                if ($promo) {
                    $calculo              = $promo->calcularPrecio($precioDb, (float)$item['cantidad']);
                    $item['subtotal']     = $calculo['subtotal'];
                    $item['precio_final'] = $calculo['precio_final'];
                    $item['promo_id']     = $promo->id;
                    $item['promo_desc']   = $calculo['descripcion'];
                } else {
                    $item['subtotal']     = round($precioDb * (float)$item['cantidad'], 2);
                    $item['precio_final'] = $precioDb;
                    $item['promo_id']     = null;
                    $item['promo_desc']   = '';
                }

                $total += $item['subtotal'];
            }
            unset($item);

            // Validar cupón si se proporcionó (fuera de transacción — solo lectura)
            $cuponData = null;
            if ($cuponCodigo !== '') {
                $cuponRepo = new CuponRepository();
                $cuponRh   = $cuponRepo->validar($cuponCodigo, $total);
                if (!$cuponRh->response) {
                    echo json_encode($cuponRh);
                    return;
                }
                $cuponData = $cuponRh->result;
            }

            // Validar canje de puntos si se solicitó (fuera de transacción — solo lectura)
            $puntosRepo     = new PuntosRepository();
            $cfgPuntos      = $puntosRepo->config();
            $descuentoPuntos = 0.0;

            if ($puntosACanjear > 0) {
                $clienteActual = $this->getClienteActual();
                $saldoPuntos   = $puntosRepo->saldo($clienteActual->id);

                if ($puntosACanjear > $saldoPuntos) {
                    echo json_encode($rh->setResponse(false, 'No tienes suficientes puntos'));
                    return;
                }
                if ($puntosACanjear < $cfgPuntos['minimo_puntos_canje']) {
                    echo json_encode($rh->setResponse(false, "El mínimo para canjear es {$cfgPuntos['minimo_puntos_canje']} puntos"));
                    return;
                }

                $descuentoPuntos = $puntosRepo->valorEnPesos($puntosACanjear);
            }

            $descuento  = ($cuponData ? (float)$cuponData['descuento'] : 0.0) + $descuentoPuntos;
            $totalFinal = max(0.0, round($total - $descuento, 2));
            $clienteId  = $this->getClienteActual()->id;

            Capsule::transaction(function () use ($items, $total, $totalFinal, $descuento, $descuentoPuntos, $puntosACanjear, $cuponData, $direccion, $clienteId, $puntosRepo, $rh) {
                // Bloquear filas y validar stock antes de cualquier escritura.
                // lockForUpdate() emite SELECT ... FOR UPDATE, impidiendo que otra
                // transacción concurrente lea el mismo stock hasta que ésta termine.
                foreach ($items as $item) {
                    $producto = Producto::lockForUpdate()->find((int)$item['id']);

                    if (!$producto || !$producto->activo) {
                        throw new \RuntimeException("El producto ya no está disponible");
                    }

                    if ($producto->stock < (float)$item['cantidad']) {
                        $disponible = $producto->venta_por_peso
                            ? number_format((float)$producto->stock, 3) . ' ' . $producto->unidad_peso
                            : (int)$producto->stock . ' unidades';
                        throw new \RuntimeException(
                            "Stock insuficiente para \"{$producto->nombre}\". Solo hay {$disponible} disponibles"
                        );
                    }
                }

                // Incrementar uso del cupón dentro de la transacción — previene doble uso concurrente
                if ($cuponData) {
                    $afectados = \App\Models\Cupon::where('id', $cuponData['cupon_id'])
                        ->where('activo', 1)
                        ->where(function ($q) use ($cuponData) {
                            $q->whereNull('usos_max')
                              ->orWhereRaw('usos_actual < usos_max');
                        })
                        ->increment('usos_actual');

                    if ($afectados === 0) {
                        throw new \RuntimeException('El cupón ya no está disponible. Intenta sin cupón');
                    }
                }

                $pedido                    = new Pedido();
                $pedido->cliente_id        = $clienteId;
                $pedido->total             = $totalFinal;
                $pedido->descuento         = $descuento;
                $pedido->cupon_id          = $cuponData ? $cuponData['cupon_id'] : null;
                $pedido->puntos_usados     = $puntosACanjear;
                $pedido->estado            = 'pendiente';
                $pedido->direccion_entrega = $direccion;
                $pedido->save();

                if ($puntosACanjear > 0) {
                    \App\Models\Cliente::where('id', $clienteId)->decrement('puntos', $puntosACanjear);

                    $tx             = new \App\Models\PuntosTransaccion();
                    $tx->cliente_id = $clienteId;
                    $tx->pedido_id  = $pedido->id;
                    $tx->tipo       = 'canjeado';
                    $tx->puntos     = -$puntosACanjear;
                    $tx->descripcion = "Canje en pedido #{$pedido->id} — descuento $" . number_format($descuentoPuntos, 2);
                    $tx->created_at = date('Y-m-d H:i:s');
                    $tx->save();
                }

                foreach ($items as $item) {
                    $detalle                  = new PedidoDetalle();
                    $detalle->pedido_id       = $pedido->id;
                    $detalle->producto_id     = $item['id'];
                    $detalle->cantidad        = $item['cantidad'];
                    $detalle->precio_unitario = $item['precio_final'];
                    $detalle->subtotal        = $item['subtotal'];
                    $detalle->save();

                    Producto::where('id', $item['id'])
                        ->decrement('stock', $item['cantidad']);
                }

                $rh->setResponse(true, 'Pedido realizado correctamente');
                $rh->result = ['pedido_id' => $pedido->id];
            });

            // Enviar email de confirmación al cliente (fuera de la transacción)
            if ($rh->response && !empty($rh->result['pedido_id'])) {
                \App\Services\CacheService::forget('pedidos_pendientes_count');
                $this->enviarEmailConfirmacion((int)$rh->result['pedido_id']);
            }
        } catch (\RuntimeException $e) {
            $rh->setResponse(false, $e->getMessage());
        } catch (\Exception $e) {
            Log::error(TiendaController::class, $e->getMessage());
            $rh->setResponse(false, 'No se pudo procesar el pedido');
        }

        echo json_encode($rh);
    }

    // ── Email: confirmación de pedido nuevo ───────────────────
    private function enviarEmailConfirmacion(int $pedidoId): void
    {
        try {
            $pedido  = Pedido::with(['detalles.producto', 'cliente'])->findOrFail($pedidoId);
            $cliente = $pedido->cliente;

            if (empty($cliente->email)) return;

            MailHelper::send(
                $cliente->email,
                $cliente->nombre . ' ' . $cliente->apellido,
                '¡Tu pedido #' . str_pad((string)$pedidoId, 4, '0', STR_PAD_LEFT) . ' fue recibido!',
                'pedido_confirmado',
                [
                    'pedido'   => $pedido,
                    'cliente'  => $cliente,
                    'simbolo'  => Configuracion::get('moneda', 'MXN') === 'USD' ? 'USD $' : '$',
                    'negocio'  => Configuracion::get('negocio_nombre', 'Supermercado Web'),
                    'base_url' => _BASE_HTTP_,
                ]
            );
        } catch (\Exception $e) {
            Log::error(TiendaController::class, 'Email confirmación: ' . $e->getMessage());
        }
    }

    // ── Confirmación ──────────────────────────────────────────
    public function getConfirmacion(int $id)
    {
        $pedido = Pedido::with(['detalles.producto'])->findOrFail($id);

        return $this->render('tienda/confirmacion.twig', [
            'title'  => 'Pedido confirmado',
            'menu'   => false,
            'pedido' => $pedido,
        ]);
    }

    // ── Seguimiento ───────────────────────────────────────────
    public function getSeguimiento()
    {
        if (!$this->clienteLoggedIn()) {
            header('Location: ' . _BASE_HTTP_ . 'tienda/login');
            exit;
        }

        $cliente = $this->getClienteActual();
        $pedidos = Pedido::with(['detalles.producto'])
            ->where('cliente_id', $cliente->id)
            ->orderByDesc('created_at')
            ->get();

        return $this->render('tienda/seguimiento.twig', [
            'title'   => 'Mis pedidos',
            'menu'    => false,
            'pedidos' => $pedidos,
            'cliente' => $cliente,
        ]);
    }

    // ── Login ─────────────────────────────────────────────────
    public function getLogin()
    {
        if ($this->clienteLoggedIn()) {
            header('Location: ' . _BASE_HTTP_ . 'tienda');
            exit;
        }

        return $this->render('tienda/login.twig', [
            'title'    => 'Iniciar sesión',
            'menu'     => false,
            'redirect' => $_GET['redirect'] ?? '',
        ]);
    }

    public function postLogin()
    {
        $rh = new ResponseHelper();

        try {
            $email    = trim($_POST['email']    ?? '');
            $password = $_POST['password']      ?? '';
            $redirect = $_POST['redirect']      ?? '';

            if (empty($email) || empty($password)) {
                echo json_encode($rh->setResponse(false, 'Correo y contraseña son requeridos'));
                return;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode($rh->setResponse(false, 'El correo no tiene un formato válido'));
                return;
            }

            // Whitelist de redirecciones permitidas para evitar open redirect
            $allowedRedirects = ['checkout', 'seguimiento', ''];
            if (!in_array($redirect, $allowedRedirects, true)) {
                $redirect = '';
            }

            $cliente = Cliente::where('email', $email)->where('activo', 1)->first();

            if (!$cliente || !$this->verificarPasswordCliente($password, $cliente->password)) {
                echo json_encode($rh->setResponse(false, 'Correo o contraseña incorrectos'));
                return;
            }

            // Migración transparente SHA1 → bcrypt
            if (!$this->esBcrypt($cliente->password)) {
                $cliente->password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $cliente->save();
            }

            $_SESSION['cliente_id']     = $cliente->id;
            $_SESSION['cliente_nombre'] = $cliente->nombre;
            $_SESSION['cliente_email']  = $cliente->email;

            $rh->setResponse(true, 'Bienvenido ' . $cliente->nombre);
            $rh->href = $redirect ? 'tienda/' . $redirect : 'tienda';
        } catch (\Exception $e) {
            Log::error(TiendaController::class, $e->getMessage());
            $rh->setResponse(false, 'Error al iniciar sesión');
        }

        echo json_encode($rh);
    }

    // ── Registro ──────────────────────────────────────────────
    public function getRegistro()
    {
        if ($this->clienteLoggedIn()) {
            header('Location: ' . _BASE_HTTP_ . 'tienda');
            exit;
        }

        return $this->render('tienda/registro.twig', [
            'title' => 'Crear cuenta',
            'menu'  => false,
        ]);
    }

    public function postRegistro()
    {
        $rh = new ResponseHelper();

        try {
            $nombre   = trim($_POST['nombre']   ?? '');
            $apellido = trim($_POST['apellido'] ?? '');
            $email    = trim($_POST['email']    ?? '');
            $password = $_POST['password']      ?? '';
            $telefono = trim($_POST['telefono'] ?? '');

            if (empty($nombre) || empty($apellido) || empty($email) || empty($password)) {
                echo json_encode($rh->setResponse(false, 'Todos los campos son requeridos'));
                return;
            }

            if (strlen($password) < 6) {
                echo json_encode($rh->setResponse(false, 'La contraseña debe tener mínimo 6 caracteres'));
                return;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode($rh->setResponse(false, 'El correo no tiene un formato válido'));
                return;
            }

            if (Cliente::where('email', $email)->exists()) {
                echo json_encode($rh->setResponse(false, 'Ya existe una cuenta con ese correo'));
                return;
            }

            $cliente           = new Cliente();
            $cliente->nombre   = $nombre;
            $cliente->apellido = $apellido;
            $cliente->email    = $email;
            $cliente->telefono = $telefono;
            $cliente->password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $cliente->activo   = 1;
            $cliente->save();

            $_SESSION['cliente_id']     = $cliente->id;
            $_SESSION['cliente_nombre'] = $cliente->nombre;
            $_SESSION['cliente_email']  = $cliente->email;

            $rh->setResponse(true, '¡Cuenta creada exitosamente!');
            $rh->href = 'tienda';
        } catch (\Exception $e) {
            Log::error(TiendaController::class, $e->getMessage());
            $rh->setResponse(false, 'No se pudo crear la cuenta');
        }

        echo json_encode($rh);
    }

    // ── Mi cuenta ─────────────────────────────────────────────
    public function getCuenta(): string
    {
        if (!$this->clienteLoggedIn()) {
            header('Location: ' . _BASE_HTTP_ . 'tienda/login');
            exit;
        }

        $clienteId      = (int)$_SESSION['cliente_id'];
        $cliente        = $this->getClienteActual();
        $clienteRepo    = new ClienteRepository();
        $puntosRepo     = new PuntosRepository();
        $pedidos        = $clienteRepo->pedidosCliente($clienteId);
        $estadisticas   = $clienteRepo->estadisticas($clienteId);
        $saldoPuntos    = $puntosRepo->saldo($clienteId);
        $historialPuntos = $puntosRepo->historial($clienteId);
        $cfgPuntos      = $puntosRepo->config();

        return $this->render('tienda/cuenta.twig', [
            'title'            => 'Mi cuenta',
            'menu'             => false,
            'cliente'          => $cliente,
            'pedidos'          => $pedidos,
            'estadisticas'     => $estadisticas,
            'saldo_puntos'     => $saldoPuntos,
            'historial_puntos' => $historialPuntos,
            'cfg_puntos'       => $cfgPuntos,
        ]);
    }

    public function postActualizarCuenta(): void
    {
        $rh = new ResponseHelper();

        if (!$this->clienteLoggedIn()) {
            echo json_encode($rh->setResponse(false, 'Sesión expirada'));
            return;
        }

        $nombre   = trim($_POST['nombre']   ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');

        if (empty($nombre) || empty($apellido)) {
            echo json_encode($rh->setResponse(false, 'Nombre y apellido son requeridos'));
            return;
        }

        try {
            $cliente           = $this->getClienteActual();
            $cliente->nombre   = $nombre;
            $cliente->apellido = $apellido;
            $cliente->telefono = $telefono;
            $cliente->direccion = $direccion;
            $cliente->save();

            $_SESSION['cliente_nombre'] = $nombre;

            $rh->setResponse(true, 'Datos actualizados correctamente');
        } catch (\Exception $e) {
            Log::error(TiendaController::class, 'Actualizar cuenta: ' . $e->getMessage());
            $rh->setResponse(false, 'No se pudo actualizar la información');
        }

        echo json_encode($rh);
    }

    public function postCambiarPassword(): void
    {
        $rh = new ResponseHelper();

        if (!$this->clienteLoggedIn()) {
            echo json_encode($rh->setResponse(false, 'Sesión expirada'));
            return;
        }

        $actual   = $_POST['password_actual']  ?? '';
        $nueva    = $_POST['password_nueva']   ?? '';
        $confirma = $_POST['password_confirma'] ?? '';

        if (empty($actual) || empty($nueva)) {
            echo json_encode($rh->setResponse(false, 'Todos los campos son requeridos'));
            return;
        }

        if (strlen($nueva) < 6) {
            echo json_encode($rh->setResponse(false, 'La nueva contraseña debe tener mínimo 6 caracteres'));
            return;
        }

        if ($nueva !== $confirma) {
            echo json_encode($rh->setResponse(false, 'Las contraseñas no coinciden'));
            return;
        }

        try {
            $cliente = $this->getClienteActual();

            if (!$this->verificarPasswordCliente($actual, $cliente->password)) {
                echo json_encode($rh->setResponse(false, 'La contraseña actual es incorrecta'));
                return;
            }

            $cliente->password = password_hash($nueva, PASSWORD_BCRYPT, ['cost' => 12]);
            $cliente->save();

            $rh->setResponse(true, 'Contraseña actualizada correctamente');
        } catch (\Exception $e) {
            Log::error(TiendaController::class, 'Cambiar password: ' . $e->getMessage());
            $rh->setResponse(false, 'No se pudo actualizar la contraseña');
        }

        echo json_encode($rh);
    }

    // ── Wishlist ──────────────────────────────────────────────
    public function getWishlist(): string
    {
        if (!$this->clienteLoggedIn()) {
            header('Location: ' . _BASE_HTTP_ . 'tienda/login');
            exit;
        }

        $wishlistRepo = new WishlistRepository();
        $productos    = $wishlistRepo->listar((int)$_SESSION['cliente_id']);
        $this->adjuntarPromociones($productos);

        return $this->render('tienda/wishlist.twig', [
            'title'       => 'Mi lista de deseos',
            'menu'        => false,
            'productos'   => $productos,
            'wishlist_ids' => $wishlistRepo->idsDeCliente((int)$_SESSION['cliente_id']),
        ]);
    }

    public function postWishlistToggle(): void
    {
        $rh = new ResponseHelper();

        if (!$this->clienteLoggedIn()) {
            echo json_encode($rh->setResponse(false, 'Debes iniciar sesión'));
            return;
        }

        $productoId = (int)($_POST['producto_id'] ?? 0);
        if ($productoId <= 0) {
            echo json_encode($rh->setResponse(false, 'Producto inválido'));
            return;
        }

        try {
            $wishlistRepo = new WishlistRepository();
            $accion       = $wishlistRepo->toggle((int)$_SESSION['cliente_id'], $productoId);
            $rh->setResponse(true, $accion === 'added' ? 'Agregado a tu lista' : 'Eliminado de tu lista');
            $rh->result = ['accion' => $accion];
        } catch (\Exception $e) {
            Log::error(TiendaController::class, 'Wishlist: ' . $e->getMessage());
            $rh->setResponse(false, 'No se pudo actualizar la lista');
        }

        echo json_encode($rh);
    }

    // ── Helper: wishlist IDs del cliente activo ───────────────
    private function wishlistIds(): array
    {
        if (!$this->clienteLoggedIn()) return [];
        return (new WishlistRepository())->idsDeCliente((int)$_SESSION['cliente_id']);
    }

    // ── Reseña de producto ────────────────────────────────────
    public function postResena(): void
    {
        $rh = new ResponseHelper();

        if (!$this->clienteLoggedIn()) {
            echo json_encode($rh->setResponse(false, 'Debes iniciar sesión para dejar una reseña'));
            return;
        }

        $productoId   = (int)($_POST['producto_id']  ?? 0);
        $calificacion = (int)($_POST['calificacion'] ?? 0);
        $comentario   = trim($_POST['comentario']    ?? '');

        if ($productoId <= 0 || $calificacion < 1 || $calificacion > 5) {
            echo json_encode($rh->setResponse(false, 'Datos inválidos'));
            return;
        }

        $resenaRepo = new ResenaRepository();
        echo json_encode($resenaRepo->guardar(
            (int)$_SESSION['cliente_id'],
            $productoId,
            $calificacion,
            $comentario
        ));
    }

    // ── Aplicar cupón (cliente) ───────────────────────────────
    public function postAplicarCupon(): void
    {
        $rh     = new ResponseHelper();
        $codigo = trim($_POST['codigo'] ?? '');
        $total  = (float)($_POST['total'] ?? 0);

        if (empty($codigo)) {
            echo json_encode($rh->setResponse(false, 'Ingresa un código de cupón'));
            return;
        }

        if ($total <= 0) {
            echo json_encode($rh->setResponse(false, 'El carrito está vacío'));
            return;
        }

        $cuponRepo = new CuponRepository();
        echo json_encode($cuponRepo->validar($codigo, $total));
    }

    // ── Cancelar pedido (cliente) ─────────────────────────────
    public function postCancelarPedido(): void
    {
        $rh        = new ResponseHelper();
        $pedidoId  = (int)($_POST['pedido_id'] ?? 0);

        if (!$this->clienteLoggedIn()) {
            echo json_encode($rh->setResponse(false, 'Debes iniciar sesión'));
            return;
        }

        if ($pedidoId <= 0) {
            echo json_encode($rh->setResponse(false, 'Pedido no válido'));
            return;
        }

        $pedidoRepo = new PedidoRepository();
        echo json_encode($pedidoRepo->cancelarPorCliente($pedidoId, (int)$_SESSION['cliente_id']));
    }

    // ── Recuperar contraseña ──────────────────────────────────
    public function getRecuperar(): string
    {
        if ($this->clienteLoggedIn()) {
            header('Location: ' . _BASE_HTTP_ . 'tienda');
            exit;
        }

        return $this->render('tienda/recuperar.twig', [
            'title' => 'Recuperar contraseña',
            'menu'  => false,
        ]);
    }

    public function postRecuperar(): void
    {
        $rh    = new ResponseHelper();
        $email = trim($_POST['email'] ?? '');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode($rh->setResponse(false, 'Ingresa un correo electrónico válido'));
            return;
        }

        try {
            $cliente = Cliente::where('email', $email)->where('activo', 1)->first();

            if ($cliente) {
                Capsule::table('password_resets_clientes')->where('email', $email)->delete();

                $token     = bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', strtotime('+60 minutes'));

                Capsule::table('password_resets_clientes')->insert([
                    'email'      => $email,
                    'token'      => $token,
                    'expires_at' => $expiresAt,
                ]);

                $this->enviarEmailRecuperar($cliente, $token);
            }

            // Respuesta genérica — no revelar si el email existe (anti-enumeración)
            echo json_encode($rh->setResponse(true,
                'Si el correo está registrado, recibirás un enlace en los próximos minutos'
            ));
        } catch (\Exception $e) {
            Log::error(TiendaController::class, 'Recuperar password: ' . $e->getMessage());
            echo json_encode($rh->setResponse(false, 'No se pudo procesar la solicitud'));
        }
    }

    public function getNuevaPassword(string $token): string
    {
        $reset = Capsule::table('password_resets_clientes')
            ->where('token', $token)
            ->where('expires_at', '>=', date('Y-m-d H:i:s'))
            ->first();

        if (!$reset) {
            return $this->render('tienda/recuperar.twig', [
                'title'        => 'Enlace inválido',
                'menu'         => false,
                'tokenInvalid' => true,
            ]);
        }

        return $this->render('tienda/nueva_password.twig', [
            'title' => 'Nueva contraseña',
            'menu'  => false,
            'token' => $token,
        ]);
    }

    public function postNuevaPassword(): void
    {
        $rh       = new ResponseHelper();
        $token    = trim($_POST['token']           ?? '');
        $password = $_POST['password']             ?? '';
        $confirm  = $_POST['password_confirm']     ?? '';

        if (empty($token) || empty($password)) {
            echo json_encode($rh->setResponse(false, 'Datos incompletos'));
            return;
        }

        if (strlen($password) < 6) {
            echo json_encode($rh->setResponse(false, 'La contraseña debe tener mínimo 6 caracteres'));
            return;
        }

        if ($password !== $confirm) {
            echo json_encode($rh->setResponse(false, 'Las contraseñas no coinciden'));
            return;
        }

        try {
            $reset = Capsule::table('password_resets_clientes')
                ->where('token', $token)
                ->where('expires_at', '>=', date('Y-m-d H:i:s'))
                ->first();

            if (!$reset) {
                echo json_encode($rh->setResponse(false, 'El enlace ha expirado o ya fue usado. Solicita uno nuevo'));
                return;
            }

            $cliente = Cliente::where('email', $reset->email)->where('activo', 1)->first();

            if (!$cliente) {
                echo json_encode($rh->setResponse(false, 'No se encontró la cuenta'));
                return;
            }

            $cliente->password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $cliente->save();

            Capsule::table('password_resets_clientes')->where('token', $token)->delete();

            $rh->setResponse(true, '¡Contraseña actualizada! Ahora puedes iniciar sesión');
            $rh->href = 'tienda/login';
        } catch (\Exception $e) {
            Log::error(TiendaController::class, 'Nueva password: ' . $e->getMessage());
            echo json_encode($rh->setResponse(false, 'No se pudo actualizar la contraseña'));
            return;
        }

        echo json_encode($rh);
    }

    // ── Email: recuperar contraseña ───────────────────────────
    private function enviarEmailRecuperar(Cliente $cliente, string $token): void
    {
        try {
            MailHelper::send(
                $cliente->email,
                $cliente->nombre . ' ' . $cliente->apellido,
                'Recupera tu contraseña — ' . Configuracion::get('negocio_nombre', 'Supermercado Web'),
                'recuperar_password',
                [
                    'cliente'   => $cliente,
                    'reset_url' => _BASE_HTTP_ . 'tienda/nueva-password/' . $token,
                    'negocio'   => Configuracion::get('negocio_nombre', 'Supermercado Web'),
                    'base_url'  => _BASE_HTTP_,
                ]
            );
        } catch (\Exception $e) {
            Log::error(TiendaController::class, 'Email recuperar: ' . $e->getMessage());
        }
    }

    // ── Logout ────────────────────────────────────────────────
    public function getLogout()
    {
        unset($_SESSION['cliente_id'], $_SESSION['cliente_nombre'], $_SESSION['cliente_email']);
        header('Location: ' . _BASE_HTTP_ . 'tienda');
        exit;
    }

    // ── Helpers privados ──────────────────────────────────────
    private function verificarPasswordCliente(string $password, string $hash): bool
    {
        if ($this->esBcrypt($hash)) {
            return password_verify($password, $hash);
        }
        return hash_equals($hash, sha1($password));
    }

    // ── Colonias por código postal (Ajax, público) ────────────
    public function getColonias(): void
    {
        $cp = preg_replace('/\D/', '', $_GET['cp'] ?? '');
        $rh = new ResponseHelper();

        if (strlen($cp) !== 5) {
            echo json_encode($rh->setResponse(false, 'CP inválido'));
            return;
        }

        $filas = CodigoPostal::where('cp', $cp)
            ->orderBy('colonia')
            ->get(['colonia', 'municipio', 'estado', 'ciudad']);

        if ($filas->isEmpty()) {
            echo json_encode($rh->setResponse(false, 'CP no encontrado'));
            return;
        }

        $primero = $filas->first();

        $rh->setResponse(true, 'OK');
        $rh->result = [
            'colonias'  => $filas->pluck('colonia')->unique()->values()->toArray(),
            'municipio' => $primero->municipio,
            'ciudad'    => $primero->ciudad ?: $primero->municipio,
            'estado'    => $primero->estado,
        ];
        echo json_encode($rh);
    }

    private function esBcrypt(string $hash): bool
    {
        return str_starts_with($hash, '$2y$') || str_starts_with($hash, '$2a$');
    }

    private function clienteLoggedIn(): bool
    {
        return !empty($_SESSION['cliente_id']);
    }

    private function getClienteActual(): ?Cliente
    {
        if (!$this->clienteLoggedIn()) return null;
        return Cliente::find($_SESSION['cliente_id']);
    }
}