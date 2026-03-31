<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\Producto;
use Core\Log;
use Exception;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Eloquent\Collection;

class WishlistRepository
{
    /** Agrega o quita un producto de la wishlist. Devuelve la acción realizada. */
    public function toggle(int $clienteId, int $productoId): string
    {
        $existe = Capsule::table('wishlist')
            ->where('cliente_id', $clienteId)
            ->where('producto_id', $productoId)
            ->exists();

        if ($existe) {
            Capsule::table('wishlist')
                ->where('cliente_id', $clienteId)
                ->where('producto_id', $productoId)
                ->delete();
            return 'removed';
        }

        Capsule::table('wishlist')->insert([
            'cliente_id'  => $clienteId,
            'producto_id' => $productoId,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);
        return 'added';
    }

    /** IDs de productos en la wishlist del cliente — para marcar botones en las vistas. */
    public function idsDeCliente(int $clienteId): array
    {
        try {
            return Capsule::table('wishlist')
                ->where('cliente_id', $clienteId)
                ->pluck('producto_id')
                ->toArray();
        } catch (Exception $e) {
            Log::error(WishlistRepository::class, $e->getMessage());
            return [];
        }
    }

    /** Productos completos de la wishlist con promo adjunta (para la página /tienda/wishlist). */
    public function listar(int $clienteId): Collection
    {
        try {
            $ids = Capsule::table('wishlist')
                ->where('cliente_id', $clienteId)
                ->orderByDesc('created_at')
                ->pluck('producto_id')
                ->toArray();

            if (empty($ids)) {
                return collect();
            }

            return Producto::with('categoria')
                ->whereIn('id', $ids)
                ->where('activo', 1)
                ->orderByRaw('FIELD(id, ' . implode(',', $ids) . ')')
                ->get();
        } catch (Exception $e) {
            Log::error(WishlistRepository::class, $e->getMessage());
            return collect();
        }
    }
}