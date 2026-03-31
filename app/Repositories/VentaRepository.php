<?php
namespace App\Repositories;

use App\Helpers\ResponseHelper;
use App\Models\{Venta, VentaDetalle, Producto};
use App\Repositories\PuntosRepository;
use App\Services\AuditoriaService;
use Core\{Auth, Log};
use Exception;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Collection;

class VentaRepository
{
    private $model;

    public function __construct()
    {
        $this->model = new Venta();
    }

    // ── Listar ventas ─────────────────────────────────────────
    public function listar(int $pagina = 1, int $limite = 20, ?string $fecha = null): array
    {
        try {
            $query = $this->model->with(['usuario', 'cliente']);

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
            Log::error(VentaRepository::class, $e->getMessage());
            return ['datos' => collect(), 'total' => 0, 'pagina' => 1, 'total_pages' => 1];
        }
    }

    // ── Obtener venta con detalles ────────────────────────────
    public function obtener(int $id): Venta
    {
        try {
            return $this->model
                ->with(['usuario', 'cliente', 'detalles.producto'])
                ->findOrFail($id);
        } catch (Exception $e) {
            Log::error(VentaRepository::class, $e->getMessage());
            return new Venta();
        }
    }

    // ── Registrar venta ───────────────────────────────────────
    public function registrar(array $items, float $subtotal, float $descuento, float $total, string $tipo, ?int $clienteId): ResponseHelper
    {
        $rh = new ResponseHelper();

        try {
            Capsule::transaction(function () use ($items, $subtotal, $descuento, $total, $tipo, $clienteId, $rh) {
                $venta             = new Venta();
                $venta->usuario_id = Auth::getCurrentUser()->id;
                $venta->cliente_id = $clienteId;
                $venta->subtotal   = $subtotal;
                $venta->descuento  = $descuento;
                $venta->total      = $total;
                $venta->tipo       = $tipo;
                $venta->estado     = 'completada';
                $venta->save();

                foreach ($items as $item) {
                    $precioOriginal = (float)($item['precio']       ?? $item['precio_final'] ?? 0);
                    $precioFinal    = (float)($item['precio_final'] ?? $item['precio']       ?? 0);
                    $descuentoPromo = round(($precioOriginal - $precioFinal) * $item['cantidad'], 2);

                    $detalle                   = new VentaDetalle();
                    $detalle->venta_id         = $venta->id;
                    $detalle->producto_id      = $item['id'];
                    $detalle->cantidad         = $item['cantidad'];
                    $detalle->precio_unitario  = $precioFinal;
                    $detalle->precio_original  = $precioOriginal;
                    $detalle->descuento_promo  = $descuentoPromo;
                    $detalle->promo_id         = $item['promo_id']   ?? null;
                    $detalle->promo_desc       = $item['promo_desc'] ?? null;
                    $detalle->subtotal         = round($precioFinal * $item['cantidad'], 2);
                    $detalle->save();

                    Producto::where('id', $item['id'])
                        ->decrement('stock', $item['cantidad']);
                }

                $rh->setResponse(true, 'Venta registrada correctamente');
                $rh->result = ['venta_id' => $venta->id];
            });
        } catch (\Exception $e) {
            Log::error(VentaRepository::class, $e->getMessage());
            $rh->setResponse(false, 'No se pudo registrar la venta');
        }

        if ($rh->response) {
            $ventaId = $rh->result['venta_id'] ?? null;
            AuditoriaService::registrar('ventas', 'registrar', "Venta #{$ventaId} — Total: \${$total}", $ventaId);

            if ($clienteId) {
                try {
                    (new PuntosRepository())->acumular(
                        $clienteId,
                        null,
                        $total,
                        "Compra en caja — venta #{$ventaId}"
                    );
                } catch (\Exception $e) {
                    Log::error(VentaRepository::class, 'Puntos no acumulados: ' . $e->getMessage());
                }
            }
        }

        return $rh;
    }

