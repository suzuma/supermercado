<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\ResponseHelper;
use App\Models\{Merma, Producto};
use App\Services\{AuditoriaService, CacheService};
use Core\{Auth, Log};
use Exception;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Collection;

class MermaRepository
{
    public function listar(int $pagina = 1, int $limite = 20, ?string $fecha = null): array
    {
        try {
            $query = Merma::with(['producto', 'usuario'])->orderByDesc('created_at');

            if ($fecha) {
                $query->whereDate('created_at', $fecha);
            }

            $total = $query->count();
            $datos = $query->skip(($pagina - 1) * $limite)->take($limite)->get();

            return [
                'datos'       => $datos,
                'total'       => $total,
                'pagina'      => $pagina,
                'total_pages' => (int)ceil($total / $limite),
            ];
        } catch (Exception $e) {
            Log::error(MermaRepository::class, $e->getMessage());
            return ['datos' => collect(), 'total' => 0, 'pagina' => 1, 'total_pages' => 1];
        }
    }

    public function registrar(array $data): ResponseHelper
    {
        $rh = new ResponseHelper();

        try {
            Capsule::transaction(function () use ($data, &$rh) {
                $merma             = new Merma();
                $merma->producto_id = (int)$data['producto_id'];
                $merma->cantidad   = (float)$data['cantidad'];
                $merma->motivo     = $data['motivo'];
                $merma->notas      = $data['notas'] ?? null;
                $merma->usuario_id = Auth::getCurrentUser()->id;
                $merma->created_at = now();
                $merma->save();

                Producto::where('id', $merma->producto_id)
                    ->decrement('stock', $merma->cantidad);

                $rh->setResponse(true, 'Merma registrada correctamente');
                $rh->result = ['id' => $merma->id];
            });
        } catch (Exception $e) {
            Log::error(MermaRepository::class, $e->getMessage());
            $rh->setResponse(false, 'No se pudo registrar la merma');
        }

        if ($rh->response) {
            AuditoriaService::registrar('merma', 'registrar', "Merma manual: producto #{$data['producto_id']}", $rh->result['id'] ?? null);
        }

        return $rh;
    }

    /**
     * Desactiva todos los productos vencidos y registra su stock actual como merma.
     * Retorna cuántos productos fueron procesados.
     */
    public function darDeBajaVencidos(): ResponseHelper
    {
        $rh = new ResponseHelper();

        try {
            $usuarioId = Auth::getCurrentUser()->id;
            $hoy       = date('Y-m-d');

            $vencidos = Producto::activos()
                ->whereNotNull('fecha_caducidad')
                ->where('fecha_caducidad', '<', $hoy)
                ->where('stock', '>', 0)
                ->get();

            if ($vencidos->isEmpty()) {
                return $rh->setResponse(true, 'No hay productos vencidos con stock pendiente');
            }

            Capsule::transaction(function () use ($vencidos, $usuarioId, &$rh) {
                $procesados = 0;

                foreach ($vencidos as $producto) {
                    $merma             = new Merma();
                    $merma->producto_id = $producto->id;
                    $merma->cantidad   = $producto->stock;
                    $merma->motivo     = 'vencimiento';
                    $merma->notas      = "Baja automática — venció el {$producto->fecha_caducidad->format('d/m/Y')}";
                    $merma->usuario_id = $usuarioId;
                    $merma->created_at = now();
                    $merma->save();

                    $producto->stock  = 0;
                    $producto->activo = 0;
                    $producto->exists = true;
                    $producto->save();

                    $procesados++;
                }

                CacheService::forget('stock_bajo_count');

                $rh->setResponse(true, "Se dieron de baja {$procesados} producto(s) vencido(s)");
                $rh->result = ['procesados' => $procesados];
            });
        } catch (Exception $e) {
            Log::error(MermaRepository::class, $e->getMessage());
            $rh->setResponse(false, 'Error al procesar productos vencidos');
        }

        if ($rh->response) {
            AuditoriaService::registrar('merma', 'baja_vencidos', $rh->message, null);
        }

        return $rh;
    }

    public function totalMes(): float
    {
        try {
            return (float) Merma::whereMonth('created_at', date('m'))
                ->whereYear('created_at', date('Y'))
                ->join('productos', 'merma.producto_id', '=', 'productos.id')
                ->selectRaw('SUM(merma.cantidad * productos.precio_compra) as total')
                ->value('total');
        } catch (Exception $e) {
            Log::error(MermaRepository::class, $e->getMessage());
            return 0.0;
        }
    }
}