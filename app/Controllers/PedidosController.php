<?php
namespace App\Controllers;

use App\Repositories\{PedidoRepository, EmpleadoRepository};
use Core\{Controller, Log};

class PedidosController extends Controller
{
    private $pedidoRepo;
    private $empleadoRepo;

    public function __construct()
    {
        parent::__construct();
        $this->pedidoRepo   = new PedidoRepository();
        $this->empleadoRepo = new EmpleadoRepository();
    }

    // ── Listado ───────────────────────────────────────────────
    public function getIndex()
    {
        $pagina = (int)($_GET['pagina'] ?? 1);
        $estado = $_GET['estado'] ?? null;

        $resultado    = $this->pedidoRepo->listar($pagina, 20, $estado ?: null);
        $repartidores = $this->empleadoRepo->listarRepartidores();

        return $this->render('pedidos/index.twig', [
            'title'        => 'Pedidos en línea',
            'datos'        => $resultado['datos'],
            'total'        => $resultado['total'],
            'pagina'       => $resultado['pagina'],
            'total_pages'  => $resultado['total_pages'],
            'estado'       => $estado,
            'repartidores' => $repartidores,
        ]);
    }

    // ── Detalle ───────────────────────────────────────────────
    public function getDetalle(int $id)
    {
        $pedido       = $this->pedidoRepo->obtener($id);
        $repartidores = $this->empleadoRepo->listarRepartidores();

        return $this->render('pedidos/detalle.twig', [
            'title'        => 'Pedido #' . str_pad($id, 5, '0', STR_PAD_LEFT),
            'pedido'       => $pedido,
            'repartidores' => $repartidores,
        ]);
    }

    // ── Orden de entrega (impresión) ──────────────────────────
    public function getOrden(int $id)
    {
        $pedido = $this->pedidoRepo->obtener($id);

        return $this->render('pedidos/orden.twig', [
            'title'  => 'Orden de entrega #' . str_pad($id, 5, '0', STR_PAD_LEFT),
            'pedido' => $pedido,
            'menu'   => false,
        ]);
    }

    // ── Cambiar estado (Ajax) ─────────────────────────────────
    public function postEstado()
    {
        $rh     = new \App\Helpers\ResponseHelper();
        $estado = $_POST['estado'] ?? '';

        $estadosValidos = ['pendiente', 'confirmado', 'enviado', 'entregado', 'cancelado'];
        if (!in_array($estado, $estadosValidos, true)) {
            echo json_encode($rh->setResponse(false, 'Estado no válido'));
            return;
        }

        $rh = $this->pedidoRepo->cambiarEstado(
            (int)$_POST['id'],
            $estado
        );
        echo json_encode($rh);
    }

    // ── Asignar repartidor (Ajax) ─────────────────────────────
    public function postAsignar()
    {
        $rh = $this->pedidoRepo->asignarRepartidor(
            (int)$_POST['id'],
            (int)$_POST['usuario_id']
        );
        echo json_encode($rh);
    }

    // ── Cancelar (Ajax) ───────────────────────────────────────
    public function postCancelar()
    {
        $rh = $this->pedidoRepo->cambiarEstado(
            (int)$_POST['id'],
            'cancelado'
        );
        echo json_encode($rh);
    }
}