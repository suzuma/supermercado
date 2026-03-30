<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\{AuditoriaRepository, UsuarioRepository};
use Core\Controller;

class AuditoriaController extends Controller
{
    private AuditoriaRepository $repo;
    private UsuarioRepository $usuarioRepo;

    public function __construct()
    {
        parent::__construct();
        $this->repo        = new AuditoriaRepository();
        $this->usuarioRepo = new UsuarioRepository();
    }

    public function getIndex(): string
    {
        $filtros = [
            'modulo'      => $_GET['modulo']      ?? '',
            'usuario_id'  => $_GET['usuario_id']  ?? '',
            'fecha_desde' => $_GET['fecha_desde'] ?? '',
            'fecha_hasta' => $_GET['fecha_hasta'] ?? '',
        ];

        $pagina = max(1, (int) ($_GET['page'] ?? 1));
        $data   = $this->repo->listar($filtros, $pagina);

        return $this->render('auditoria/index.twig', array_merge($data, [
            'title'    => 'Registro de Auditoría',
            'filtros'  => $filtros,
            'modulos'  => $this->repo->modulos(),
            'usuarios' => $this->usuarioRepo->listar(),
        ]));
    }
}
