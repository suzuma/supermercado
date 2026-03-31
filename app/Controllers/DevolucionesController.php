<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Repositories\DevolucionesRepository;
use Core\{Controller, Log};

class DevolucionesController extends Controller
{
    private $devRepo;

    public function __construct()
    {
        parent::__construct();
        $this->devRepo = new DevolucionesRepository();
    }

    public function getIndex()
    {
        return $this->render('devoluciones/index.twig', ['title' => 'Nueva devolución']);
    }

    public function getHistorial()
    {
        $pagina = max(1, (int)($_GET['pagina'] ?? 1));
        $fecha  = $_GET['fecha'] ?? date('Y-m-d');

        $data          = $this->devRepo->listar($pagina, 20, $fecha);
        $data['title'] = 'Historial de devoluciones';
        $data['fecha'] = $fecha;

        return $this->render('devoluciones/historial.twig', $data);
    }

    public function getRecibo(int $id)
    {
        $devolucion = $this->devRepo->obtener($id);

        if (!$devolucion) {
            http_response_code(404);
            return $this->render('errors/404.php', []);
        }

        return $this->render('devoluciones/recibo.twig', [
            'title'      => 'Recibo de devolución #' . str_pad((string) $id, 5, '0', STR_PAD_LEFT),
            'devolucion' => $devolucion,
        ]);
    }

    public function postBuscarVenta()
    {
        $rh = new ResponseHelper();
        $id = (int)($_POST['venta_id'] ?? 0);

        if ($id <= 0) {
            echo json_encode($rh->setResponse(false, 'Ingresa un folio válido'));
            return;
        }

        $venta = $this->devRepo->buscarVenta($id);

        if (!$venta) {
            echo json_encode($rh->setResponse(false, 'Venta no encontrada o ya está cancelada'));
            return;
        }

        // Verificar que quede algo por devolver
        $hayDisponible = false;
        foreach ($venta->detalles as $d) {
            if ((float)$d->cantidad - (float)$d->cantidad_devuelta > 0) {
                $hayDisponible = true;
                break;
            }
        }

        if (!$hayDisponible) {
            echo json_encode($rh->setResponse(false, 'Todos los artículos de esta venta ya fueron devueltos'));
            return;
        }

        $rh->setResponse(true, 'Venta encontrada');
        $rh->result = $venta;
        echo json_encode($rh);
    }

    public function postRegistrar()
    {
        $rh = new ResponseHelper();

        $ventaId = (int)($_POST['venta_id'] ?? 0);
        $motivo  = trim($_POST['motivo']   ?? '');
        $items   = json_decode($_POST['items'] ?? '[]', true);

        if ($ventaId <= 0) {
            echo json_encode($rh->setResponse(false, 'Folio de venta inválido'));
            return;
        }

        if (empty($items) || !is_array($items)) {
            echo json_encode($rh->setResponse(false, 'Selecciona al menos un artículo para devolver'));
            return;
        }

        foreach ($items as $item) {
            if (empty($item['venta_detalle_id']) || !isset($item['cantidad']) || (float)$item['cantidad'] <= 0) {
                echo json_encode($rh->setResponse(false, 'Datos de artículos inválidos'));
                return;
            }
        }

        $resultado = $this->devRepo->registrar($ventaId, $items, $motivo);
        echo json_encode($resultado);
    }
}