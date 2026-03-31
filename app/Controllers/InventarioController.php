<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Producto;
use App\Repositories\{ProductoRepository, CategoriaRepository, ProveedorRepository};
use App\Validations\ProductoValidation;
use Core\{Controller, Log};

class InventarioController extends Controller
{
    private $productoRepo;
    private $categoriaRepo;
    private $proveedorRepo;

    public function __construct()
    {
        parent::__construct();
        $this->productoRepo  = new ProductoRepository();
        $this->categoriaRepo = new CategoriaRepository();
        $this->proveedorRepo = new ProveedorRepository();
    }

    // ── Listado principal ─────────────────────────────────────
    public function getIndex()
    {
        $pagina    = (int)($_GET['pagina'] ?? 1);
        $categoria = $_GET['categoria'] ?? null;
        $busqueda  = $_GET['busqueda'] ?? null;

        $resultado        = $this->productoRepo->listar($pagina, 15, $categoria ?: null, $busqueda ?: null);
        $categorias       = $this->categoriaRepo->listar();
        $alertas          = $this->productoRepo->alertasStockBajo();
        $alertasCaducidad = $this->productoRepo->alertasCaducidad();

        return $this->render('inventario/index.twig', [
            'title'             => 'Inventario',
            'datos'             => $resultado['datos'],
            'total'             => $resultado['total'],
            'pagina'            => $resultado['pagina'],
            'total_pages'       => $resultado['total_pages'],
            'categorias'        => $categorias,
            'alertas'           => $alertas,
            'alertas_caducidad' => $alertasCaducidad,
            'cat_seleccionada'  => $categoria,
            'busqueda'          => $busqueda,
        ]);
    }

    // ── Formulario nuevo / editar ─────────────────────────────
    public function getFormulario(int $id = 0)
    {
        $model       = $id ? $this->productoRepo->obtener($id) : new Producto();
        $categorias  = $this->categoriaRepo->listar();
        $proveedores = $this->proveedorRepo->listar();

        return $this->render('inventario/formulario.twig', [
            'title'       => $id ? 'Editar producto' : 'Nuevo producto',
            'model'       => $model,
            'categorias'  => $categorias,
            'proveedores' => $proveedores,
        ]);
    }

    // ── Guardar ───────────────────────────────────────────────
    public function postGuardar()
    {
        ProductoValidation::validar($_POST);

        $model                 = new Producto();
        $model->id             = $_POST['id'] ?? null;
        $model->categoria_id   = $_POST['categoria_id'];
        $model->proveedor_id   = $_POST['proveedor_id'] ?? null;
        $model->nombre         = $_POST['nombre'];
        $model->descripcion    = $_POST['descripcion'] ?? '';
        $model->precio_compra  = $_POST['precio_compra'];
        $model->precio_venta   = $_POST['precio_venta'];
        $model->stock          = $_POST['stock'];
        $model->stock_minimo   = $_POST['stock_minimo'];
        $model->codigo_barras  = $_POST['codigo_barras'] ?? null;
        $model->venta_por_peso  = !empty($_POST['venta_por_peso']) ? 1 : 0;
        $model->unidad_peso     = $model->venta_por_peso ? ($_POST['unidad_peso'] ?? 'kg') : 'kg';
        $model->fecha_caducidad = !empty($_POST['fecha_caducidad']) ? $_POST['fecha_caducidad'] : null;

        $imagen = $_FILES['imagen'] ?? null;
        $rh     = $this->productoRepo->guardar($model, $imagen);

        if ($rh->response) {
            $rh->href = 'inventario';
        }

        echo json_encode($rh);
    }

    // ── Eliminar ──────────────────────────────────────────────
    public function postEliminar()
    {
        $rh = $this->productoRepo->eliminar((int)$_POST['id']);

        if ($rh->response) {
            $rh->href = 'inventario';
        }

        echo json_encode($rh);
    }

    // ── Buscar por código de barras (Ajax) ────────────────────
    public function postBuscarCodigo()
    {
        $rh       = new ResponseHelper();
        $producto = $this->productoRepo->buscarPorCodigo($_POST['codigo'] ?? '');

        if ($producto) {
            $rh->setResponse(true);
            $rh->result = $producto;
        } else {
            $rh->setResponse(false, 'Código no encontrado');
        }

        echo json_encode($rh);
    }

    // ── Auto-generar código de barras (Ajax) ──────────────────
    public function postGenerarCodigo()
    {
        $rh = new ResponseHelper();
        $id = (int)($_POST['id'] ?? 0);

        if (!$id) {
            echo json_encode($rh->setResponse(false, 'ID inválido'));
            return;
        }

        $rh = $this->productoRepo->generarCodigo($id);
        echo json_encode($rh);
    }

    // ── Imprimir etiquetas ────────────────────────────────────
    // GET /inventario/imprimirEtiquetas?ids=1,2,3
    public function getImprimirEtiquetas()
    {
        $raw  = trim($_GET['ids'] ?? '');
        $ids  = array_filter(array_map('intval', explode(',', $raw)));

        if (empty($ids)) {
            return $this->render('inventario/etiquetas.twig', [
                'title'    => 'Etiquetas',
                'menu'     => false,
                'productos' => collect(),
            ]);
        }

        $productos = $this->productoRepo->obtenerMultiples($ids);

        return $this->render('inventario/etiquetas.twig', [
            'title'     => 'Imprimir etiquetas',
            'menu'      => false,
            'productos' => $productos,
        ]);
    }
}