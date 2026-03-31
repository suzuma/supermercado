<?php
declare(strict_types=1);

namespace App\Controllers;

use Core\{Auth, Controller};
use App\Repositories\{
    VentaRepository,
    ProductoRepository,
    EmpleadoRepository,
    PedidoRepository,
    DevolucionesRepository
};

class HomeController extends Controller
{
    private $ventaRepo;
    private $productoRepo;
    private $empleadoRepo;
    private $pedidoRepo;
    private $devRepo;

    public function __construct()
    {
        parent::__construct();
        $this->ventaRepo    = new VentaRepository();
        $this->productoRepo = new ProductoRepository();
        $this->empleadoRepo = new EmpleadoRepository();
        $this->pedidoRepo   = new PedidoRepository();
        $this->devRepo      = new DevolucionesRepository();
    }

    public function getIndex()
    {
        // Métricas principales
        $ventasSemana       = $this->ventaRepo->ventasSemana();
        $devolucionesSemana = $this->devRepo->devolucionesSemana();

        // Alertas de stock — solo primeras 8 para el dashboard
        $alertasStock = $this->productoRepo->alertasStockBajo()->take(8)->map(function($p) {
            return [
                'nombre'    => $p->nombre,
                'categoria' => $p->categoria->nombre ?? '—',
                'stock'     => $p->stock,
            ];
        })->values()->toArray();

        // Últimas ventas del día
        $ultimasVentas = $this->ventaRepo->ultimasVentas(8);

        // Pedidos recientes
        $pedidosRecientes = $this->pedidoRepo->recientes(8)->map(function($p) {
            return [
                'id'      => $p->id,
                'cliente' => $p->cliente ? $p->cliente->nombre . ' ' . $p->cliente->apellido : 'Cliente',
                'estado'  => $p->estado,
                'total'   => $p->total,
            ];
        })->values()->toArray();

        return $this->render('home/index.twig', [
            'title' => 'Dashboard',

            // Métricas tarjetas
            'ventas_dia'         => $this->ventaRepo->totalDia(),
            'total_ventas_dia'   => $this->ventaRepo->cantidadDia(),
            'total_productos'    => $this->productoRepo->totalProductos(),
            'stock_bajo'         => $this->productoRepo->totalStockBajo(),
            'empleados_activos'  => $this->empleadoRepo->totalActivos(),
            'total_empleados'    => $this->empleadoRepo->total(),
            'pedidos_pendientes' => $this->pedidoRepo->totalPendientes(),

            // Métricas devoluciones
            'devoluciones_dia'       => $this->devRepo->totalDia(),
            'total_devoluciones_dia' => $this->devRepo->cantidadDia(),

            // Métricas adicionales
            'promedio_ticket' => $this->ventaRepo->promedioTicketDia(),
            'producto_top'    => $this->ventaRepo->productoTopDia(),
            'cajero_top'      => $this->ventaRepo->cajeroTopDia(),

            // Gráfica
            'ventas_labels'        => $ventasSemana['labels'],
            'ventas_datos'         => $ventasSemana['datos'],
            'devoluciones_datos'   => $devolucionesSemana['datos'],

            // Listas
            'alertas_stock'     => $alertasStock,
            'ultimas_ventas'    => $ultimasVentas,
            'pedidos_recientes' => $pedidosRecientes,
        ]);
    }
}