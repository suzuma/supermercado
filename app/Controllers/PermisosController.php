<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Repositories\PermisoRepository;
use Core\{Controller, Log};

class PermisosController extends Controller
{
    private PermisoRepository $repo;

    public function __construct()
    {
        parent::__construct();
        $this->repo = new PermisoRepository();
    }

    public function getIndex(): string
    {
        $data = $this->repo->listarRolesConPermisos();

        return $this->render('permisos/index.twig', array_merge($data, [
            'title' => 'Gestión de Permisos',
        ]));
    }

    public function postGuardar(): void
    {
        $rh    = new ResponseHelper();
        $rolId = (int)($_POST['rol_id'] ?? 0);

        if ($rolId <= 0) {
            echo json_encode($rh->setResponse(false, 'Rol no válido'));
            return;
        }

        if ($rolId === 1) {
            echo json_encode($rh->setResponse(false, 'Los permisos del Administrador no se pueden modificar'));
            return;
        }

        $permisosIds = array_map('intval', $_POST['permisos'] ?? []);

        echo json_encode($this->repo->sincronizarPermisos($rolId, $permisosIds));
    }
}
