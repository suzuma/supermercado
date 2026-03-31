<?php

$router->group(['before' => 'auth'], function ($router) {

    // Dashboard — todos los roles autenticados
    $router->get('/home', ['App\\Controllers\\HomeController', 'getIndex']);

    // ── Ventas ───────────────────────────────────────────────
    $router->group(['before' => 'can:ventas.ver'], function ($router) {
        $router->get('/ventas',               ['App\\Controllers\\VentasController', 'getIndex']);
        $router->get('/ventas/historial',     ['App\\Controllers\\VentasController', 'getHistorial']);
        $router->get('/ventas/ticket/{id}',   ['App\\Controllers\\VentasController', 'getTicket']);
        $router->post('/ventas/buscarProducto', ['App\\Controllers\\VentasController', 'postBuscarProducto']);
        $router->post('/ventas/buscarCliente',  ['App\\Controllers\\VentasController', 'postBuscarCliente']);
    });

    $router->group(['before' => 'can:ventas.registrar'], function ($router) {
        $router->post('/ventas/registrar', ['App\\Controllers\\VentasController', 'postRegistrar']);
    });

    $router->group(['before' => 'can:ventas.cancelar'], function ($router) {
        $router->post('/ventas/cancelar', ['App\\Controllers\\VentasController', 'postCancelar']);
    });

    // ── Inventario ────────────────────────────────────────────
    $router->group(['before' => 'can:inventario.ver'], function ($router) {
        $router->get('/inventario',                   ['App\\Controllers\\InventarioController', 'getIndex']);
        $router->get('/inventario/formulario',        ['App\\Controllers\\InventarioController', 'getFormulario']);
        $router->get('/inventario/formulario/{id}',   ['App\\Controllers\\InventarioController', 'getFormulario']);
        $router->get('/inventario/imprimirEtiquetas', ['App\\Controllers\\InventarioController', 'getImprimirEtiquetas']);
        $router->post('/inventario/buscarCodigo',     ['App\\Controllers\\InventarioController', 'postBuscarCodigo']);
    });

    $router->group(['before' => 'can:inventario.editar'], function ($router) {
        $router->post('/inventario/guardar',      ['App\\Controllers\\InventarioController', 'postGuardar']);
        $router->post('/inventario/eliminar',     ['App\\Controllers\\InventarioController', 'postEliminar']);
        $router->post('/inventario/generarCodigo',['App\\Controllers\\InventarioController', 'postGenerarCodigo']);
    });

    // ── Corte de caja ─────────────────────────────────────────
    $router->group(['before' => 'can:corte_caja.ver'], function ($router) {
        $router->get('/corte-caja',              ['App\\Controllers\\CorteCajaController', 'getIndex']);
        $router->get('/corte-caja/historial',    ['App\\Controllers\\CorteCajaController', 'getHistorial']);
        $router->get('/corte-caja/detalle/{id}', ['App\\Controllers\\CorteCajaController', 'getDetalle']);
    });

    $router->group(['before' => 'can:corte_caja.registrar'], function ($router) {
        $router->post('/corte-caja/registrar', ['App\\Controllers\\CorteCajaController', 'postRegistrar']);
    });

    // ── Devoluciones ──────────────────────────────────────────
    $router->group(['before' => 'can:devoluciones.ver'], function ($router) {
        $router->get('/devoluciones',             ['App\\Controllers\\DevolucionesController', 'getIndex']);
        $router->get('/devoluciones/historial',   ['App\\Controllers\\DevolucionesController', 'getHistorial']);
        $router->get('/devoluciones/recibo/{id}', ['App\\Controllers\\DevolucionesController', 'getRecibo']);
    });

    $router->group(['before' => 'can:devoluciones.registrar'], function ($router) {
        $router->post('/devoluciones/buscarVenta', ['App\\Controllers\\DevolucionesController', 'postBuscarVenta']);
        $router->post('/devoluciones/registrar',   ['App\\Controllers\\DevolucionesController', 'postRegistrar']);
    });

    // ── Pedidos en línea ──────────────────────────────────────
    $router->group(['before' => 'can:pedidos.ver'], function ($router) {
        $router->get('/pedidos',              ['App\\Controllers\\PedidosController', 'getIndex']);
        $router->get('/pedidos/kanban',       ['App\\Controllers\\PedidosController', 'getKanban']);
        $router->get('/pedidos/exportar',     ['App\\Controllers\\PedidosController', 'getExportar']);
        $router->get('/pedidos/detalle/{id}', ['App\\Controllers\\PedidosController', 'getDetalle']);
        $router->get('/pedidos/orden/{id}',   ['App\\Controllers\\PedidosController', 'getOrden']);
    });

    $router->group(['before' => 'can:pedidos.gestionar'], function ($router) {
        $router->post('/pedidos/estado',   ['App\\Controllers\\PedidosController', 'postEstado']);
        $router->post('/pedidos/asignar',  ['App\\Controllers\\PedidosController', 'postAsignar']);
        $router->post('/pedidos/cancelar', ['App\\Controllers\\PedidosController', 'postCancelar']);
    });

    // ── Reportes ──────────────────────────────────────────────
    $router->group(['before' => 'can:reportes.ver'], function ($router) {
        $router->get('/reportes',                ['App\\Controllers\\ReportesController', 'getIndex']);
        $router->get('/reportes/utilidades',     ['App\\Controllers\\ReportesController', 'getUtilidades']);
        $router->get('/reportes/csvUtilidades',  ['App\\Controllers\\ReportesController', 'getCsvUtilidades']);
        $router->get('/reportes/pdfVentas',      ['App\\Controllers\\ReportesController', 'getPdfVentas']);
        $router->get('/reportes/pdfInventario',  ['App\\Controllers\\ReportesController', 'getPdfInventario']);
        $router->get('/reportes/pdfEmpleados',   ['App\\Controllers\\ReportesController', 'getPdfEmpleados']);
        $router->get('/reportes/csvVentas',      ['App\\Controllers\\ReportesController', 'getCsvVentas']);
        $router->get('/reportes/csvInventario',  ['App\\Controllers\\ReportesController', 'getCsvInventario']);
    });

    // ── Proveedores ───────────────────────────────────────────
    $router->group(['before' => 'can:proveedores.ver'], function ($router) {
        $router->get('/proveedores',                   ['App\\Controllers\\ProveedoresController', 'getIndex']);
        $router->get('/proveedores/formulario',        ['App\\Controllers\\ProveedoresController', 'getFormulario']);
        $router->get('/proveedores/formulario/{id}',   ['App\\Controllers\\ProveedoresController', 'getFormulario']);
        $router->get('/proveedores/ordenes/{id}',      ['App\\Controllers\\ProveedoresController', 'getOrdenes']);
        $router->get('/proveedores/nuevaOrden/{id}',   ['App\\Controllers\\ProveedoresController', 'getNuevaOrden']);
        $router->get('/proveedores/detalleOrden/{id}', ['App\\Controllers\\ProveedoresController', 'getDetalleOrden']);
    });

    $router->group(['before' => 'can:proveedores.editar'], function ($router) {
        $router->post('/proveedores/guardar',      ['App\\Controllers\\ProveedoresController', 'postGuardar']);
        $router->post('/proveedores/desactivar',   ['App\\Controllers\\ProveedoresController', 'postDesactivar']);
        $router->post('/proveedores/guardarOrden', ['App\\Controllers\\ProveedoresController', 'postGuardarOrden']);
        $router->post('/proveedores/estadoOrden',  ['App\\Controllers\\ProveedoresController', 'postEstadoOrden']);
    });

    // ── Clientes ──────────────────────────────────────────────
    $router->group(['before' => 'can:clientes.ver'], function ($router) {
        $router->get('/clientes',                 ['App\\Controllers\\ClientesController', 'getIndex']);
        $router->get('/clientes/formulario',      ['App\\Controllers\\ClientesController', 'getFormulario']);
        $router->get('/clientes/formulario/{id}', ['App\\Controllers\\ClientesController', 'getFormulario']);
        $router->get('/clientes/perfil/{id}',     ['App\\Controllers\\ClientesController', 'getPerfil']);
    });

    $router->group(['before' => 'can:clientes.editar'], function ($router) {
        $router->post('/clientes/guardar',    ['App\\Controllers\\ClientesController', 'postGuardar']);
        $router->post('/clientes/desactivar', ['App\\Controllers\\ClientesController', 'postDesactivar']);
    });

    // ── Promociones ───────────────────────────────────────────
    $router->group(['before' => 'can:promociones.ver'], function ($router) {
        $router->get('/promociones',                  ['App\\Controllers\\PromocionesController', 'getIndex']);
        $router->get('/promociones/formulario',       ['App\\Controllers\\PromocionesController', 'getFormulario']);
        $router->get('/promociones/formulario/{id}',  ['App\\Controllers\\PromocionesController', 'getFormulario']);
        $router->get('/promociones/producto/{id}',    ['App\\Controllers\\PromocionesController', 'getPromoProducto']);
        $router->post('/promociones/calcular',        ['App\\Controllers\\PromocionesController', 'postCalcular']);
    });

    $router->group(['before' => 'can:promociones.editar'], function ($router) {
        $router->post('/promociones/guardar',    ['App\\Controllers\\PromocionesController', 'postGuardar']);
        $router->post('/promociones/desactivar', ['App\\Controllers\\PromocionesController', 'postDesactivar']);
    });

    // ── Empleados ─────────────────────────────────────────────
    $router->group(['before' => 'can:empleados.ver'], function ($router) {
        $router->get('/empleados',                ['App\\Controllers\\EmpleadoController', 'getIndex']);
        $router->get('/empleados/formulario',     ['App\\Controllers\\EmpleadoController', 'getFormulario']);
        $router->get('/empleados/formulario/{id}',['App\\Controllers\\EmpleadoController', 'getFormulario']);
        $router->get('/empleados/asistencia/{id}',['App\\Controllers\\EmpleadoController', 'getAsistencia']);
    });

    $router->group(['before' => 'can:empleados.editar'], function ($router) {
        $router->post('/empleados/guardar',     ['App\\Controllers\\EmpleadoController', 'postGuardar']);
        $router->post('/empleados/desactivar',  ['App\\Controllers\\EmpleadoController', 'postDesactivar']);
        $router->post('/empleados/asistencia',  ['App\\Controllers\\EmpleadoController', 'postAsistencia']);
    });

    // ── Cupones ───────────────────────────────────────────────
    $router->group(['before' => 'can:cupones.ver'], function ($router) {
        $router->get('/cupones', ['App\\Controllers\\CuponesController', 'getIndex']);
    });

    $router->group(['before' => 'can:cupones.editar'], function ($router) {
        $router->post('/cupones/guardar',    ['App\\Controllers\\CuponesController', 'postGuardar']);
        $router->post('/cupones/desactivar', ['App\\Controllers\\CuponesController', 'postDesactivar']);
    });

    // ── Auditoría ─────────────────────────────────────────────
    $router->group(['before' => 'can:auditoria.ver'], function ($router) {
        $router->get('/auditoria', ['App\\Controllers\\AuditoriaController', 'getIndex']);
    });

    // ── Configuración ─────────────────────────────────────────
    $router->group(['before' => 'can:configuracion.ver'], function ($router) {
        $router->get('/configuracion', ['App\\Controllers\\ConfiguracionController', 'getIndex']);
        $router->post('/configuracion/password', ['App\\Controllers\\ConfiguracionController', 'postPassword']);
    });

    $router->group(['before' => 'can:configuracion.editar'], function ($router) {
        $router->post('/configuracion/negocio',           ['App\\Controllers\\ConfiguracionController', 'postNegocio']);
        $router->post('/configuracion/usuario',           ['App\\Controllers\\ConfiguracionController', 'postUsuario']);
        $router->post('/configuracion/desactivarUsuario', ['App\\Controllers\\ConfiguracionController', 'postDesactivarUsuario']);
    });

    // ── Permisos — solo Administrador ─────────────────────────
    $router->group(['before' => 'isAdmin'], function ($router) {
        $router->get('/permisos',          ['App\\Controllers\\PermisosController', 'getIndex']);
        $router->post('/permisos/guardar', ['App\\Controllers\\PermisosController', 'postGuardar']);
    });
});


