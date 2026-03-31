<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Models\Empleado;
use App\Repositories\{EmpleadoRepository, RolRepository};
use App\Validations\EmpleadoValidation;
use Core\{Controller, Log};

class EmpleadoController extends Controller
{
    private $empleadoRepo;
    private $rolRepo;

    public function __construct()
    {
        parent::__construct();
        $this->empleadoRepo = new EmpleadoRepository();
        $this->rolRepo      = new RolRepository();
    }

    // ── Listado ───────────────────────────────────────────────
    public function getIndex()
    {
        $pagina   = (int)($_GET['pagina'] ?? 1);
        $turno    = $_GET['turno'] ?? null;
        $busqueda = $_GET['busqueda'] ?? null;

        $resultado = $this->empleadoRepo->listar($pagina, 15, $turno ?: null, $busqueda ?: null);

        return $this->render('empleados/index.twig', [
            'title'       => 'Empleados',
            'datos'       => $resultado['datos'],
            'total'       => $resultado['total'],
            'pagina'      => $resultado['pagina'],
            'total_pages' => $resultado['total_pages'],
            'turno'       => $turno,
            'busqueda'    => $busqueda,
        ]);
    }

    // ── Formulario nuevo / editar ─────────────────────────────
    public function getFormulario(int $id = 0)
    {
        $empleado = $id ? $this->empleadoRepo->obtener($id) : new Empleado();
        $roles    = $this->rolRepo->listar();

        return $this->render('empleados/formulario.twig', [
            'title'    => $id ? 'Editar empleado' : 'Nuevo empleado',
            'empleado' => $empleado,
            'roles'    => $roles,
        ]);
    }

    // ── Guardar ───────────────────────────────────────────────
    public function postGuardar()
    {
        EmpleadoValidation::validar($_POST);

        $rh = $this->empleadoRepo->guardar($_POST);

        if ($rh->response) {
            $rh->href = 'empleados';
        }

        echo json_encode($rh);
    }

    // ── Desactivar ────────────────────────────────────────────
    public function postDesactivar()
    {
        $rh = $this->empleadoRepo->desactivar((int)$_POST['id']);
        echo json_encode($rh);
    }

    // ── Historial de asistencia ───────────────────────────────
    public function getAsistencia(int $id)
    {
        $empleado  = $this->empleadoRepo->obtener($id);
        $mes       = $_GET['mes'] ?? date('Y-m');
        $historial = $this->empleadoRepo->historialAsistencia($id, $mes);

        return $this->render('empleados/asistencia.twig', [
            'title'     => 'Asistencia — ' . ($empleado->usuario->nombre ?? ''),
            'empleado'  => $empleado,
            'historial' => $historial,
            'mes'       => $mes,
        ]);
    }

    // ── Registrar asistencia (Ajax) ───────────────────────────
    public function postAsistencia()
    {
        $rh = $this->empleadoRepo->registrarAsistencia($_POST);
        echo json_encode($rh);
    }
}