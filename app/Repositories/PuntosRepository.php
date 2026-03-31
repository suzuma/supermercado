<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\ResponseHelper;
use App\Models\{Cliente, Configuracion, PuntosTransaccion};
use Core\Log;
use Exception;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Collection;

class PuntosRepository
{
    public function config(): array
    {
        return [
            'puntos_por_peso'     => (float)Configuracion::get('puntos_por_peso', '10'),
            'valor_punto'         => (float)Configuracion::get('valor_punto', '0.50'),
            'minimo_puntos_canje' => (int)Configuracion::get('minimo_puntos_canje', '50'),
        ];
    }

    public function calcularPuntosGanados(float $total): int
    {
        $ppp = (float)Configuracion::get('puntos_por_peso', '10');
        return $ppp > 0 ? (int)floor($total / $ppp) : 0;
    }

    public function valorEnPesos(int $puntos): float
    {
        $vp = (float)Configuracion::get('valor_punto', '0.50');
        return round($puntos * $vp, 2);
    }

    public function saldo(int $clienteId): int
    {
        try {
            return (int)(Cliente::find($clienteId)?->puntos ?? 0);
        } catch (Exception $e) {
            Log::error(PuntosRepository::class, $e->getMessage());
            return 0;
        }
    }

    public function historial(int $clienteId, int $limite = 20): Collection
    {
        try {
            return PuntosTransaccion::with('pedido')
                ->where('cliente_id', $clienteId)
                ->orderByDesc('created_at')
                ->take($limite)
                ->get();
        } catch (Exception $e) {
            Log::error(PuntosRepository::class, $e->getMessage());
            return collect();
        }
    }

    /**
     * Acumula puntos al cliente al entregar el pedido.
     * Siempre dentro de una transacción externa.
     */
    public function acumular(int $clienteId, int $pedidoId, float $totalPagado): void
    {
        $puntosGanados = $this->calcularPuntosGanados($totalPagado);
        if ($puntosGanados <= 0) return;

        Cliente::where('id', $clienteId)->increment('puntos', $puntosGanados);

        $tx             = new PuntosTransaccion();
        $tx->cliente_id = $clienteId;
        $tx->pedido_id  = $pedidoId;
        $tx->tipo       = 'ganado';
        $tx->puntos     = $puntosGanados;
        $tx->descripcion = "Compra — pedido #{$pedidoId}";
        $tx->created_at = date('Y-m-d H:i:s');
        $tx->save();
    }

    /**
     * Canjea puntos durante el checkout.
     * Retorna el descuento en pesos aplicado.
     */
    public function canjear(int $clienteId, int $pedidoId, int $puntosACanjear): ResponseHelper
    {
        $rh = new ResponseHelper();

        $cfg      = $this->config();
        $saldo    = $this->saldo($clienteId);
        $minCanje = $cfg['minimo_puntos_canje'];

        if ($puntosACanjear < $minCanje) {
            return $rh->setResponse(false, "El mínimo para canjear es {$minCanje} puntos");
        }

        if ($puntosACanjear > $saldo) {
            return $rh->setResponse(false, "No tienes suficientes puntos (tienes {$saldo})");
        }

        try {
            Capsule::transaction(function () use ($clienteId, $pedidoId, $puntosACanjear, $cfg, &$rh) {
                Cliente::where('id', $clienteId)->decrement('puntos', $puntosACanjear);

                $tx             = new PuntosTransaccion();
                $tx->cliente_id = $clienteId;
                $tx->pedido_id  = $pedidoId;
                $tx->tipo       = 'canjeado';
                $tx->puntos     = -$puntosACanjear;
                $tx->descripcion = "Canje en pedido #{$pedidoId}";
                $tx->created_at = date('Y-m-d H:i:s');
                $tx->save();

                $descuento = $this->valorEnPesos($puntosACanjear);
                $rh->setResponse(true, "{$puntosACanjear} puntos canjeados");
                $rh->result = ['descuento' => $descuento, 'puntos' => $puntosACanjear];
            });
        } catch (Exception $e) {
            Log::error(PuntosRepository::class, $e->getMessage());
            $rh->setResponse(false, 'No se pudieron canjear los puntos');
        }

        return $rh;
    }

    /**
     * Revierte puntos canjeados si se cancela un pedido.
     * Siempre dentro de una transacción externa.
     */
    public function revertirCanje(int $clienteId, int $pedidoId, int $puntosUsados): void
    {
        if ($puntosUsados <= 0) return;

        Cliente::where('id', $clienteId)->increment('puntos', $puntosUsados);

        $tx             = new PuntosTransaccion();
        $tx->cliente_id = $clienteId;
        $tx->pedido_id  = $pedidoId;
        $tx->tipo       = 'revertido';
        $tx->puntos     = $puntosUsados;
        $tx->descripcion = "Reversión por cancelación del pedido #{$pedidoId}";
        $tx->created_at = date('Y-m-d H:i:s');
        $tx->save();
    }
}