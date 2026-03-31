<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Helpers\ResponseHelper;
use App\Models\{Permiso, Rol};
use App\Services\CacheService;
use Core\Log;
use Exception;
use Illuminate\Database\Capsule\Manager as Capsule;

class PermisoRepository
{
    public function listarRolesConPermisos(): array
    {
        try {
            $roles    = Rol::with('permisos')->where('id', '<', 5)->get();
            $permisos = Permiso::orderBy('modulo')->orderBy('slug')->get()->groupBy('modulo');
            return ['roles' => $roles, 'permisos' => $permisos];
        } catch (Exception $e) {
            Log::error(self::class, $e->getMessage());
            return ['roles' => collect(), 'permisos' => collect()];
        }
    }

    public function obtenerPermisosDeRol(int $rolId): array
    {
        $cacheKey = "permisos_rol_{$rolId}";

        return CacheService::remember($cacheKey, 300, function () use ($rolId) {
            try {
                $rol = Rol::find($rolId);
                if (!$rol) return [];
                return $rol->permisos()->pluck('slug')->toArray();
            } catch (Exception $e) {
                Log::error(self::class, $e->getMessage());
                return [];
            }
        });
    }

    public function sincronizarPermisos(int $rolId, array $permisosIds): ResponseHelper
    {
        $rh = new ResponseHelper();
        try {
            Capsule::transaction(function () use ($rolId, $permisosIds) {
                $rol = Rol::findOrFail($rolId);
                $rol->permisos()->sync($permisosIds);
                CacheService::forget("permisos_rol_{$rolId}");
            });
            $rh->setResponse(true, 'Permisos actualizados correctamente');
        } catch (Exception $e) {
            Log::error(self::class, $e->getMessage());
            $rh->setResponse(false, 'No se pudieron actualizar los permisos');
        }
        return $rh;
    }
}
