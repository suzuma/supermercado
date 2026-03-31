<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Repositories\CorteCajaRepository;
use Core\{Auth, Controller, Log};

class CorteCajaController extends Controller
{
    private $repo;

    public function __construct()
    {
        parent::__construct();
        $this->repo = new CorteCajaRepository();
    }

    // ── Formulario de corte (muestra pendientes) ──────────────
    public function getIndex()
    {
        $pendientes = $this->repo->calcularPendientes();

        return $this->render('ventas/corte.twig', [
            'title'      => 'Corte de caja',
            'pendientes' => $pendientes,
        ]);
    }

    // ── Historial de cortes ───────────────────────────────────
    public function getHistorial()
    {
        $pagina    = (int)($_GET['pagina'] ?? 1);
        $resultado = $this->repo->listar($pagina);

        return $this->render('ventas/cortes.twig', [
            'title'       => 'Historial de cortes',
            'datos'       => $resultado['datos'],
            'total'       => $resultado['total'],
            'pagina'      => $resultado['pagina'],
            'total_pages' => $resultado['total_pages'],
        ]);
    }

    // ── Detalle de un corte ───────────────────────────────────
    public function getDetalle(int $id)
    {
        $corte = $this->repo->obtener($id);

        return $this->render('ventas/corte_detalle.twig', [
            'title' => 'Corte #' . str_pad($id, 5, '0', STR_PAD_LEFT),
            'corte' => $corte,
        ]);
    }

    // ── Registrar corte (Ajax) ────────────────────────────────
    public function postRegistrar()
    {
        $rh = new ResponseHelper();

        try {
            $fondoInicial     = (float)($_POST['fondo_inicial']    ?? 0);
            $efectivoContado  = (float)($_POST['efectivo_contado'] ?? 0);
            $obs              = trim($_POST['observaciones']        ?? '');

            if ($fondoInicial < 0 || $efectivoContado < 0) {
                echo json_encode($rh->setResponse(false, 'Los montos no pueden ser negativos'));
                return;
            }

            $usuarioId = Auth::getCurrentUser()->id;
            $rh        = $this->repo->registrar($fondoInicial, $efectivoContado, $obs, $usuarioId);

            if ($rh->response) {
                $rh->href = 'corte-caja/detalle/' . $rh->result['corte_id'];
            }
        } catch (\Exception $e) {
            Log::error(self::class, $e->getMessage());
            $rh->setResponse(false, 'No se pudo registrar el corte');
        }

        echo json_encode($rh);
    }
}