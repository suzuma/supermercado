<?php
namespace App\Repositories;

use App\Helpers\ResponseHelper;
use App\Models\{Proveedor, OrdenCompra, OrdenCompraDetalle, Producto};
use Core\{Auth, Log};
use Exception;
use Illuminate\Database\Eloquent\Collection;

class ProveedorRepository
{
    private $model;

    public function __construct()
    {
        $this->model = new Proveedor();
    }

    // ── Listar ────────────────────────────────────────────────
    public function listar(int $pagina = 1, int $limite = 15, ?string $busqueda = null): array
    {
        try {
            $query = $this->model->activos();

            if ($busqueda) {
                $query->where(function ($q) use ($busqueda) {
                    $q->where('nombre',   'like', "%$busqueda%")
                        ->orWhere('contacto','like', "%$busqueda%")
                        ->orWhere('email',   'like', "%$busqueda%")
                        ->orWhere('rfc',     'like', "%$busqueda%");
                });
            }

            $total  = $query->count();
            $offset = ($pagina - 1) * $limite;

            $datos = $query->orderBy('nombre')
                ->skip($offset)
                ->take($limite)
                ->get();

            return [
                'datos'       => $datos,
                'total'       => $total,
                'pagina'      => $pagina,
                'total_pages' => (int)ceil($total / $limite),
            ];
        } catch (Exception $e) {
            Log::error(ProveedorRepository::class, $e->getMessage());
            return ['datos' => collect(), 'total' => 0, 'pagina' => 1, 'total_pages' => 1];
        }
    }

    public function listarTodos(): Collection
    {
        try {
            return $this->model->activos()->orderBy('nombre')->get();
        } catch (Exception $e) {
            Log::error(ProveedorRepository::class, $e->getMessage());
            return collect();
        }
    }

    // ── Obtener uno ───────────────────────────────────────────
    public function obtener(int $id): Proveedor
    {
        try {
            return $this->model->findOrFail($id);
        } catch (Exception $e) {
            Log::error(ProveedorRepository::class, $e->getMessage());
            return new Proveedor();
        }
    }

    // ── Guardar ───────────────────────────────────────────────
    public function guardar(Proveedor $model): ResponseHelper
    {
        $rh = new ResponseHelper();

        try {
            $this->model->id        = $model->id;
            $this->model->nombre    = $model->nombre;
            $this->model->contacto  = $model->contacto;
            $this->model->telefono  = $model->telefono;
            $this->model->email     = $model->email;
            $this->model->direccion = $model->direccion;
            $this->model->rfc       = strtoupper($model->rfc ?? '');
            $this->model->activo    = 1;

            if (!empty($model->id)) {
                $this->model->exists = true;
            }

            $this->model->save();
            $rh->setResponse(true, 'Proveedor guardado correctamente');
        } catch (Exception $e) {
            Log::error(ProveedorRepository::class, $e->getMessage());
            $rh->setResponse(false, 'No se pudo guardar el proveedor');
        }

        return $rh;
    }

    // ── Desactivar ────────────────────────────────────────────
    public function desactivar(int $id): ResponseHelper
    {
        $rh = new ResponseHelper();

        try {
            $proveedor = $this->model->findOrFail($id);
            $proveedor->activo = 0;
            $proveedor->exists = true;
            $proveedor->save();
            $rh->setResponse(true, 'Proveedor desactivado correctamente');
        } catch (Exception $e) {
            Log::error(ProveedorRepository::class, $e->getMessage());
            $rh->setResponse(false, 'No se pudo desactivar el proveedor');
        }

        return $rh;
    }

    // ── Órdenes de compra ─────────────────────────────────────
    public function ordenesDeProveedor(int $proveedorId): Collection
    {
        try {
            return OrdenCompra::with(['usuario'])
                ->where('proveedor_id', $proveedorId)
                ->orderByDesc('created_at')
                ->get();
        } catch (Exception $e) {
            Log::error(ProveedorRepository::class, $e->getMessage());
            return collect();
        }
    }

    public function obtenerOrden(int $id): OrdenCompra
    {
        try {
            return OrdenCompra::with(['proveedor', 'usuario', 'detalles.producto'])
                ->findOrFail($id);
        } catch (Exception $e) {
            Log::error(ProveedorRepository::class, $e->getMessage());
            return new OrdenCompra();
        }
    }

    public function crearOrden(array $data): ResponseHelper
    {
        $rh = new ResponseHelper();

        try {
            $items = json_decode($data['items'], true);

            if (empty($items)) {
                return $rh->setResponse(false, 'Agrega al menos un producto a la orden');
            }

            $total = 0;
            foreach ($items as $item) {
                $total += $item['precio'] * $item['cantidad'];
            }

            $orden = new OrdenCompra();
            $orden->proveedor_id   = $data['proveedor_id'];
            $orden->usuario_id     = Auth::getCurrentUser()->id;
            $orden->total          = round($total, 2);
            $orden->estado         = 'pendiente';
            $orden->fecha_entrega  = !empty($data['fecha_entrega']) ? $data['fecha_entrega'] : null;
            $orden->save();

            foreach ($items as $item) {
                $detalle = new OrdenCompraDetalle();
                $detalle->orden_id        = $orden->id;
                $detalle->producto_id     = $item['id'];
                $detalle->cantidad        = $item['cantidad'];
                $detalle->precio_unitario = $item['precio'];
                $detalle->subtotal        = round($item['precio'] * $item['cantidad'], 2);
                $detalle->save();
            }

            $rh->setResponse(true, 'Orden de compra creada correctamente');
            $rh->result = ['orden_id' => $orden->id];
        } catch (Exception $e) {
            Log::error(ProveedorRepository::class, $e->getMessage());
            $rh->setResponse(false, 'No se pudo crear la orden');
        }

        return $rh;
    }

    public function cambiarEstadoOrden(int $id, string $estado): ResponseHelper
    {
        $rh = new ResponseHelper();

        try {
            $orden = OrdenCompra::with('detalles')->findOrFail($id);

            if ($orden->estado === 'recibida') {
                return $rh->setResponse(false, 'La orden ya fue recibida');
            }

            // Si se marca como recibida, actualizar stock de productos
            if ($estado === 'recibida') {
                foreach ($orden->detalles as $detalle) {
                    Producto::where('id', $detalle->producto_id)
                        ->increment('stock', $detalle->cantidad);
                }
            }

            $orden->estado = $estado;
            $orden->exists = true;
            $orden->save();

            $rh->setResponse(true, 'Estado actualizado a: ' . ucfirst($estado));
        } catch (Exception $e) {
            Log::error(ProveedorRepository::class, $e->getMessage());
            $rh->setResponse(false, 'No se pudo actualizar el estado');
        }

        return $rh;
    }
}