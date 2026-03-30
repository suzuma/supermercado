<?php
namespace App\Repositories;

use App\Helpers\ResponseHelper;
use App\Models\{Devolucion, DevolucionDetalle, Venta, VentaDetalle, Producto};
use App\Services\AuditoriaService;
use Core\{Auth, Log};
use Exception;
use Illuminate\Database\Capsule\Manager as Capsule;

class DevolucionesRepository
{
    private $model;

    public function __construct()
    {
        $this->model = new Devolucion();
    }

    public function buscarVenta(int $id): ?Venta
    {
        try {
            return Venta::with(['cliente', 'usuario', 'detalles.producto'])
                ->where('estado', 'completada')
                ->find($id);
        } catch (Exception $e) {
            Log::error(DevolucionesRepository::class, $e->getMessage());
            return null;
        }
    }

    public function registrar(int $ventaId, array $items, string $motivo): ResponseHelper
    {
        $rh = new ResponseHelper();

        try {
            Capsule::transaction(function () use ($ventaId, $items, $motivo, &$rh) {
                $venta = Venta::findOrFail($ventaId);

                if ($venta->estado !== 'completada') {
                    throw new Exception('Solo se pueden procesar devoluciones de ventas completadas');
                }

                $devolucion             = new Devolucion();
                $devolucion->venta_id   = $ventaId;
                $devolucion->usuario_id = Auth::getCurrentUser()->id;
                $devolucion->motivo     = $motivo ?: null;
                $devolucion->total_devuelto = 0;
                $devolucion->save();

                $totalDevuelto = 0.0;

                foreach ($items as $item) {
                    $detalleId        = (int)$item['venta_detalle_id'];
                    $cantidadDevolver = (float)$item['cantidad'];

                    $detalle = VentaDetalle::find($detalleId);

                    if (!$detalle || $detalle->venta_id !== $ventaId) {
                        throw new Exception('Artículo inválido o no pertenece a esta venta');
                    }

                    $disponible = (float)$detalle->cantidad - (float)$detalle->cantidad_devuelta;

                    if ($cantidadDevolver <= 0 || $cantidadDevolver > $disponible + 0.001) {
                        throw new Exception('Cantidad a devolver inválida para el producto ' . ($detalle->producto->nombre ?? $detalle->producto_id));
                    }

                    $subtotal = round($cantidadDevolver * (float)$detalle->precio_unitario, 2);
                    $totalDevuelto += $subtotal;

                    $dd                    = new DevolucionDetalle();
                    $dd->devolucion_id     = $devolucion->id;
                    $dd->venta_detalle_id  = $detalleId;
                    $dd->producto_id       = $detalle->producto_id;
                    $dd->cantidad          = $cantidadDevolver;
                    $dd->precio_unitario   = $detalle->precio_unitario;
                    $dd->subtotal          = $subtotal;
                    $dd->save();

                    Producto::where('id', $detalle->producto_id)
                        ->increment('stock', $cantidadDevolver);

                    $detalle->cantidad_devuelta = (float)$detalle->cantidad_devuelta + $cantidadDevolver;
                    $detalle->exists = true;
                    $detalle->save();
                }

                $devolucion->total_devuelto = round($totalDevuelto, 2);
                $devolucion->exists = true;
                $devolucion->save();

                $rh->setResponse(true, 'Devolución registrada correctamente');
                $rh->result = ['devolucion_id' => $devolucion->id];
            });
        } catch (Exception $e) {
            Log::error(DevolucionesRepository::class, $e->getMessage());
            $rh->setResponse(false, $e->getMessage() ?: 'No se pudo registrar la devolución');
        }

        if ($rh->response) {
            $devId = $rh->result['devolucion_id'] ?? null;
            AuditoriaService::registrar('devoluciones', 'registrar', "Devolución #$devId de Venta #$ventaId", $devId);
        }

        return $rh;
    }

    public function obtener(int $id): ?Devolucion
    {
        try {
            return $this->model
                ->with(['venta.cliente', 'usuario', 'detalles.producto'])
                ->find($id);
        } catch (Exception $e) {
            Log::error(DevolucionesRepository::class, $e->getMessage());
            return null;
        }
    }

    public function totalDia(): float
    {
        try {
            return (float) \Illuminate\Database\Capsule\Manager::table('devoluciones')
                ->whereDate('created_at', date('Y-m-d'))
                ->sum('total_devuelto');
        } catch (Exception $e) {
            return 0.0;
        }
    }

    public function cantidadDia(): int
    {
        try {
            return (int) \Illuminate\Database\Capsule\Manager::table('devoluciones')
                ->whereDate('created_at', date('Y-m-d'))
                ->count();
        } catch (Exception $e) {
            return 0;
        }
    }

    public function devolucionesSemana(): array
    {
        try {
            $datos  = [];
            $labels = [];

            for ($i = 6; $i >= 0; $i--) {
                $fecha    = date('Y-m-d', strtotime("-$i days"));
                $labels[] = date('D', strtotime($fecha));
                $datos[]  = (float) \Illuminate\Database\Capsule\Manager::table('devoluciones')
                    ->whereDate('created_at', $fecha)
                    ->sum('total_devuelto');
            }

            return ['labels' => $labels, 'datos' => $datos];
        } catch (Exception $e) {
            return ['labels' => [], 'datos' => []];
        }
    }

    public function listar(int $pagina = 1, int $limite = 20, ?string $fecha = null): array
    {
        try {
            $query = $this->model->with(['venta', 'usuario']);

            if ($fecha) {
                $query->whereDate('created_at', $fecha);
            } else {
                $query->whereDate('created_at', date('Y-m-d'));
            }

            $total  = $query->count();
            $offset = ($pagina - 1) * $limite;

            $datos = $query->orderByDesc('created_at')
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
            Log::error(DevolucionesRepository::class, $e->getMessage());
            return ['datos' => collect(), 'total' => 0, 'pagina' => 1, 'total_pages' => 1];
        }
    }
}