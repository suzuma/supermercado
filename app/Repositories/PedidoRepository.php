<?php
namespace App\Repositories;

use App\Helpers\{ResponseHelper, MailHelper};
use App\Models\{Pedido, Producto, Configuracion};
use App\Services\CacheService;
use Core\{Auth, Log};
use App\Repositories\PuntosRepository;
use Exception;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Collection;

class PedidoRepository
{
    private $model;

    public function __construct()
    {
        $this->model = new Pedido();
    }

    // ── Dashboard ─────────────────────────────────────────────
    public function totalPendientes(): int
    {
        try {
            return $this->model->where('estado', 'pendiente')->count();
        } catch (Exception $e) {
            Log::error(PedidoRepository::class, $e->getMessage());
            return 0;
        }
    }

    public function recientes(int $limite = 8): Collection
    {
        try {
            return $this->model
                ->with('cliente')
                ->whereIn('estado', ['pendiente', 'confirmado', 'enviado'])
                ->orderByDesc('created_at')
                ->take($limite)
                ->get();
        } catch (Exception $e) {
            Log::error(PedidoRepository::class, $e->getMessage());
            return collect();
        }
    }

    // ── Listar con paginación y filtros ───────────────────────
    public function listar(int $pagina = 1, int $limite = 20, ?string $estado = null, ?string $fechaDesde = null, ?string $fechaHasta = null): array
    {
        try {
            $query = $this->model->with(['cliente', 'usuario']);

            if ($estado) {
                $query->where('estado', $estado);
            }
            if ($fechaDesde) {
                $query->whereDate('created_at', '>=', $fechaDesde);
            }
            if ($fechaHasta) {
                $query->whereDate('created_at', '<=', $fechaHasta);
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
            Log::error(PedidoRepository::class, $e->getMessage());
            return ['datos' => collect(), 'total' => 0, 'pagina' => 1, 'total_pages' => 1];
        }
    }

    // ── Kanban: pedidos activos agrupados por estado ──────────
    public function listarPorEstado(): array
    {
        try {
            $pedidos = $this->model
                ->with(['cliente', 'usuario'])
                ->whereIn('estado', ['pendiente', 'confirmado', 'enviado'])
                ->orderByDesc('created_at')
                ->get();

            $grupos = ['pendiente' => [], 'confirmado' => [], 'enviado' => []];
            foreach ($pedidos as $pedido) {
                $grupos[$pedido->estado][] = $pedido;
            }
            return $grupos;
        } catch (Exception $e) {
            Log::error(PedidoRepository::class, $e->getMessage());
            return ['pendiente' => [], 'confirmado' => [], 'enviado' => []];
        }
    }

    // ── Listar sin paginación para exportar ───────────────────
    public function listarParaExportar(?string $estado, ?string $fechaDesde, ?string $fechaHasta): \Illuminate\Database\Eloquent\Collection
    {
        try {
            $query = $this->model->with(['cliente', 'usuario']);

            if ($estado) {
                $query->where('estado', $estado);
            }
            if ($fechaDesde) {
                $query->whereDate('created_at', '>=', $fechaDesde);
            }
            if ($fechaHasta) {
                $query->whereDate('created_at', '<=', $fechaHasta);
            }

            return $query->orderByDesc('created_at')->get();
        } catch (Exception $e) {
            Log::error(PedidoRepository::class, $e->getMessage());
            return collect();
        }
    }

    // ── Obtener uno con detalles ──────────────────────────────
    public function obtener(int $id): Pedido
    {
        try {
            return $this->model
                ->with(['cliente', 'usuario', 'detalles.producto'])
                ->findOrFail($id);
        } catch (Exception $e) {
            Log::error(PedidoRepository::class, $e->getMessage());
            return new Pedido();
        }
    }

    // ── Cambiar estado ────────────────────────────────────────
    public function cambiarEstado(int $id, string $estado): ResponseHelper
    {
        $rh = new ResponseHelper();

        $estadosValidos = ['pendiente', 'confirmado', 'enviado', 'entregado', 'cancelado'];

        if (!in_array($estado, $estadosValidos)) {
            return $rh->setResponse(false, 'Estado no válido');
        }

        try {
            $pedido = $this->model->findOrFail($id);

            if ($pedido->estado === 'entregado') {
                return $rh->setResponse(false, 'No se puede modificar un pedido ya entregado');
            }

            $estadoAnterior = $pedido->estado;
            $pedido->estado = $estado;

            if ($estado === 'enviado' && !$pedido->fecha_entrega) {
                $pedido->fecha_entrega = date('Y-m-d H:i:s', strtotime('+2 hours'));
            }

            $pedido->exists = true;
            $pedido->save();

            try {
                $puntosRepo = new PuntosRepository();
                if ($estado === 'entregado') {
                    $puntosRepo->acumular((int)$pedido->cliente_id, $pedido->id, (float)$pedido->total);
                } elseif ($estado === 'cancelado' && $pedido->puntos_usados > 0) {
                    $puntosRepo->revertirCanje((int)$pedido->cliente_id, $pedido->id, (int)$pedido->puntos_usados);
                }
            } catch (Exception $e) {
                Log::error(PedidoRepository::class, 'Puntos no procesados: ' . $e->getMessage());
            }

            $rh->setResponse(true, 'Estado actualizado a: ' . ucfirst($estado));
            CacheService::forget('pedidos_pendientes_count');

            // Notificar al cliente por email (solo para estados relevantes)
            if (in_array($estado, ['confirmado', 'enviado', 'entregado', 'cancelado'])) {
                $this->notificarCliente($pedido->id, $estado);
            }
        } catch (Exception $e) {
            Log::error(PedidoRepository::class, $e->getMessage());
            $rh->setResponse(false, 'No se pudo actualizar el estado');
        }

        return $rh;
    }

    // ── Cancelación por el propio cliente ────────────────────
    public function cancelarPorCliente(int $pedidoId, int $clienteId): ResponseHelper
    {
        $rh = new ResponseHelper();

        try {
            $pedido = $this->model->with('detalles')->findOrFail($pedidoId);

            if ((int)$pedido->cliente_id !== $clienteId) {
                return $rh->setResponse(false, 'No tienes permiso para cancelar este pedido');
            }

            if ($pedido->estado !== 'pendiente') {
                return $rh->setResponse(false, 'Solo puedes cancelar pedidos que aún estén pendientes');
            }

            Capsule::transaction(function () use ($pedido) {
                foreach ($pedido->detalles as $detalle) {
                    Producto::where('id', $detalle->producto_id)
                        ->increment('stock', $detalle->cantidad);
                }

                $pedido->estado = 'cancelado';
                $pedido->save();
            });

            $rh->setResponse(true, 'Tu pedido fue cancelado correctamente');
            CacheService::forget('pedidos_pendientes_count');
            $this->notificarCliente($pedido->id, 'cancelado');
        } catch (Exception $e) {
            Log::error(PedidoRepository::class, $e->getMessage());
            $rh->setResponse(false, 'No se pudo cancelar el pedido');
        }

        return $rh;
    }

    // ── Notificación de email al cliente ─────────────────────
    private function notificarCliente(int $pedidoId, string $estado): void
    {
        try {
            $pedido  = $this->model->with('cliente')->findOrFail($pedidoId);
            $cliente = $pedido->cliente;

            if (empty($cliente->email)) return;

            $asuntos = [
                'confirmado' => 'Tu pedido #%s fue confirmado',
                'enviado'    => 'Tu pedido #%s está en camino 🚚',
                'entregado'  => 'Tu pedido #%s fue entregado 🎉',
                'cancelado'  => 'Tu pedido #%s fue cancelado',
            ];

            $folio   = str_pad($pedidoId, 4, '0', STR_PAD_LEFT);
            $asunto  = sprintf($asuntos[$estado] ?? 'Actualización de tu pedido #%s', $folio);

            MailHelper::send(
                $cliente->email,
                $cliente->nombre . ' ' . $cliente->apellido,
                $asunto,
                'pedido_estado',
                [
                    'pedido'  => $pedido,
                    'cliente' => $cliente,
                    'simbolo' => Configuracion::get('moneda', 'MXN') === 'USD' ? 'USD $' : '$',
                    'negocio' => Configuracion::get('negocio_nombre', 'Supermercado Web'),
                    'base_url' => defined('_BASE_HTTP_') ? _BASE_HTTP_ : '',
                ]
            );
        } catch (Exception $e) {
            Log::error(PedidoRepository::class, 'Email estado: ' . $e->getMessage());
        }
    }

    // ── Pedidos asignados a un repartidor ────────────────────
    public function listarDeRepartidor(int $usuarioId): Collection
    {
        try {
            return $this->model
                ->with(['cliente', 'detalles.producto'])
                ->where('usuario_id', $usuarioId)
                ->whereIn('estado', ['confirmado', 'enviado'])
                ->orderByDesc('created_at')
                ->get();
        } catch (Exception $e) {
            Log::error(PedidoRepository::class, $e->getMessage());
            return collect();
        }
    }

    // ── Asignar repartidor ────────────────────────────────────
    public function asignarRepartidor(int $id, int $usuarioId): ResponseHelper
    {
        $rh = new ResponseHelper();

        try {
            $pedido = $this->model->findOrFail($id);
            $pedido->usuario_id = $usuarioId;
            $pedido->exists     = true;

            // Auto-confirmar si estaba pendiente
            if ($pedido->estado === 'pendiente') {
                $pedido->estado = 'confirmado';
            }

            $pedido->save();
            $rh->setResponse(true, 'Repartidor asignado correctamente');
        } catch (Exception $e) {
            Log::error(PedidoRepository::class, $e->getMessage());
            $rh->setResponse(false, 'No se pudo asignar el repartidor');
        }

        return $rh;
    }
}