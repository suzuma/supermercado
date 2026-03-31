<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Repositories\{VentaRepository, ProductoRepository, ClienteRepository, PromocionRepository};
use Core\{Controller, Log};

class VentasController extends Controller
{
    private $ventaRepo;
    private $productoRepo;
    private $clienteRepo;
    private $promoRepo;

    public function __construct()
    {
        parent::__construct();
        $this->ventaRepo    = new VentaRepository();
        $this->productoRepo = new ProductoRepository();
        $this->clienteRepo  = new ClienteRepository();
        $this->promoRepo    = new PromocionRepository();
    }

    public function getIndex()
    {
        return $this->render('ventas/caja.twig', ['title' => 'Punto de venta']);
    }

    public function getHistorial()
    {
        $pagina = (int)($_GET['pagina'] ?? 1);
        $fecha  = $_GET['fecha'] ?? null;
        $resultado = $this->ventaRepo->listar($pagina, 20, $fecha ?: null);

        return $this->render('ventas/historial.twig', [
            'title'       => 'Historial de ventas',
            'datos'       => $resultado['datos'],
            'total'       => $resultado['total'],
            'pagina'      => $resultado['pagina'],
            'total_pages' => $resultado['total_pages'],
            'fecha'       => $fecha ?? date('Y-m-d'),
        ]);
    }

    public function getTicket(int $id)
    {
        $venta = $this->ventaRepo->obtener($id);
        return $this->render('ventas/ticket.twig', [
            'title' => 'Ticket #' . str_pad($id, 5, '0', STR_PAD_LEFT),
            'venta' => $venta,
            'menu'  => false,
        ]);
    }

    // ── Buscar productos + adjuntar promoción vigente ─────────
    public function postBuscarProducto()
    {
        $termino = $_POST['termino'] ?? '';
        $tipo    = $_POST['tipo'] ?? 'nombre';

        if ($tipo === 'codigo') {
            $producto  = $this->productoRepo->buscarPorCodigo($termino);
            $resultado = $producto ? [$producto] : [];
        } else {
            $resultado = $this->productoRepo->listar(1, 10, null, $termino)['datos'];
        }

        $resultado = collect($resultado)->map(function ($producto) {
            $promo       = $this->promoRepo->obtenerDeProducto($producto->id);
            $productoArr = $producto->toArray();

            if ($promo) {
                $calculo = $promo->calcularPrecio($producto->precio_venta, 1);
                $productoArr['promo'] = [
                    'id'           => $promo->id,
                    'nombre'       => $promo->nombre,
                    'tipo'         => $promo->tipo,
                    'valor'        => $promo->valor,
                    'cantidad_min' => $promo->cantidad_min,
                    'precio_final' => $calculo['precio_final'],
                    'descripcion'  => $calculo['descripcion'],
                ];
            } else {
                $productoArr['promo'] = null;
            }

            return $productoArr;
        })->values()->toArray();

        header('Content-Type: application/json');
        echo json_encode($resultado);
    }

    public function postBuscarCliente()
    {
        $termino   = $_POST['termino'] ?? '';
        $resultado = $this->clienteRepo->buscar($termino);
        header('Content-Type: application/json');
        echo json_encode($resultado->values());
    }

    // ── Registrar venta aplicando promociones ─────────────────
    public function postRegistrar()
    {
        $rh    = new ResponseHelper();
        $items = json_decode($_POST['items'] ?? '[]', true);

        if (empty($items) || !is_array($items)) {
            echo json_encode($rh->setResponse(false, 'El carrito está vacío'));
            return;
        }

        foreach ($items as $item) {
            if (empty($item['id'])     || (int)$item['id'] <= 0
                || empty($item['cantidad']) || (float)$item['cantidad'] <= 0
                || !isset($item['precio'])  || (float)$item['precio'] < 0) {
                echo json_encode($rh->setResponse(false, 'Los datos del carrito son inválidos'));
                return;
            }
        }

        $descuento = (float)($_POST['descuento'] ?? 0);
        if ($descuento < 0 || $descuento > 100) {
            echo json_encode($rh->setResponse(false, 'El descuento debe estar entre 0 y 100'));
            return;
        }

        $tiposPagoValidos = ['efectivo', 'tarjeta', 'transferencia'];
        $tipoPago = $_POST['tipo_pago'] ?? 'efectivo';
        if (!in_array($tipoPago, $tiposPagoValidos, true)) {
            $tipoPago = 'efectivo';
        }

        $clienteId = !empty($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : null;

        $subtotalBruto = 0;
        $itemsConPromo = [];

        foreach ($items as $item) {
            $promo = $this->promoRepo->obtenerDeProducto((int)$item['id']);

            if ($promo) {
                $calculo              = $promo->calcularPrecio((float)$item['precio'], (int)$item['cantidad']);
                $subtotalItem         = $calculo['subtotal'];
                $item['precio_final'] = $calculo['precio_final'];
                $item['ahorro']       = $calculo['ahorro'];
                $item['promo_desc']   = $calculo['descripcion'];
                $item['promo_id']     = $promo->id;
            } else {
                $subtotalItem         = round($item['precio'] * $item['cantidad'], 2);
                $item['precio_final'] = $item['precio'];
                $item['ahorro']       = 0;
                $item['promo_desc']   = '';
                $item['promo_id']     = null;
            }

            $item['subtotal'] = $subtotalItem;
            $subtotalBruto   += $subtotalItem;
            $itemsConPromo[]  = $item;
        }

        $montoDesc = round($subtotalBruto * $descuento / 100, 2);
        $total     = round($subtotalBruto - $montoDesc, 2);

        $rh = $this->ventaRepo->registrar(
            $itemsConPromo,
            $subtotalBruto,
            $descuento,
            $total,
            $tipoPago,
            $clienteId
        );

        echo json_encode($rh);
    }

    public function postCancelar()
    {
        $rh = $this->ventaRepo->cancelar((int)$_POST['id']);
        header('Content-Type: application/json');
        echo json_encode($rh);
    }
}