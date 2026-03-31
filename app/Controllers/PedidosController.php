<?php
declare(strict_types=1);
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
    public function getIndex(): string
    {
        $pagina     = (int)($_GET['pagina'] ?? 1);
        $estado     = $_GET['estado']      ?? null;
        $fechaDesde = $_GET['fecha_desde'] ?? null;
        $fechaHasta = $_GET['fecha_hasta'] ?? null;

        $resultado    = $this->pedidoRepo->listar($pagina, 20, $estado ?: null, $fechaDesde ?: null, $fechaHasta ?: null);
        $repartidores = $this->empleadoRepo->listarRepartidores();

        return $this->render('pedidos/index.twig', [
            'title'        => 'Pedidos en línea',
            'datos'        => $resultado['datos'],
            'total'        => $resultado['total'],
            'pagina'       => $resultado['pagina'],
            'total_pages'  => $resultado['total_pages'],
            'estado'       => $estado,
            'fecha_desde'  => $fechaDesde,
            'fecha_hasta'  => $fechaHasta,
            'repartidores' => $repartidores,
        ]);
    }

    // ── Vista kanban ──────────────────────────────────────────
    public function getKanban(): string
    {
        $grupos       = $this->pedidoRepo->listarPorEstado();
        $repartidores = $this->empleadoRepo->listarRepartidores();

        return $this->render('pedidos/kanban.twig', [
            'title'        => 'Pedidos — Kanban',
            'grupos'       => $grupos,
            'repartidores' => $repartidores,
        ]);
    }

    // ── Exportar CSV ──────────────────────────────────────────
    public function getExportar(): void
    {
        $estado     = $_GET['estado']      ?? null;
        $fechaDesde = $_GET['fecha_desde'] ?? null;
        $fechaHasta = $_GET['fecha_hasta'] ?? null;

        $pedidos = $this->pedidoRepo->listarParaExportar($estado ?: null, $fechaDesde ?: null, $fechaHasta ?: null);

        $nombre = 'pedidos_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $nombre . '"');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));  // BOM UTF-8 para Excel

        fputcsv($out, ['Folio', 'Fecha', 'Cliente', 'Email', 'Teléfono', 'Dirección', 'Repartidor', 'Estado', 'Total']);

        foreach ($pedidos as $p) {
            fputcsv($out, [
                '#' . str_pad((string)$p->id, 5, '0', STR_PAD_LEFT),
                $p->created_at->format('d/m/Y H:i'),
                $p->cliente ? $p->cliente->nombre . ' ' . $p->cliente->apellido : '',
                $p->cliente->email    ?? '',
                $p->cliente->telefono ?? '',
                $p->direccion_entrega ?? '',
                $p->usuario ? $p->usuario->nombre . ' ' . $p->usuario->apellido : '',
                $p->estado,
                number_format((float)$p->total, 2),
            ]);
        }

        fclose($out);
    }

    // ── Detalle ───────────────────────────────────────────────
    public function getDetalle(int $id): string
    {
        $pedido       = $this->pedidoRepo->obtener($id);
        $repartidores = $this->empleadoRepo->listarRepartidores();

        return $this->render('pedidos/detalle.twig', [
            'title'        => 'Pedido #' . str_pad((string)$id, 5, '0', STR_PAD_LEFT),
            'pedido'       => $pedido,
            'repartidores' => $repartidores,
        ]);
    }

    // ── Orden de entrega (impresión) ──────────────────────────
    public function getOrden(int $id): string
    {
        $pedido = $this->pedidoRepo->obtener($id);

        return $this->render('pedidos/orden.twig', [
            'title'  => 'Orden de entrega #' . str_pad((string)$id, 5, '0', STR_PAD_LEFT),
            'pedido' => $pedido,
            'menu'   => false,
        ]);
    }

    // ── Cambiar estado (Ajax) ─────────────────────────────────
    public function postEstado(): void
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
    public function postAsignar(): void
    {
        $rh = $this->pedidoRepo->asignarRepartidor(
            (int)$_POST['id'],
            (int)$_POST['usuario_id']
        );
        echo json_encode($rh);
    }

    // ── Cancelar (Ajax) ───────────────────────────────────────
    public function postCancelar(): void
    {
        $rh = $this->pedidoRepo->cambiarEstado(
            (int)$_POST['id'],
            'cancelado'
        );
        echo json_encode($rh);
    }
}