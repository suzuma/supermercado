<?php
namespace App\Controllers;

use App\Repositories\ReporteRepository;
use Core\{Controller, Log};
use Dompdf\Dompdf;
use Dompdf\Options;

class ReportesController extends Controller
{
    private $repo;

    public function __construct()
    {
        parent::__construct();
        $this->repo = new ReporteRepository();
    }

    // ── Dashboard de reportes ─────────────────────────────────
    public function getIndex()
    {
        $desde = $_GET['desde'] ?? date('Y-m-01');
        $hasta = $_GET['hasta'] ?? date('Y-m-d');

        // Agregar estas dos líneas
        $hace7dias   = date('Y-m-d', strtotime('-7 days'));
        $inicioMes   = date('Y-m-01');

        $ventas       = $this->repo->ventasPorPeriodo($desde, $hasta);
        $formaPago    = $this->repo->ventasPorFormaPago($desde, $hasta);
        $masVendidos  = $this->repo->productosMasVendidos($desde, $hasta);
        $inventario   = $this->repo->inventario();
        $empleados    = $this->repo->empleados();
        $devoluciones = $this->repo->devoluciones($desde, $hasta);

        return $this->render('reportes/index.twig', [
            'title'        => 'Reportes',
            'desde'        => $desde,
            'hasta'        => $hasta,
            'ventas'       => $ventas,
            'forma_pago'   => $formaPago,
            'mas_vendidos' => $masVendidos,
            'inventario'   => $inventario,
            'empleados'    => $empleados,
            'devoluciones' => $devoluciones,
            'hace7dias'    => $hace7dias,
            'inicio_mes'   => $inicioMes,
        ]);
    }

    // ── Reporte de utilidades ─────────────────────────────────
    public function getUtilidades()
    {
        $desde     = $_GET['desde'] ?? date('Y-m-01');
        $hasta     = $_GET['hasta'] ?? date('Y-m-d');
        $hace7dias = date('Y-m-d', strtotime('-7 days'));
        $inicioMes = date('Y-m-01');

        $datos = $this->repo->utilidades($desde, $hasta);

        return $this->render('reportes/utilidades.twig', array_merge($datos, [
            'title'      => 'Reporte de utilidades',
            'desde'      => $desde,
            'hasta'      => $hasta,
            'hace7dias'  => $hace7dias,
            'inicio_mes' => $inicioMes,
        ]));
    }

    // ── Exportar CSV utilidades ───────────────────────────────
    public function getCsvUtilidades()
    {
        $desde = $_GET['desde'] ?? date('Y-m-01');
        $hasta = $_GET['hasta'] ?? date('Y-m-d');
        $datos = $this->repo->utilidades($desde, $hasta);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="utilidades_' . $desde . '_' . $hasta . '.csv"');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($out, ['Producto', 'Categoría', 'Unidades', 'Ingresos', 'Costos', 'Utilidad', 'Margen %']);
        foreach ($datos['productos'] as $p) {
            fputcsv($out, [
                $p->nombre,
                $p->categoria,
                $p->unidades,
                number_format($p->ingresos, 2),
                number_format($p->costos,   2),
                number_format($p->utilidad, 2),
                number_format($p->margen,   1) . '%',
            ]);
        }
        fputcsv($out, []);
        fputcsv($out, ['TOTAL', '', '',
            number_format($datos['total_ingresos'], 2),
            number_format($datos['total_costos'],   2),
            number_format($datos['total_utilidad'], 2),
            number_format($datos['margen_pct'],     1) . '%',
        ]);
        fclose($out);
        exit;
    }

    // ── Exportar PDF ventas ───────────────────────────────────
    public function getPdfVentas()
    {
        $desde = $_GET['desde'] ?? date('Y-m-01');
        $hasta = $_GET['hasta'] ?? date('Y-m-d');

        $ventas      = $this->repo->ventasPorPeriodo($desde, $hasta);
        $formaPago   = $this->repo->ventasPorFormaPago($desde, $hasta);
        $masVendidos = $this->repo->productosMasVendidos($desde, $hasta);

        $html = $this->render('reportes/pdf_ventas.twig', [
            'desde'        => $desde,
            'hasta'        => $hasta,
            'ventas'       => $ventas,
            'forma_pago'   => $formaPago,
            'mas_vendidos' => $masVendidos,
        ]);

        $this->generarPdf($html, "reporte_ventas_{$desde}_{$hasta}.pdf");
    }

    // ── Exportar PDF inventario ───────────────────────────────
    public function getPdfInventario()
    {
        $inventario = $this->repo->inventario();

        $html = $this->render('reportes/pdf_inventario.twig', [
            'inventario' => $inventario,
            'fecha'      => date('d/m/Y H:i'),
        ]);

        $this->generarPdf($html, 'reporte_inventario_' . date('Ymd') . '.pdf');
    }

    // ── Exportar PDF empleados ────────────────────────────────
    public function getPdfEmpleados()
    {
        $empleados = $this->repo->empleados();

        $html = $this->render('reportes/pdf_empleados.twig', [
            'empleados' => $empleados,
            'fecha'     => date('d/m/Y H:i'),
        ]);

        $this->generarPdf($html, 'reporte_empleados_' . date('Ymd') . '.pdf');
    }

    // ── Exportar Excel ventas (CSV) ───────────────────────────
    public function getCsvVentas()
    {
        $desde = $_GET['desde'] ?? date('Y-m-01');
        $hasta = $_GET['hasta'] ?? date('Y-m-d');

        $ventas = $this->repo->ventasPorPeriodo($desde, $hasta);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="ventas_' . $desde . '_' . $hasta . '.csv"');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8

        fputcsv($out, ['Fecha', 'Número de ventas', 'Monto total', 'Promedio']);
        foreach ($ventas['datos'] as $row) {
            fputcsv($out, [
                $row->fecha,
                $row->total_ventas,
                number_format($row->monto_total, 2),
                number_format($row->promedio, 2),
            ]);
        }
        fputcsv($out, ['TOTAL', $ventas['total_ventas'], number_format($ventas['total_monto'], 2), '']);
        fclose($out);
        exit;
    }

    // ── Exportar Excel inventario (CSV) ───────────────────────
    public function getCsvInventario()
    {
        $inventario = $this->repo->inventario();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="inventario_' . date('Ymd') . '.csv"');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($out, ['Producto', 'Categoría', 'Proveedor', 'Precio compra', 'Precio venta', 'Margen', 'Stock', 'Stock mínimo', 'Estado']);
        foreach ($inventario['productos'] as $p) {
            fputcsv($out, [
                $p->nombre,
                $p->categoria,
                $p->proveedor ?? '—',
                number_format($p->precio_compra, 2),
                number_format($p->precio_venta, 2),
                number_format($p->margen, 2),
                $p->stock,
                $p->stock_minimo,
                $p->estado_stock,
            ]);
        }
        fclose($out);
        exit;
    }

    // ── Helper: generar PDF con dompdf ────────────────────────
    private function generarPdf(string $html, string $filename): void
    {
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $dompdf->stream($filename, ['Attachment' => true]);
        exit;
    }
}