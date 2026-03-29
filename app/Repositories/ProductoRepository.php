<?php
/*
    autor: Noe Cazarez Camargo
    fecha: 2019-06-24
    descripcion: REPOSITORIO DE PRODUCTOS
*/
namespace App\Repositories;

use App\Helpers\ResponseHelper;
use App\Models\Producto;
use Core\Log;
use Exception;
use Illuminate\Database\Eloquent\Collection;

class ProductoRepository
{
    private $model;

    public function __construct()
    {
        $this->model = new Producto();
    }

    // ── Listar con paginación ─────────────────────────────────
    public function listar(int $pagina = 1, int $limite = 15, ?int $categoria_id = null, ?string $busqueda = null): array
    {
        try {
            $query = $this->model
                ->with(['categoria', 'proveedor'])
                ->activos();

            if ($categoria_id) {
                $query->where('categoria_id', $categoria_id);
            }

            if ($busqueda) {
                $query->where(function ($q) use ($busqueda) {
                    $q->where('nombre', 'like', "%$busqueda%")
                        ->orWhere('codigo_barras', 'like', "%$busqueda%");
                });
            }

            $total  = $query->count();
            $offset = ($pagina - 1) * $limite;

            $datos = $query
                ->orderBy('nombre')
                ->skip($offset)
                ->take($limite)
                ->get();

            return [
                'datos'       => $datos,
                'total'       => $total,
                'pagina'      => $pagina,
                'limite'      => $limite,
                'total_pages' => ceil($total / $limite),
            ];
        } catch (Exception $e) {
            Log::error(ProductoRepository::class, $e->getMessage());
            return ['datos' => collect(), 'total' => 0, 'pagina' => 1, 'limite' => $limite, 'total_pages' => 1];
        }
    }

    // ── Obtener uno ───────────────────────────────────────────
    public function obtener(int $id): Producto
    {
        $producto = new Producto();

        try {
            $producto = $this->model->with(['categoria', 'proveedor'])->findOrFail($id);
        } catch (Exception $e) {
            Log::error(ProductoRepository::class, $e->getMessage());
        }

        return $producto;
    }

    // ── Buscar por código de barras ───────────────────────────
    public function buscarPorCodigo(string $codigo): ?Producto
    {
        try {
            return $this->model->where('codigo_barras', $codigo)->first();
        } catch (Exception $e) {
            Log::error(ProductoRepository::class, $e->getMessage());
            return null;
        }
    }

    // ── Guardar (crear o actualizar) ──────────────────────────
    public function guardar(Producto $model, ?array $imagen = null): ResponseHelper
    {
        $rh = new ResponseHelper();

        try {
            $esNuevo = empty($model->id);

            $this->model->id           = $model->id;
            $this->model->categoria_id = $model->categoria_id;
            $this->model->proveedor_id = $model->proveedor_id ?: null;
            $this->model->nombre       = $model->nombre;
            $this->model->descripcion  = $model->descripcion;
            $this->model->precio_compra = $model->precio_compra;
            $this->model->precio_venta  = $model->precio_venta;
            $this->model->stock         = $model->stock;
            $this->model->stock_minimo  = $model->stock_minimo;
            $this->model->codigo_barras  = $model->codigo_barras ?: null;
            $this->model->venta_por_peso  = $model->venta_por_peso;
            $this->model->unidad_peso     = $model->unidad_peso;
            $this->model->fecha_caducidad = $model->fecha_caducidad ?: null;
            $this->model->activo          = 1;

            // Subir imagen si viene
            if ($imagen && $imagen['error'] === UPLOAD_ERR_OK) {
                $nombreImagen = $this->subirImagen($imagen);
                if ($nombreImagen) {
                    // Eliminar imagen anterior si existe
                    if (!$esNuevo && $this->model->imagen) {
                        $this->eliminarImagen($this->model->imagen);
                    }
                    $this->model->imagen = $nombreImagen;
                }
            }

            if (!$esNuevo) {
                $this->model->exists = true;
            }

            $this->model->save();
            $rh->setResponse(true, 'Producto guardado correctamente');
        } catch (Exception $e) {
            Log::error(ProductoRepository::class, $e->getMessage());
            $rh->setResponse(false, 'No se pudo guardar el producto');
        }

        return $rh;
    }

