<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Cliente;
use App\Repositories\ClienteRepository;
use Core\{Controller, Log};

class ClientesController extends Controller
{
    private $repo;

    public function __construct()
    {
        parent::__construct();
        $this->repo = new ClienteRepository();
    }

    // ── Listado ───────────────────────────────────────────
    public function getIndex()
    {
        $pagina   = (int)($_GET['pagina'] ?? 1);
        $busqueda = $_GET['busqueda'] ?? null;
        $filtro   = $_GET['filtro'] ?? 'todos';

        $resultado = $this->repo->listar($pagina, 15, $busqueda ?: null, $filtro);

        return $this->render('clientes/index.twig', [
            'title'       => 'Clientes',
            'datos'       => $resultado['datos'],
            'total'       => $resultado['total'],
            'pagina'      => $resultado['pagina'],
            'total_pages' => $resultado['total_pages'],
            'busqueda'    => $busqueda,
            'filtro'      => $filtro,
        ]);
    }

    // ── Formulario nuevo / editar ─────────────────────────
    public function getFormulario(int $id = 0)
    {
        $model = $id ? $this->repo->obtener($id) : new Cliente();

        return $this->render('clientes/formulario.twig', [
            'title' => $id ? 'Editar cliente' : 'Nuevo cliente',
            'model' => $model,
        ]);
    }

    // ── Guardar ───────────────────────────────────────────
    public function postGuardar()
    {
        $model                   = new Cliente();
        $model->id               = $_POST['id'] ?? null;
        $model->nombre           = $_POST['nombre'] ?? '';
        $model->apellido         = $_POST['apellido'] ?? '';
        $model->email            = $_POST['email'] ?? '';
        $model->telefono         = $_POST['telefono'] ?? '';
        $model->direccion        = $_POST['direccion'] ?? '';
        $model->fecha_nacimiento = $_POST['fecha_nacimiento'] ?? null;
        $model->rfc              = $_POST['rfc'] ?? '';
        $model->password         = $_POST['password'] ?? '';

        if (empty($model->nombre) || empty($model->apellido) || empty($model->email)) {
            echo json_encode(['response' => false, 'message' => 'Nombre, apellido y correo son requeridos']);
            return;
        }

        if (!filter_var($model->email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['response' => false, 'message' => 'El correo no tiene un formato válido']);
            return;
        }

        $rh = $this->repo->guardar($model);

        if ($rh->response) {
            $rh->href = 'clientes';
        }

        echo json_encode($rh);
    }

    // ── Desactivar ────────────────────────────────────────
    public function postDesactivar()
    {
        $rh = $this->repo->desactivar((int)$_POST['id']);
        echo json_encode($rh);
    }

    // ── Perfil del cliente ────────────────────────────────
    public function getPerfil(int $id)
    {
        $cliente      = $this->repo->obtener($id);
        $estadisticas = $this->repo->estadisticas($id);
        $ventas       = $this->repo->historialVentas($id);
        $pedidos      = $this->repo->pedidosCliente($id);

        return $this->render('clientes/perfil.twig', [
            'title'        => $cliente->nombre_completo,
            'cliente'      => $cliente,
            'estadisticas' => $estadisticas,
            'ventas'       => $ventas,
            'pedidos'      => $pedidos,
        ]);
    }
}