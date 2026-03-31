<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Repositories\PedidoRepository;
use Core\{Auth, Controller};

class RepartidorController extends Controller
{
    private PedidoRepository $pedidoRepo;

    public function __construct()
    {
        parent::__construct();
        $this->pedidoRepo = new PedidoRepository();
    }

    public function getIndex(): string
    {
        $usuario = Auth::getCurrentUser();
        $pedidos = $this->pedidoRepo->listarDeRepartidor($usuario->id);

        return $this->render('repartidor/index.twig', [
            'title'   => 'Mis entregas',
            'menu'    => false,
            'pedidos' => $pedidos,
        ]);
    }

    public function postEntregar(): void
    {
        $rh      = new ResponseHelper();
        $usuario = Auth::getCurrentUser();
        $id      = (int)($_POST['id']     ?? 0);
        $estado  = trim($_POST['estado']  ?? '');

        if (!in_array($estado, ['enviado', 'entregado'], true)) {
            echo json_encode($rh->setResponse(false, 'Estado no válido'));
            return;
        }

        // Verificar que el pedido esté asignado a este repartidor
        $pedido = \App\Models\Pedido::find($id);

        if (!$pedido || (int)$pedido->usuario_id !== $usuario->id) {
            echo json_encode($rh->setResponse(false, 'Pedido no encontrado o no asignado'));
            return;
        }

        echo json_encode($this->pedidoRepo->cambiarEstado($id, $estado));
    }
}