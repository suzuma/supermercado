<?php
/*
    autor: Noe Cazarez Camargo
    fecha: 2019-06-24
    descripcion: definicion de las rutas del sistema
*/

/* Controllers */
$router->group(['before' => 'auth'], function($router){
    $router->controller('/home', 'App\\Controllers\\HomeController');
    $router->controller('/inventario', 'App\\Controllers\\InventarioController');
    $router->get('/inventario/imprimirEtiquetas',  ['App\\Controllers\\InventarioController', 'getImprimirEtiquetas']);
    $router->post('/inventario/generarCodigo',     ['App\\Controllers\\InventarioController', 'postGenerarCodigo']);
    $router->controller('/empleados', 'App\\Controllers\\EmpleadoController');

    // Corte de caja
    $router->get('/corte-caja',              ['App\\Controllers\\CorteCajaController', 'getIndex']);
    $router->get('/corte-caja/historial',    ['App\\Controllers\\CorteCajaController', 'getHistorial']);
    $router->get('/corte-caja/detalle/{id}', ['App\\Controllers\\CorteCajaController', 'getDetalle']);
    $router->post('/corte-caja/registrar',   ['App\\Controllers\\CorteCajaController', 'postRegistrar']);

    // Devoluciones
    $router->get('/devoluciones',                ['App\\Controllers\\DevolucionesController', 'getIndex']);
    $router->get('/devoluciones/historial',      ['App\\Controllers\\DevolucionesController', 'getHistorial']);
    $router->get('/devoluciones/recibo/{id}',    ['App\\Controllers\\DevolucionesController', 'getRecibo']);
    $router->post('/devoluciones/buscarVenta',   ['App\\Controllers\\DevolucionesController', 'postBuscarVenta']);
    $router->post('/devoluciones/registrar',     ['App\\Controllers\\DevolucionesController', 'postRegistrar']);

    // Ventas — rutas explícitas para los endpoints Ajax
    $router->get('/ventas',                    ['App\\Controllers\\VentasController', 'getIndex']);
    $router->get('/ventas/historial',          ['App\\Controllers\\VentasController', 'getHistorial']);
    $router->get('/ventas/ticket/{id}',        ['App\\Controllers\\VentasController', 'getTicket']);
    $router->post('/ventas/registrar',         ['App\\Controllers\\VentasController', 'postRegistrar']);
    $router->post('/ventas/cancelar',          ['App\\Controllers\\VentasController', 'postCancelar']);
    $router->post('/ventas/buscarProducto',    ['App\\Controllers\\VentasController', 'postBuscarProducto']);
    $router->post('/ventas/buscarCliente',     ['App\\Controllers\\VentasController', 'postBuscarCliente']);

    $router->get('/pedidos',                  ['App\\Controllers\\PedidosController', 'getIndex']);
    $router->get('/pedidos/kanban',           ['App\\Controllers\\PedidosController', 'getKanban']);
    $router->get('/pedidos/exportar',         ['App\\Controllers\\PedidosController', 'getExportar']);
    $router->get('/pedidos/detalle/{id}',     ['App\\Controllers\\PedidosController', 'getDetalle']);
    $router->get('/pedidos/orden/{id}',       ['App\\Controllers\\PedidosController', 'getOrden']);
    $router->post('/pedidos/estado',          ['App\\Controllers\\PedidosController', 'postEstado']);
    $router->post('/pedidos/asignar',         ['App\\Controllers\\PedidosController', 'postAsignar']);
    $router->post('/pedidos/cancelar',        ['App\\Controllers\\PedidosController', 'postCancelar']);

    $router->get('/reportes',                  ['App\\Controllers\\ReportesController', 'getIndex']);
    $router->get('/reportes/utilidades',       ['App\\Controllers\\ReportesController', 'getUtilidades']);
    $router->get('/reportes/csvUtilidades',    ['App\\Controllers\\ReportesController', 'getCsvUtilidades']);
    $router->get('/reportes/pdfVentas',        ['App\\Controllers\\ReportesController', 'getPdfVentas']);
    $router->get('/reportes/pdfInventario',    ['App\\Controllers\\ReportesController', 'getPdfInventario']);
    $router->get('/reportes/pdfEmpleados',     ['App\\Controllers\\ReportesController', 'getPdfEmpleados']);
    $router->get('/reportes/csvVentas',        ['App\\Controllers\\ReportesController', 'getCsvVentas']);
    $router->get('/reportes/csvInventario',    ['App\\Controllers\\ReportesController', 'getCsvInventario']);

    $router->get('/proveedores',                       ['App\\Controllers\\ProveedoresController', 'getIndex']);
    $router->get('/proveedores/formulario',            ['App\\Controllers\\ProveedoresController', 'getFormulario']);
    $router->get('/proveedores/formulario/{id}',       ['App\\Controllers\\ProveedoresController', 'getFormulario']);
    $router->get('/proveedores/ordenes/{id}',          ['App\\Controllers\\ProveedoresController', 'getOrdenes']);
    $router->get('/proveedores/nuevaOrden/{id}',       ['App\\Controllers\\ProveedoresController', 'getNuevaOrden']);
    $router->get('/proveedores/detalleOrden/{id}',     [
        'App\\Controllers\\ProveedoresController', 'getDetalleOrden']);
    $router->post('/proveedores/guardar',              ['App\\Controllers\\ProveedoresController', 'postGuardar']);
    $router->post('/proveedores/desactivar',           ['App\\Controllers\\ProveedoresController', 'postDesactivar']);
    $router->post('/proveedores/guardarOrden',         ['App\\Controllers\\ProveedoresController', 'postGuardarOrden']);
    $router->post('/proveedores/estadoOrden',          ['App\\Controllers\\ProveedoresController', 'postEstadoOrden']);

    $router->get('/auditoria',  ['App\\Controllers\\AuditoriaController', 'getIndex']);

    $router->get('/cupones',               ['App\\Controllers\\CuponesController', 'getIndex']);
    $router->post('/cupones/guardar',      ['App\\Controllers\\CuponesController', 'postGuardar']);
    $router->post('/cupones/desactivar',   ['App\\Controllers\\CuponesController', 'postDesactivar']);

    $router->get('/configuracion',                          ['App\\Controllers\\ConfiguracionController', 'getIndex']);
    $router->post('/configuracion/negocio',                 ['App\\Controllers\\ConfiguracionController', 'postNegocio']);
    $router->post('/configuracion/password',                ['App\\Controllers\\ConfiguracionController', 'postPassword']);
    $router->post('/configuracion/usuario',                 ['App\\Controllers\\ConfiguracionController', 'postUsuario']);
    $router->post('/configuracion/desactivarUsuario',       ['App\\Controllers\\ConfiguracionController', 'postDesactivarUsuario']);


    $router->get('/clientes',                  ['App\\Controllers\\ClientesController', 'getIndex']);
    $router->get('/clientes/formulario',       ['App\\Controllers\\ClientesController', 'getFormulario']);
    $router->get('/clientes/formulario/{id}',  ['App\\Controllers\\ClientesController', 'getFormulario']);
    $router->get('/clientes/perfil/{id}',      ['App\\Controllers\\ClientesController', 'getPerfil']);
    $router->post('/clientes/guardar',         ['App\\Controllers\\ClientesController', 'postGuardar']);
    $router->post('/clientes/desactivar',      ['App\\Controllers\\ClientesController', 'postDesactivar']);


    $router->get('/promociones',                   ['App\\Controllers\\PromocionesController', 'getIndex']);
    $router->get('/promociones/formulario',        ['App\\Controllers\\PromocionesController', 'getFormulario']);
    $router->get('/promociones/formulario/{id}',   ['App\\Controllers\\PromocionesController', 'getFormulario']);
    $router->get('/promociones/producto/{id}',     ['App\\Controllers\\PromocionesController', 'getPromoProducto']);
    $router->post('/promociones/guardar',          ['App\\Controllers\\PromocionesController', 'postGuardar']);
    $router->post('/promociones/desactivar',       ['App\\Controllers\\PromocionesController', 'postDesactivar']);
    $router->post('/promociones/calcular',         ['App\\Controllers\\PromocionesController', 'postCalcular']);

});


