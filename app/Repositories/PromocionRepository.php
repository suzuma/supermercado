<?php
namespace App\Repositories;

use App\Helpers\ResponseHelper;
use App\Models\{Promocion, Producto};
use Core\Log;
use Exception;
use Illuminate\Database\Eloquent\Collection;

class PromocionRepository
{
    private $model;

    public function __construct()
    {
        $this->model = new Promocion();
    }

    // ── Listar todas ──────────────────────────────────────────
    public function listar(): Collection
    {
        try {
            return $this->model
                ->with('producto')
                ->orderByDesc('created_at')
                ->get();
        } catch (Exception $e) {
            Log::error(PromocionRepository::class, $e->getMessage());
            return collect();
        }
    }

    // ── Obtener vigentes (para caja y tienda) ─────────────────
    public function vigentes(): Collection
    {
        try {
            return $this->model->vigentes()->with('producto')->get();
        } catch (Exception $e) {
            Log::error(PromocionRepository::class, $e->getMessage());
            return collect();
        }
    }

    // ── Obtener promoción vigente de un producto ──────────────
    public function obtenerDeProducto(int $productoId): ?Promocion
    {
        try {
            return $this->model->vigentes()
                ->where('producto_id', $productoId)
                ->first();
        } catch (Exception $e) {
            Log::error(PromocionRepository::class, $e->getMessage());
            return null;
        }
    }

    // ── Obtener una ───────────────────────────────────────────
    public function obtener(int $id): Promocion
    {
        try {
            return $this->model->findOrFail($id);
        } catch (Exception $e) {
            Log::error(PromocionRepository::class, $e->getMessage());
            return new Promocion();
        }
    }

    // ── Guardar ───────────────────────────────────────────────
    public function guardar(Promocion $model): ResponseHelper
    {
        $rh = new ResponseHelper();

        try {
            if (empty($model->producto_id) || empty($model->nombre)) {
                return $rh->setResponse(false, 'Producto y nombre son requeridos');
            }

            if ($model->fecha_inicio > $model->fecha_fin) {
                return $rh->setResponse(false, 'La fecha de inicio no puede ser mayor a la fecha de fin');
            }

            $promo               = empty($model->id) ? new Promocion() : Promocion::findOrFail($model->id);
            $promo->producto_id  = $model->producto_id;
            $promo->nombre       = $model->nombre;
            $promo->tipo         = $model->tipo;
            $promo->valor        = $model->valor ?? 0;
            $promo->cantidad_min = $model->cantidad_min ?? 1;
            $promo->fecha_inicio = $model->fecha_inicio;
            $promo->fecha_fin    = $model->fecha_fin;
            $promo->activo       = 1;

            if (!empty($model->id)) {
                $promo->exists = true;
            }

            $promo->save();
            $rh->setResponse(true, 'Promoción guardada correctamente');
        } catch (Exception $e) {
            Log::error(PromocionRepository::class, $e->getMessage());
            $rh->setResponse(false, 'No se pudo guardar la promoción');
        }

        return $rh;
    }

    // ── Desactivar ────────────────────────────────────────────
    public function desactivar(int $id): ResponseHelper
    {
        $rh = new ResponseHelper();

        try {
            $promo         = $this->model->findOrFail($id);
            $promo->activo = 0;
            $promo->exists = true;
            $promo->save();
            $rh->setResponse(true, 'Promoción desactivada');
        } catch (Exception $e) {
            Log::error(PromocionRepository::class, $e->getMessage());
            $rh->setResponse(false, 'No se pudo desactivar la promoción');
        }

        return $rh;
    }

    // ── Obtener mapa de promociones vigentes por producto_id ──
    // Útil para caja: { producto_id => promocion }
    public function mapaVigentes(): array
    {
        try {
            return $this->model->vigentes()
                ->get()
                ->keyBy('producto_id')
                ->toArray();
        } catch (Exception $e) {
            Log::error(PromocionRepository::class, $e->getMessage());
            return [];
        }
    }
}