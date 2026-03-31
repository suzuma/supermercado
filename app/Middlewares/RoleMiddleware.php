<?php
declare(strict_types=1);

namespace App\Middlewares;

use App\Repositories\PermisoRepository;
use Core\Auth;

class RoleMiddleware
{
    public static function isAdmin(): bool
    {
        return Auth::getCurrentUser()->rol_id === 1;
    }

    public static function isSeller(): bool
    {
        $rolId = Auth::getCurrentUser()->rol_id;
        return $rolId === 1 || $rolId === 2;
    }

    public static function isAnalyst(): bool
    {
        $rolId = Auth::getCurrentUser()->rol_id;
        return $rolId === 1 || $rolId === 3;
    }

    public static function can(string $slug): bool
    {
        $user = Auth::getCurrentUser();

        if ($user->rol_id === 1) {
            return true;
        }

        $repo     = new PermisoRepository();
        $permisos = $repo->obtenerPermisosDeRol((int)$user->rol_id);

        return in_array($slug, $permisos, true);
    }
}
