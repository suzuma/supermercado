<?php
namespace App\Repositories;

use App\Helpers\ResponseHelper;
use App\Models\{CorteCaja, Venta};
use Core\Log;
use Exception;
use Illuminate\Database\Capsule\Manager as Capsule;

class CorteCajaRepository
{
    // ── Totales de ventas sin corte asignado ──────────────────
    public function calcularPendientes(): array
    {
        try {
            $ventas = Venta::where('estado', 'completada')
                ->whereNull('corte_id')
                ->get(['id', 'tipo', 'total']);

            $desglose = [
                'efectivo'      => ['total' => 0.0, 'count' => 0],
                'tarjeta'       => ['total' => 0.0, 'count' => 0],
                'transferencia' => ['total' => 0.0, 'count' => 0],
            ];

            foreach ($ventas as $v) {
                $tipo = array_key_exists($v->tipo, $desglose) ? $v->tipo : 'efectivo';
                $desglose[$tipo]['total'] += (float)$v->total;
                $desglose[$tipo]['count']++;
            }

            return [
                'desglose'     => $desglose,
                'total_ventas' => round(array_sum(array_column($desglose, 'total')), 2),
                'num_ventas'   => array_sum(array_column($desglose, 'count')),
                'ids'          => $ventas->pluck('id')->toArray(),
            ];
        } catch (Exception $e) {
            Log::error(self::class, $e->getMessage());
            return [
                'desglose'     => ['efectivo' => ['total' => 0, 'count' => 0],
                                   'tarjeta'  => ['total' => 0, 'count' => 0],
                                   'transferencia' => ['total' => 0, 'count' => 0]],
                'total_ventas' => 0,
                'num_ventas'   => 0,
                'ids'          => [],
            ];
        }
    }

    // ── Registrar corte ───────────────────────────────────────
    public function registrar(float $fondoInicial, float $efectivoContado, string $obs, int $usuarioId): ResponseHelper
    {
        $rh = new ResponseHelper();

        try {
            Capsule::transaction(function () use ($fondoInicial, $efectivoContado, $obs, $usuarioId, $rh) {
                $p = $this->calcularPendientes();

                if ($p['num_ventas'] === 0) {
                    throw new Exception('No hay ventas pendientes de corte');
                }

                $efEsperado = round($fondoInicial + $p['desglose']['efectivo']['total'], 2);
                $diferencia = round($efectivoContado - $efEsperado, 2);

                $corte                      = new CorteCaja();
                $corte->usuario_id          = $usuarioId;
                $corte->fondo_inicial       = $fondoInicial;
                $corte->total_efectivo      = round($p['desglose']['efectivo']['total'], 2);
                $corte->total_tarjeta       = round($p['desglose']['tarjeta']['total'], 2);
                $corte->total_transferencia = round($p['desglose']['transferencia']['total'], 2);
                $corte->total_ventas        = $p['total_ventas'];
                $corte->num_ventas          = $p['num_ventas'];
                $corte->efectivo_esperado   = $efEsperado;
                $corte->efectivo_contado    = $efectivoContado;
                $corte->diferencia          = $diferencia;
                $corte->observaciones       = $obs !== '' ? $obs : null;
                $corte->save();

                if (!empty($p['ids'])) {
                    Venta::whereIn('id', $p['ids'])->update(['corte_id' => $corte->id]);
                }

                $rh->setResponse(true, 'Corte de caja registrado correctamente');
                $rh->result = ['corte_id' => $corte->id];
            });
        } catch (Exception $e) {
            Log::error(self::class, $e->getMessage());
            $rh->setResponse(false, $e->getMessage() ?: 'No se pudo registrar el corte');
        }

        return $rh;
    }

    // ── Historial paginado ────────────────────────────────────
    public function listar(int $pagina = 1, int $limite = 20): array
    {
        try {
            $query = CorteCaja::with('usuario')->orderByDesc('created_at');
            $total  = $query->count();
            $datos  = $query->skip(($pagina - 1) * $limite)->take($limite)->get();

            return [
                'datos'       => $datos,
                'total'       => $total,
                'pagina'      => $pagina,
                'total_pages' => (int)ceil($total / $limite),
            ];
        } catch (Exception $e) {
            Log::error(self::class, $e->getMessage());
            return ['datos' => collect(), 'total' => 0, 'pagina' => 1, 'total_pages' => 1];
        }
    }

    // ── Obtener uno con sus ventas ────────────────────────────
    public function obtener(int $id): CorteCaja
    {
        try {
            return CorteCaja::with(['usuario', 'ventas.cliente'])->findOrFail($id);
        } catch (Exception $e) {
            Log::error(self::class, $e->getMessage());
            return new CorteCaja();
        }
    }
}