$router->get('/tienda',                    ['App\\Controllers\\TiendaController', 'getIndex']);
$router->get('/tienda/catalogo',           ['App\\Controllers\\TiendaController', 'getCatalogo']);
$router->get('/tienda/producto/{id}',      ['App\\Controllers\\TiendaController', 'getProducto']);
$router->get('/tienda/checkout',           ['App\\Controllers\\TiendaController', 'getCheckout']);
$router->get('/tienda/confirmacion/{id}',  ['App\\Controllers\\TiendaController', 'getConfirmacion']);
$router->get('/tienda/seguimiento',        ['App\\Controllers\\TiendaController', 'getSeguimiento']);
$router->get('/tienda/login',              ['App\\Controllers\\TiendaController', 'getLogin']);
$router->get('/tienda/logout',              ['App\\Controllers\\TiendaController', 'getLogout']);
$router->get('/tienda/registro',            ['App\\Controllers\\TiendaController', 'getRegistro']);
$router->get('/tienda/recuperar',           ['App\\Controllers\\TiendaController', 'getRecuperar']);
$router->get('/tienda/wishlist',            ['App\\Controllers\\TiendaController', 'getWishlist']);
$router->get('/tienda/cuenta',              ['App\\Controllers\\TiendaController', 'getCuenta']);
$router->get('/tienda/nueva-password/{token}', ['App\\Controllers\\TiendaController', 'getNuevaPassword']);

