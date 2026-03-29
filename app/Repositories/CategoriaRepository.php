<?php
namespace App\Repositories;

use App\Models\Categoria;
use Core\Log;
use Exception;
use Illuminate\Database\Eloquent\Collection;

class CategoriaRepository
{
    private $model;

    public function __construct()
    {
        $this->model = new Categoria();
    }

    public function listar(): Collection
    {
        try {
            return $this->model->orderBy('nombre')->get();
        } catch (Exception $e) {
            Log::error(CategoriaRepository::class, $e->getMessage());
            return collect();
        }
    }

    public function obtener(int $id): Categoria
    {
        try {
            return $this->model->findOrFail($id);
        } catch (Exception $e) {
            Log::error(CategoriaRepository::class, $e->getMessage());
            return new Categoria();
        }
    }
}