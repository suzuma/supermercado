<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\ResponseHelper;
use App\Models\Resena;
use Core\Log;
use Exception;
use Illuminate\Database\Capsule\Manager as Capsule;

class ResenaRepository
{
    private Resena $model;

    public function __construct()
    {
        $this->model = new Resena();
    }

    /** Reseñas activas de un producto con datos del cliente. */
    public function listarDeProducto(int $productoId): array
    {
        try {
            $resenas = $this->model
                ->with('cliente:id,nombre,apellido')
                ->where('producto_id', $productoId)
                ->where('activo', 1)
                ->orderByDesc('created_at')
                ->get();

            $promedio = $resenas->avg('calificacion') ?? 0;

            return [
                'resenas'  => $resenas,
                'promedio' => round((float)$promedio, 1),
                'total'    => $resenas->count(),
            ];
        } catch (Exception $e) {
            Log::error(ResenaRepository::class, $e->getMessage());
            return ['resenas' => collect(), 'promedio' => 0.0, 'total' => 0];
        }
    }

    /** Verifica si el cliente ya dejó reseña en el producto. */
    public function yaReseno(int $clienteId, int $productoId): bool
    {
        try {
            return $this->model
                ->where('cliente_id', $clienteId)
                ->where('producto_id', $productoId)
                ->exists();
        } catch (Exception $e) {
            Log::error(ResenaRepository::class, $e->getMessage());
            return false;
        }
    }

    /** Verifica si el cliente compró el producto (pedido entregado). */
    public function clienteComproProducto(int $clienteId, int $productoId): bool
    {
        try {
            return Capsule::table('pedido_detalles')
                ->join('pedidos', 'pedidos.id', '=', 'pedido_detalles.pedido_id')
                ->where('pedidos.cliente_id', $clienteId)
                ->where('pedidos.estado', 'entregado')
                ->where('pedido_detalles.producto_id', $productoId)
                ->exists();
        } catch (Exception $e) {
            Log::error(ResenaRepository::class, $e->getMessage());
            return false;
        }
    }

    public function guardar(int $clienteId, int $productoId, int $calificacion, string $comentario): ResponseHelper
    {
        $rh = new ResponseHelper();

        try {
            if ($calificacion < 1 || $calificacion > 5) {
                return $rh->setResponse(false, 'La calificación debe ser entre 1 y 5');
            }

            if (!$this->clienteComproProducto($clienteId, $productoId)) {
                return $rh->setResponse(false, 'Solo puedes reseñar productos que hayas comprado y recibido');
            }

            if ($this->yaReseno($clienteId, $productoId)) {
                return $rh->setResponse(false, 'Ya dejaste una reseña para este producto');
            }

            $resena               = new Resena();
            $resena->cliente_id   = $clienteId;
            $resena->producto_id  = $productoId;
            $resena->calificacion = $calificacion;
            $resena->comentario   = trim($comentario);
            $resena->created_at   = date('Y-m-d H:i:s');
            $resena->save();

            $rh->setResponse(true, '¡Gracias por tu reseña!');
        } catch (Exception $e) {
            Log::error(ResenaRepository::class, $e->getMessage());
            $rh->setResponse(false, 'No se pudo guardar la reseña');
        }

        return $rh;
    }
}