    // ── Eliminar (soft delete — desactiva) ────────────────────
    public function eliminar(int $id): ResponseHelper
    {
        $rh = new ResponseHelper();

        try {
            $producto = $this->model->findOrFail($id);
            $producto->activo = 0;
            $producto->exists = true;
            $producto->save();
            $rh->setResponse(true, 'Producto eliminado correctamente');
        } catch (Exception $e) {
            Log::error(ProductoRepository::class, $e->getMessage());
            $rh->setResponse(false, 'No se pudo eliminar el producto');
        }

        return $rh;
    }

    // ── Alertas de caducidad (vencidos + próximos 30 días) ────
    public function alertasCaducidad(int $dias = 30): Collection
    {
        try {
            return $this->model
                ->activos()
                ->proximosVencer($dias)
                ->orderBy('fecha_caducidad')
                ->get();
        } catch (Exception $e) {
            Log::error(ProductoRepository::class, $e->getMessage());
            return collect();
        }
    }

    // ── Alertas de stock bajo ─────────────────────────────────
    public function alertasStockBajo(): Collection
    {
        try {
            return $this->model
                ->with('categoria')
                ->activos()
                ->stockBajo()
                ->orderBy('stock')
                ->get();
        } catch (Exception $e) {
            Log::error(ProductoRepository::class, $e->getMessage());
            return collect();
        }
    }

    // ── Auto-generar código de barras ────────────────────────
    public function generarCodigo(int $id): ResponseHelper
    {
        $rh = new ResponseHelper();
        try {
            $producto = $this->model->findOrFail($id);

            if ($producto->codigo_barras) {
                $rh->setResponse(true, 'El producto ya tiene código');
                $rh->result = ['codigo' => $producto->codigo_barras];
                return $rh;
            }

            // Formato: SUZ + ID con ceros a la izquierda (12 chars total)
            $codigo = 'SUZ' . str_pad($id, 9, '0', STR_PAD_LEFT);

            $producto->codigo_barras = $codigo;
            $producto->exists        = true;
            $producto->save();

            $rh->setResponse(true, 'Código generado correctamente');
            $rh->result = ['codigo' => $codigo];
        } catch (Exception $e) {
            Log::error(ProductoRepository::class, $e->getMessage());
            $rh->setResponse(false, 'No se pudo generar el código');
        }
        return $rh;
    }

    // ── Obtener múltiples por IDs (para impresión) ────────────
    public function obtenerMultiples(array $ids): Collection
    {
        try {
            return $this->model->whereIn('id', $ids)->get();
        } catch (Exception $e) {
            Log::error(ProductoRepository::class, $e->getMessage());
            return collect();
        }
    }

    // ── Totales para el dashboard ─────────────────────────────
    public function totalProductos(): int
    {
        try {
            return $this->model->activos()->count();
        } catch (Exception $e) {
            return 0;
        }
    }

    public function totalStockBajo(): int
    {
        try {
            return $this->model->activos()->stockBajo()->count();
        } catch (Exception $e) {
            return 0;
        }
    }

    // ── Helpers de imagen ─────────────────────────────────────
    private function subirImagen(array $archivo): ?string
    {
        $extensionesPermitidas = ['jpg', 'jpeg', 'png', 'webp'];
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

        if (!in_array($extension, $extensionesPermitidas)) {
            return null;
        }

        if ($archivo['size'] > 2 * 1024 * 1024) { // 2MB máximo
            return null;
        }

        $nombre    = uniqid('producto_') . '.' . $extension;
        $directorio = _BASE_PATH_ . 'public/images/productos/';

        if (!is_dir($directorio)) {
            mkdir($directorio, 0755, true);
        }

        if (move_uploaded_file($archivo['tmp_name'], $directorio . $nombre)) {
            return $nombre;
        }

        return null;
    }

    private function eliminarImagen(string $nombre): void
    {
        $ruta = _BASE_PATH_ . 'public/images/productos/' . $nombre;
        if (file_exists($ruta)) {
            unlink($ruta);
        }
    }
}