// ── Tienda pública ────────────────────────────────────────────
$router->get('/tienda',                       ['App\\Controllers\\TiendaController', 'getIndex']);
$router->get('/tienda/catalogo',              ['App\\Controllers\\TiendaController', 'getCatalogo']);
$router->get('/tienda/producto/{id}',         ['App\\Controllers\\TiendaController', 'getProducto']);
$router->get('/tienda/checkout',              ['App\\Controllers\\TiendaController', 'getCheckout']);
$router->get('/tienda/confirmacion/{id}',     ['App\\Controllers\\TiendaController', 'getConfirmacion']);
$router->get('/tienda/seguimiento',           ['App\\Controllers\\TiendaController', 'getSeguimiento']);
$router->get('/tienda/login',                 ['App\\Controllers\\TiendaController', 'getLogin']);
$router->get('/tienda/logout',                ['App\\Controllers\\TiendaController', 'getLogout']);
$router->get('/tienda/registro',              ['App\\Controllers\\TiendaController', 'getRegistro']);
$router->get('/tienda/recuperar',             ['App\\Controllers\\TiendaController', 'getRecuperar']);
$router->get('/tienda/wishlist',              ['App\\Controllers\\TiendaController', 'getWishlist']);
$router->get('/tienda/cuenta',                ['App\\Controllers\\TiendaController', 'getCuenta']);
$router->get('/tienda/nueva-password/{token}',['App\\Controllers\\TiendaController', 'getNuevaPassword']);