$router->group(['before' => 'csrf'], function($router) {
    $router->post('/tienda/pedido',           ['App\\Controllers\\TiendaController', 'postPedido']);
    $router->post('/tienda/login',            ['App\\Controllers\\TiendaController', 'postLogin']);
    $router->post('/tienda/registro',         ['App\\Controllers\\TiendaController', 'postRegistro']);
    $router->post('/tienda/recuperar',        ['App\\Controllers\\TiendaController', 'postRecuperar']);
    $router->post('/tienda/nueva-password',   ['App\\Controllers\\TiendaController', 'postNuevaPassword']);
    $router->post('/tienda/cancelar-pedido',  ['App\\Controllers\\TiendaController', 'postCancelarPedido']);
    $router->post('/tienda/aplicar-cupon',    ['App\\Controllers\\TiendaController', 'postAplicarCupon']);
    $router->post('/tienda/resena',              ['App\\Controllers\\TiendaController', 'postResena']);
    $router->post('/tienda/wishlist/toggle',      ['App\\Controllers\\TiendaController', 'postWishlistToggle']);
    $router->post('/tienda/cuenta/actualizar',    ['App\\Controllers\\TiendaController', 'postActualizarCuenta']);
    $router->post('/tienda/cuenta/password',      ['App\\Controllers\\TiendaController', 'postCambiarPassword']);
});


$router->controller('/auth', 'App\\Controllers\\AuthController');


$router->get('/', function(){
    /*
     * si quieres que el usuario se loguee cuando inicie el sistema
     * */
    /*if(!\Core\Auth::isLoggedIn()){
        \App\Helpers\UrlHelper::redirect('auth');
    } else {
        \App\Helpers\UrlHelper::redirect('home');
    }*/

    //Si quieres tener una pagina publica
    \App\Helpers\UrlHelper::redirect('home');
});

$router->get('/welcome', function(){
    return 'Welcome page';
}, ['before' => 'auth']);

$router->get('/test', function(){
    return 'Plantilla desarrollada por SuZuMa';
}, ['before' => 'auth']);