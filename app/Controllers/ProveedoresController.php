<?php
namespace App\Controllers;

use App\Models\Proveedor;
use App\Repositories\{ProveedorRepository, ProductoRepository};
use App\Validations\ProveedorValidation;
use Core\{Controller, Log};

class ProveedoresController extends Controller
{
    private $repo;
    private $productoRepo;

    public function __construct()
    {
        parent::__construct();
        $this->repo         = new ProveedorRepository();
        $this->productoRepo = new ProductoRepository();
    }

    // ── Listado ───────────────────────────────────────────────
    public function getIndex()
    {
        $pagina   = (int)($_GET['pagina'] ?? 1);
        $busqueda = $_GET['busqueda'] ?? null;

        $resultado = $this->repo->listar($pagina, 15, $busqueda ?: null);

        return $this->render('proveedores/index.twig', [
            'title'       => 'Proveedores',
            'datos'       => $resultado['datos'],
            'total'       => $resultado['total'],
            'pagina'      => $resultado['pagina'],
            'total_pages' => $resultado['total_pages'],
            'busqueda'    => $busqueda,
        ]);
    }

    // ── Formulario nuevo / editar ─────────────────────────────
    public function getFormulario(int $id = 0)
    {
        $model = $id ? $this->repo->obtener($id) : new Proveedor();

        return $this->render('proveedores/formulario.twig', [
            'title' => $id ? 'Editar proveedor' : 'Nuevo proveedor',
            'model' => $model,
        ]);
    }

    // ── Guardar ───────────────────────────────────────────────
    public function postGuardar()
    {
        ProveedorValidation::validar($_POST);

        $model            = new Proveedor();
        $model->id        = $_POST['id'] ?? null;
        $model->nombre    = $_POST['nombre'];
        $model->contacto  = $_POST['contacto'] ?? '';
        $model->telefono  = $_POST['telefono'] ?? '';
        $model->email     = $_POST['email'] ?? '';
        $model->direccion = $_POST['direccion'] ?? '';
        $model->rfc       = $_POST['rfc'] ?? '';

        $rh = $this->repo->guardar($model);

        if ($rh->response) {
            $rh->href = 'proveedores';
        }

        echo json_encode($rh);
    }

    // ── Desactivar ────────────────────────────────────────────
    public function postDesactivar()
    {
        $rh = $this->repo->desactivar((int)$_POST['id']);
        echo json_encode($rh);
    }

    // ── Órdenes de un proveedor ───────────────────────────────
    public function getOrdenes(int $id)
    {
        $proveedor = $this->repo->obtener($id);
        $ordenes   = $this->repo->ordenesDeProveedor($id);

        return $this->render('proveedores/ordenes.twig', [
            'title'      => 'Órdenes — ' . $proveedor->nombre,
            'proveedor'  => $proveedor,
            'ordenes'    => $ordenes,
        ]);
    }

    // ── Nueva orden de compra ─────────────────────────────────
    public function getNuevaOrden(int $id)
    {
        $proveedor = $this->repo->obtener($id);
        $productos = $this->productoRepo->listar(1, 200)['datos'];

        return $this->render('proveedores/nueva_orden.twig', [
            'title'      => 'Nueva orden de compra',
            'proveedor'  => $proveedor,
            'productos'  => $productos,
        ]);
    }

    // ── Guardar orden (Ajax) ──────────────────────────────────
    public function postGuardarOrden()
    {
        $rh = $this->repo->crearOrden($_POST);

        if ($rh->response) {
            $rh->href = 'proveedores/ordenes/' . $_POST['proveedor_id'];
        }

        echo json_encode($rh);
    }

    // ── Cambiar estado de orden (Ajax) ────────────────────────
    public function postEstadoOrden()
    {
        $rh     = new \App\Helpers\ResponseHelper();
        $estado = $_POST['estado'] ?? '';

        $estadosValidos = ['pendiente', 'recibida', 'cancelada'];
        if (!in_array($estado, $estadosValidos, true)) {
            echo json_encode($rh->setResponse(false, 'Estado de orden no válido'));
            return;
        }

        $rh = $this->repo->cambiarEstadoOrden(
            (int)$_POST['id'],
            $estado
        );
        echo json_encode($rh);
    }

    // ── Detalle de orden ──────────────────────────────────────
    public function getDetalleOrden(int $id)
    {
        $orden = $this->repo->obtenerOrden($id);

        return $this->render('proveedores/detalle_orden.twig', [
            'title' => 'Orden #' . str_pad($id, 5, '0', STR_PAD_LEFT),
            'orden' => $orden,
        ]);
    }
}