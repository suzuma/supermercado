<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\ResponseHelper;
use App\Models\Cupon;
use App\Services\AuditoriaService;
use Core\Log;
use Exception;
use Illuminate\Database\Eloquent\Collection;

class CuponRepository
{
    private Cupon $model;

    public function __construct()
    {
        $this->model = new Cupon();
    }

    public function listar(): Collection
    {
        try {
            return $this->model->orderByDesc('created_at')->get();
        } catch (Exception $e) {
            Log::error(CuponRepository::class, $e->getMessage());
            return collect();
        }
    }

    public function obtener(int $id): Cupon
    {
        try {
            return $this->model->findOrFail($id);
        } catch (Exception $e) {
            Log::error(CuponRepository::class, $e->getMessage());
            return new Cupon();
        }
    }

    /**
     * Valida un cupón aplicable a un total dado.
     * Devuelve response=true con result['descuento'] y result['cupon_id'] si es válido.
     */
    public function validar(string $codigo, float $total): ResponseHelper
    {
        $rh = new ResponseHelper();

        try {
            $cupon = $this->model
                ->where('codigo', strtoupper(trim($codigo)))
                ->where('activo', 1)
                ->first();

            if (!$cupon) {
                return $rh->setResponse(false, 'El cupón no existe o no está activo');
            }

            $hoy = date('Y-m-d');

            if ($cupon->fecha_inicio && $cupon->fecha_inicio > $hoy) {
                return $rh->setResponse(false, 'Este cupón aún no está vigente');
            }

            if ($cupon->fecha_fin && $cupon->fecha_fin < $hoy) {
                return $rh->setResponse(false, 'Este cupón ya expiró');
            }

            if ($cupon->usos_max !== null && $cupon->usos_actual >= $cupon->usos_max) {
                return $rh->setResponse(false, 'Este cupón ya alcanzó el límite de usos');
            }

            if ($total < (float)$cupon->monto_minimo) {
                $minimo = number_format((float)$cupon->monto_minimo, 2);
                return $rh->setResponse(false, "Este cupón requiere un mínimo de \${$minimo} en tu pedido");
            }

            $descuento = $cupon->calcularDescuento($total);

            $rh->setResponse(true, 'Cupón aplicado: ' . $cupon->descripcion);
            $rh->result = [
                'cupon_id'   => $cupon->id,
                'codigo'     => $cupon->codigo,
                'descuento'  => $descuento,
                'tipo'       => $cupon->tipo,
                'valor'      => $cupon->valor,
                'descripcion' => $cupon->descripcion,
            ];
        } catch (Exception $e) {
            Log::error(CuponRepository::class, $e->getMessage());
            $rh->setResponse(false, 'No se pudo validar el cupón');
        }

        return $rh;
    }

    public function guardar(array $data): ResponseHelper
    {
        $rh = new ResponseHelper();

        try {
            $codigo = strtoupper(trim($data['codigo'] ?? ''));

            if (empty($codigo) || empty($data['tipo']) || !isset($data['valor'])) {
                return $rh->setResponse(false, 'Código, tipo y valor son requeridos');
            }

            if ($this->model->where('codigo', $codigo)->exists()) {
                return $rh->setResponse(false, "El código {$codigo} ya existe");
            }

            $cupon              = new Cupon();
            $cupon->codigo      = $codigo;
            $cupon->descripcion = trim($data['descripcion'] ?? '');
            $cupon->tipo        = $data['tipo'];
            $cupon->valor       = (float)$data['valor'];
            $cupon->monto_minimo = (float)($data['monto_minimo'] ?? 0);
            $cupon->usos_max    = !empty($data['usos_max']) ? (int)$data['usos_max'] : null;
            $cupon->fecha_inicio = !empty($data['fecha_inicio']) ? $data['fecha_inicio'] : null;
            $cupon->fecha_fin   = !empty($data['fecha_fin']) ? $data['fecha_fin'] : null;
            $cupon->activo      = 1;
            $cupon->save();

            AuditoriaService::registrar('cupones', 'crear', "Cupón {$codigo}", $cupon->id);

            $rh->setResponse(true, "Cupón {$codigo} creado correctamente");
            $rh->result = ['id' => $cupon->id];
        } catch (Exception $e) {
            Log::error(CuponRepository::class, $e->getMessage());
            $rh->setResponse(false, 'No se pudo crear el cupón');
        }

        return $rh;
    }

    public function desactivar(int $id): ResponseHelper
    {
        $rh = new ResponseHelper();

        try {
            $cupon         = $this->model->findOrFail($id);
            $cupon->activo = 0;
            $cupon->save();

            AuditoriaService::registrar('cupones', 'desactivar', "Cupón {$cupon->codigo}", $id);

            $rh->setResponse(true, "Cupón {$cupon->codigo} desactivado");
        } catch (Exception $e) {
            Log::error(CuponRepository::class, $e->getMessage());
            $rh->setResponse(false, 'No se pudo desactivar el cupón');
        }

        return $rh;
    }
}
