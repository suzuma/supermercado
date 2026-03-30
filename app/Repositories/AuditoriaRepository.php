<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Models\Auditoria;
use Core\Log;
use Exception;

class AuditoriaRepository
{
    private Auditoria $model;

    public function __construct()
    {
        $this->model = new Auditoria();
    }

    public function listar(array $filtros = [], int $pagina = 1, int $limite = 50): array
    {
        try {
            $query = $this->model
                ->with('usuario')
                ->orderBy('created_at', 'desc');

            if (!empty($filtros['modulo'])) {
                $query->where('modulo', $filtros['modulo']);
            }

            if (!empty($filtros['usuario_id'])) {
                $query->where('usuario_id', (int) $filtros['usuario_id']);
            }

            if (!empty($filtros['fecha_desde'])) {
                $query->whereDate('created_at', '>=', $filtros['fecha_desde']);
            }

            if (!empty($filtros['fecha_hasta'])) {
                $query->whereDate('created_at', '<=', $filtros['fecha_hasta']);
            }

            $total  = $query->count();
            $offset = ($pagina - 1) * $limite;

            $registros = $query->skip($offset)->take($limite)->get();

            return [
                'registros'   => $registros,
                'total'       => $total,
                'pagina'      => $pagina,
                'total_pages' => (int) ceil($total / $limite),
            ];
        } catch (Exception $e) {
            Log::error(self::class, $e->getMessage());
            return ['registros' => collect(), 'total' => 0, 'pagina' => 1, 'total_pages' => 1];
        }
    }

    public function modulos(): array
    {
        try {
            return $this->model
                ->select('modulo')
                ->distinct()
                ->orderBy('modulo')
                ->pluck('modulo')
                ->toArray();
        } catch (Exception $e) {
            Log::error(self::class, $e->getMessage());
            return [];
        }
    }
}