    // ── Cancelar venta ────────────────────────────────────────
    public function cancelar(int $id): ResponseHelper
    {
        $rh = new ResponseHelper();

        try {
            $venta = $this->model->with('detalles')->findOrFail($id);

            if ($venta->estado === 'cancelada') {
                return $rh->setResponse(false, 'La venta ya está cancelada');
            }

            Capsule::transaction(function () use ($venta, $rh) {
                foreach ($venta->detalles as $detalle) {
                    Producto::where('id', $detalle->producto_id)
                        ->increment('stock', $detalle->cantidad);
                }

                $venta->estado = 'cancelada';
                $venta->exists = true;
                $venta->save();

                $rh->setResponse(true, 'Venta cancelada y stock revertido');
            });
        } catch (Exception $e) {
            Log::error(VentaRepository::class, $e->getMessage());
            $rh->setResponse(false, 'No se pudo cancelar la venta');
        }

        if ($rh->response) {
            AuditoriaService::registrar('ventas', 'cancelar', "Venta #$id cancelada", $id);
        }

        return $rh;
    }

    // ── Totales para dashboard ────────────────────────────────
    public function totalDia(): float
    {
        try {
            return (float)$this->model
                ->where('estado', 'completada')
                ->whereDate('created_at', date('Y-m-d'))
                ->sum('total');
        } catch (Exception $e) {
            return 0.0;
        }
    }

    public function cantidadDia(): int
    {
        try {
            return $this->model
                ->where('estado', 'completada')
                ->whereDate('created_at', date('Y-m-d'))
                ->count();
        } catch (Exception $e) {
            return 0;
        }
    }

    public function ventasSemana(): array
    {
        try {
            $datos  = [];
            $labels = [];

            for ($i = 6; $i >= 0; $i--) {
                $fecha    = date('Y-m-d', strtotime("-$i days"));
                $labels[] = date('D', strtotime($fecha));
                $datos[]  = (float)$this->model
                    ->where('estado', 'completada')
                    ->whereDate('created_at', $fecha)
                    ->sum('total');
            }

            return ['labels' => $labels, 'datos' => $datos];
        } catch (Exception $e) {
            return ['labels' => [], 'datos' => []];
        }
    }

    public function ultimasVentas(int $limite = 8): Collection
    {
        try {
            return $this->model
                ->with('cliente')
                ->where('estado', 'completada')
                ->whereDate('created_at', date('Y-m-d'))
                ->orderByDesc('created_at')
                ->take($limite)
                ->get();
        } catch (Exception $e) {
            return collect();
        }
    }

    public function promedioTicketDia(): float
    {
        try {
            $cantidad = $this->cantidadDia();
            return $cantidad > 0 ? round($this->totalDia() / $cantidad, 2) : 0.0;
        } catch (Exception $e) {
            return 0.0;
        }
    }

    public function productoTopDia(): array
    {
        try {
            $row = \Illuminate\Database\Capsule\Manager::table('venta_detalles')
                ->join('ventas', 'ventas.id', '=', 'venta_detalles.venta_id')
                ->join('productos', 'productos.id', '=', 'venta_detalles.producto_id')
                ->where('ventas.estado', 'completada')
                ->whereDate('ventas.created_at', date('Y-m-d'))
                ->selectRaw('productos.nombre, SUM(venta_detalles.cantidad) as total_vendido')
                ->groupBy('venta_detalles.producto_id', 'productos.nombre')
                ->orderByDesc('total_vendido')
                ->first();

            return $row ? ['nombre' => $row->nombre, 'cantidad' => (float)$row->total_vendido] : [];
        } catch (Exception $e) {
            Log::error(VentaRepository::class, $e->getMessage());
            return [];
        }
    }

    public function cajeroTopDia(): array
    {
        try {
            $row = \Illuminate\Database\Capsule\Manager::table('ventas')
                ->join('usuarios', 'usuarios.id', '=', 'ventas.usuario_id')
                ->where('ventas.estado', 'completada')
                ->whereDate('ventas.created_at', date('Y-m-d'))
                ->selectRaw('usuarios.nombre, usuarios.apellido, SUM(ventas.total) as total_vendido, COUNT(*) as num_ventas')
                ->groupBy('ventas.usuario_id', 'usuarios.nombre', 'usuarios.apellido')
                ->orderByDesc('total_vendido')
                ->first();

            return $row
                ? ['nombre' => $row->nombre . ' ' . $row->apellido, 'total' => (float)$row->total_vendido, 'ventas' => (int)$row->num_ventas]
                : [];
        } catch (Exception $e) {
            Log::error(VentaRepository::class, $e->getMessage());
            return [];
        }
    }
}