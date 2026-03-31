<?php
namespace App\Repositories;

use App\Helpers\ResponseHelper;
use App\Models\Cliente;
use App\Services\AuditoriaService;
use Core\Log;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Database\Capsule\Manager as DB;

class ClienteRepository
{
    private $model;

    public function __construct()
    {
        $this->model = new Cliente();
    }

    // ── Listar con paginación y búsqueda ──────────────────
    public function listar(int $pagina = 1, int $limite = 15, ?string $busqueda = null, string $filtro = 'todos'): array
    {
        try {
            $query = $this->model->newQuery();

            if ($filtro === 'activos') {
                $query->where('activo', 1);
            } elseif ($filtro === 'inactivos') {
                $query->where('activo', 0);
            }

            if ($busqueda) {
                $query->where(function ($q) use ($busqueda) {
                    $q->where('nombre',   'like', "%$busqueda%")
                        ->orWhere('apellido','like', "%$busqueda%")
                        ->orWhere('email',   'like', "%$busqueda%")
                        ->orWhere('telefono','like', "%$busqueda%")
                        ->orWhere('rfc',     'like', "%$busqueda%");
                });
            }

            $total  = $query->count();
            $offset = ($pagina - 1) * $limite;

            $datos = $query->withCount(['ventas', 'pedidos'])
                ->orderBy('nombre')
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
            Log::error(ClienteRepository::class, $e->getMessage());
            return ['datos' => collect(), 'total' => 0, 'pagina' => 1, 'total_pages' => 1];
        }
    }

    // ── Obtener uno ───────────────────────────────────────
    public function obtener(int $id): Cliente
    {
        try {
            return $this->model
                ->withCount(['ventas', 'pedidos'])
                ->findOrFail($id);
        } catch (Exception $e) {
            Log::error(ClienteRepository::class, $e->getMessage());
            return new Cliente();
        }
    }

    // ── Guardar ───────────────────────────────────────────
    public function guardar(Cliente $model): ResponseHelper
    {
        $rh = new ResponseHelper();

        try {
            $esNuevo = empty($model->id);

            // Verificar email único
            $existe = $this->model->where('email', $model->email)
                ->when(!$esNuevo, fn($q) => $q->where('id', '!=', $model->id))
                ->first();

            if ($existe) {
                return $rh->setResponse(false, 'Ya existe un cliente con ese correo');
            }

            $cliente                   = $esNuevo ? new Cliente() : Cliente::findOrFail($model->id);
            $cliente->nombre           = $model->nombre;
            $cliente->apellido         = $model->apellido;
            $cliente->email            = $model->email;
            $cliente->telefono         = $model->telefono ?? '';
            $cliente->direccion        = $model->direccion ?? '';
            $cliente->fecha_nacimiento = !empty($model->fecha_nacimiento) ? $model->fecha_nacimiento : null;
            $cliente->rfc              = !empty($model->rfc) ? strtoupper($model->rfc) : null;
            $cliente->activo           = 1;

            if ($esNuevo) {
                $cliente->password = password_hash($model->password ?: 'cliente123', PASSWORD_BCRYPT, ['cost' => 12]);
            } elseif (!empty($model->password)) {
                $cliente->password = password_hash($model->password, PASSWORD_BCRYPT, ['cost' => 12]);
            }

            if (!$esNuevo) {
                $cliente->exists = true;
            }

            $cliente->save();
            $rh->setResponse(true, $esNuevo ? 'Cliente registrado correctamente' : 'Cliente actualizado correctamente');
        } catch (Exception $e) {
            Log::error(ClienteRepository::class, $e->getMessage());
            $rh->setResponse(false, 'No se pudo guardar el cliente');
        }

        if ($rh->response) {
            $accion = $esNuevo ? 'crear' : 'editar';
            AuditoriaService::registrar('clientes', $accion, "Cliente '{$model->nombre} {$model->apellido}'", $cliente->id ?? null);
        }

        return $rh;
    }

    // ── Desactivar ────────────────────────────────────────
    public function desactivar(int $id): ResponseHelper
    {
        $rh = new ResponseHelper();

        try {
            $cliente         = Cliente::findOrFail($id);
            $cliente->activo = 0;
            $cliente->exists = true;
            $cliente->save();
            $rh->setResponse(true, 'Cliente desactivado correctamente');
        } catch (Exception $e) {
            Log::error(ClienteRepository::class, $e->getMessage());
            $rh->setResponse(false, 'No se pudo desactivar el cliente');
        }

        if ($rh->response) {
            AuditoriaService::registrar('clientes', 'desactivar', "Cliente #$id desactivado", $id);
        }

        return $rh;
    }

    // ── Historial de compras (ventas) ─────────────────────
    public function historialVentas(int $clienteId): SupportCollection
    {
        try {
            return DB::table('ventas')
                ->where('cliente_id', $clienteId)
                ->where('estado', 'completada')
                ->orderByDesc('created_at')
                ->get();
        } catch (Exception $e) {
            Log::error(ClienteRepository::class, $e->getMessage());
            return collect();
        }
    }

    // ── Pedidos del cliente ───────────────────────────────
    public function pedidosCliente(int $clienteId):  SupportCollection
    {
        try {
            return DB::table('pedidos')
                ->where('cliente_id', $clienteId)
                ->orderByDesc('created_at')
                ->get();
        } catch (Exception $e) {
            Log::error(ClienteRepository::class, $e->getMessage());
            return collect();
        }
    }

    // ── Estadísticas del cliente ──────────────────────────
    public function estadisticas(int $clienteId): array
    {
        try {
            $ventas = DB::table('ventas')
                ->where('cliente_id', $clienteId)
                ->where('estado', 'completada')
                ->selectRaw('COUNT(*) as total_ventas, SUM(total) as monto_total, MAX(created_at) as ultima_compra')
                ->first();

            $pedidos = DB::table('pedidos')
                ->where('cliente_id', $clienteId)
                ->selectRaw('COUNT(*) as total_pedidos, SUM(total) as monto_pedidos')
                ->first();

            return [
                'total_compras'  => ($ventas->total_ventas ?? 0) + ($pedidos->total_pedidos ?? 0),
                'monto_total'    => ($ventas->monto_total ?? 0) + ($pedidos->monto_pedidos ?? 0),
                'ultima_compra'  => $ventas->ultima_compra ?? null,
                'total_ventas'   => $ventas->total_ventas ?? 0,
                'total_pedidos'  => $pedidos->total_pedidos ?? 0,
            ];
        } catch (Exception $e) {
            Log::error(ClienteRepository::class, $e->getMessage());
            return ['total_compras' => 0, 'monto_total' => 0, 'ultima_compra' => null, 'total_ventas' => 0, 'total_pedidos' => 0];
        }
    }

    // ── Buscar para autocomplete (caja) ───────────────────
    public function buscar(string $termino): Collection
    {
        try {
            return $this->model->activos()
                ->where(function ($q) use ($termino) {
                    $q->where('nombre',   'like', "%$termino%")
                        ->orWhere('apellido','like', "%$termino%")
                        ->orWhere('email',   'like', "%$termino%")
                        ->orWhere('telefono','like', "%$termino%");
                })
                ->take(8)
                ->get();
        } catch (Exception $e) {
            Log::error(ClienteRepository::class, $e->getMessage());
            return collect();
        }
    }
}