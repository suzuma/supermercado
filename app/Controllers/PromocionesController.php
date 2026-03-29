<?php
namespace App\Controllers;

use App\Models\Promocion;
use App\Repositories\{PromocionRepository, ProductoRepository};
use Core\{Controller, Log};

class PromocionesController extends Controller
{
    private $repo;
    private $productoRepo;

    public function __construct()
    {
        parent::__construct();
        $this->repo         = new PromocionRepository();
        $this->productoRepo = new ProductoRepository();
    }

    // ── Listado ───────────────────────────────────────────────
    public function getIndex()
    {
        $promociones = $this->repo->listar();

        return $this->render('promociones/index.twig', [
            'title'       => 'Promociones',
            'promociones' => $promociones,
            'hoy'         => date('Y-m-d'),
        ]);
    }

    // ── Formulario ────────────────────────────────────────────
    public function getFormulario(int $id = 0)
    {
        $model     = $id ? $this->repo->obtener($id) : new Promocion();
        $productos = $this->productoRepo->listar(1, 200)['datos'];

        return $this->render('promociones/formulario.twig', [
            'title'     => $id ? 'Editar promoción' : 'Nueva promoción',
            'model'     => $model,
            'productos' => $productos,
        ]);
    }

    // ── Guardar ───────────────────────────────────────────────
    public function postGuardar()
    {
        $rh = new \App\Helpers\ResponseHelper();

        $nombre      = trim($_POST['nombre'] ?? '');
        $tipo        = $_POST['tipo'] ?? 'porcentaje';
        $fechaInicio = $_POST['fecha_inicio'] ?? date('Y-m-d');
        $fechaFin    = $_POST['fecha_fin']    ?? date('Y-m-d');

        if (empty($nombre)) {
            echo json_encode($rh->setResponse(false, 'El nombre de la promoción es requerido'));
            return;
        }

        $tiposValidos = ['porcentaje', 'precio_fijo', '2x1', 'cantidad_minima'];
        if (!in_array($tipo, $tiposValidos, true)) {
            echo json_encode($rh->setResponse(false, 'Tipo de promoción no válido'));
            return;
        }

        if (!strtotime($fechaInicio) || !strtotime($fechaFin)) {
            echo json_encode($rh->setResponse(false, 'Las fechas no tienen un formato válido'));
            return;
        }

        if ($fechaInicio > $fechaFin) {
            echo json_encode($rh->setResponse(false, 'La fecha de inicio no puede ser posterior a la fecha de fin'));
            return;
        }

        $model               = new Promocion();
        $model->id           = $_POST['id'] ?? null;
        $model->producto_id  = $_POST['producto_id'] ?? null;
        $model->nombre       = $nombre;
        $model->tipo         = $tipo;
        $model->valor        = (float)($_POST['valor'] ?? 0);
        $model->cantidad_min = (int)($_POST['cantidad_min'] ?? 1);
        $model->fecha_inicio = $fechaInicio;
        $model->fecha_fin    = $fechaFin;

        $rh = $this->repo->guardar($model);

        if ($rh->response) {
            $rh->href = 'promociones';
        }

        echo json_encode($rh);
    }

    // ── Desactivar ────────────────────────────────────────────
    public function postDesactivar()
    {
        $rh = $this->repo->desactivar((int)$_POST['id']);
        echo json_encode($rh);
    }

    // ── API: obtener promoción de un producto (para caja) ─────
    public function getPromoProducto(int $productoId)
    {
        $promo = $this->repo->obtenerDeProducto($productoId);

        header('Content-Type: application/json');
        echo json_encode($promo ? [
            'tiene_promo'  => true,
            'id'           => $promo->id,
            'nombre'       => $promo->nombre,
            'tipo'         => $promo->tipo,
            'valor'        => $promo->valor,
            'cantidad_min' => $promo->cantidad_min,
        ] : ['tiene_promo' => false]);
    }

    // ── API: calcular precio con promoción (para caja) ────────
    public function postCalcular()
    {
        $productoId = (int)($_POST['producto_id'] ?? 0);
        $cantidad   = (int)($_POST['cantidad'] ?? 1);
        $precio     = (float)($_POST['precio'] ?? 0);

        $promo = $this->repo->obtenerDeProducto($productoId);

        header('Content-Type: application/json');
        if ($promo) {
            echo json_encode($promo->calcularPrecio($precio, $cantidad));
        } else {
            echo json_encode([
                'precio_original' => $precio,
                'precio_final'    => $precio,
                'subtotal'        => round($precio * $cantidad, 2),
                'ahorro'          => 0,
                'descripcion'     => '',
                'tiene_promo'     => false,
            ]);
        }
    }
}