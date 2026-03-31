<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\CuponRepository;
use Core\Controller;

class CuponesController extends Controller
{
    private CuponRepository $repo;

    public function __construct()
    {
        parent::__construct();
        $this->repo = new CuponRepository();
    }

    public function getIndex(): string
    {
        return $this->render('cupones/index.twig', [
            'title'   => 'Cupones de descuento',
            'cupones' => $this->repo->listar(),
        ]);
    }

    public function postGuardar(): void
    {
        echo json_encode($this->repo->guardar($_POST));
    }

    public function postDesactivar(): void
    {
        $id = (int)($_POST['id'] ?? 0);
        echo json_encode($this->repo->desactivar($id));
    }
}