$router->group(['before' => 'csrf'], function ($router) {
    $router->post('/tienda/pedido',            ['App\\Controllers\\TiendaController', 'postPedido']);
    $router->post('/tienda/login',             ['App\\Controllers\\TiendaController', 'postLogin']);
    $router->post('/tienda/registro',          ['App\\Controllers\\TiendaController', 'postRegistro']);
    $router->post('/tienda/recuperar',         ['App\\Controllers\\TiendaController', 'postRecuperar']);
    $router->post('/tienda/nueva-password',    ['App\\Controllers\\TiendaController', 'postNuevaPassword']);
    $router->post('/tienda/cancelar-pedido',   ['App\\Controllers\\TiendaController', 'postCancelarPedido']);
    $router->post('/tienda/aplicar-cupon',     ['App\\Controllers\\TiendaController', 'postAplicarCupon']);
    $router->post('/tienda/resena',            ['App\\Controllers\\TiendaController', 'postResena']);
    $router->post('/tienda/wishlist/toggle',   ['App\\Controllers\\TiendaController', 'postWishlistToggle']);
    $router->post('/tienda/cuenta/actualizar', ['App\\Controllers\\TiendaController', 'postActualizarCuenta']);
    $router->post('/tienda/cuenta/password',   ['App\\Controllers\\TiendaController', 'postCambiarPassword']);
});


// ── Autenticación de staff ────────────────────────────────────
$router->controller('/auth', 'App\\Controllers\\AuthController');


$router->get('/', function () {
    \App\Helpers\UrlHelper::redirect('home');
});