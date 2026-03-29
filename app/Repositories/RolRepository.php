<?php
/*
    autor: Noe Cazarez Camargo
    fecha: 2019-06-24
    descripcion: REPOSITORIO DE ROLES
*/
namespace App\Repositories;

use App\Models\Rol;
use Core\Log;
use Exception;
use Illuminate\Database\Eloquent\Collection;

class RolRepository
{
    private $model;

    public function __construct()
    {
        $this->model = new Rol();
    }

    public function listar(): Collection
    {
        try {
            return $this->model->orderBy('nombre')->get();
        } catch (Exception $e) {
            Log::error(RolRepository::class, $e->getMessage());
            return collect();
        }
    }

    public function obtener(int $id): Rol
    {
        try {
            return $this->model->findOrFail($id);
        } catch (Exception $e) {
            Log::error(RolRepository::class, $e->getMessage());
            return new Rol();
        }
    }
}