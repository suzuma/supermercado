<?php
/*
    autor: Noe Cazarez Camargo
    fecha: 2019-06-24
    descripcion: CLASE BASE DE CONTROLLERS — registra filtros y funciones Twig
*/
namespace Core;

use App\Models\Configuracion;
use App\Models\Producto;
use App\Services\CacheService;

class Controller {
    protected $provider;

    public function __construct() {
        $config = ServicesContainer::getConfig();

        $loader = new \Twig\Loader\FilesystemLoader(_APP_PATH_ . 'Views/');

        $this->provider = new \Twig\Environment($loader, array(
            'cache' => !$config['cache'] ? false : _CACHE_PATH_,
            'debug' => true,
        ));

        $this->provider->addExtension(new \Twig\Extension\DebugExtension());

        $this->addCustomFilters();
    }

    private function addCustomFilters() {
        // Filtros
        $this->provider->addFilter(new \Twig\TwigFilter('public', ['App\\Helpers\\UrlHelper', 'public']));
        $this->provider->addFilter(new \Twig\TwigFilter('url', ['App\\Helpers\\UrlHelper', 'base']));
        $this->provider->addFilter(new \Twig\TwigFilter('padLeft', function($input, $zeros = 4){
            return str_pad($input, $zeros, '0', STR_PAD_LEFT);
        }));

        // Funciones
        $this->provider->addFunction(new \Twig\TwigFunction('user',       ['Core\\Auth', 'getCurrentUser']));
        $this->provider->addFunction(new \Twig\TwigFunction('isAdmin',    ['App\\Middlewares\\RoleMiddleware', 'isAdmin']));
        $this->provider->addFunction(new \Twig\TwigFunction('isSeller',   ['App\\Middlewares\\RoleMiddleware', 'isSeller']));
        $this->provider->addFunction(new \Twig\TwigFunction('isAnalyst',  ['App\\Middlewares\\RoleMiddleware', 'isAnalyst']));
        $this->provider->addFunction(new \Twig\TwigFunction('csrf_token', ['Core\\Csrf', 'token']));
    }

    protected function render(string $view, array $data = []) : string {
        // Detecta la ruta activa a partir de la URI actual
        // Ejemplo: /home → 'home', /inventario/nuevo → 'inventario'
        $segments = explode('/', trim(_CURRENT_URI_, '/'));
        $data['_route'] = $segments[0] ?? '';

        // Stock bajo — se renueva cada 2 minutos (badge en menú)
        $stockBajoCount = CacheService::remember('stock_bajo_count', 120, function () {
            try {
                return Producto::activos()->stockBajo()->count();
            } catch (\Exception $e) {
                return 0;
            }
        });

        // Configuración global — se renueva cada 5 minutos, se invalida al guardar
        $cfg = CacheService::remember('global_config', 300, function () {
            $moneda = Configuracion::get('moneda', 'MXN');
            return [
                'moneda'         => $moneda,
                'simbolo'        => $moneda === 'USD' ? 'USD $' : '$',
                'negocio_nombre' => Configuracion::get('negocio_nombre', 'Supermercado Web'),
                'negocio_tel'    => Configuracion::get('negocio_telefono'),
                'ticket_mensaje' => Configuracion::get('ticket_mensaje', '¡Gracias por su compra!'),
                'negocio_logo'   => Configuracion::get('negocio_logo', 'logo_super.png'),
            ];
        });

        $data['_cfg'] = array_merge($cfg, ['stock_bajo_total' => $stockBajoCount]);

        // Sesión del cliente para la tienda
        $data['cliente_session'] = !empty($_SESSION['cliente_id']) ? (object)[
            'id'     => $_SESSION['cliente_id'],
            'nombre' => $_SESSION['cliente_nombre'],
            'email'  => $_SESSION['cliente_email'],
        ] : null;


        return $this->provider->render($view, $data);
    }
}