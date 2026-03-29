<?php
namespace App\Repositories;

use Core\Log;
use Exception;
use Illuminate\Database\Capsule\Manager as DB;

class ReporteRepository
{
    // ── Ventas por período ────────────────────────────────────
    public function ventasPorPeriodo(string $desde, string $hasta): array
    {
        try {
            $ventas = DB::table('ventas')
                ->whereDate('created_at', '>=', $desde)
                ->whereDate('created_at', '<=', $hasta)
                ->where('estado', 'completada')
                ->selectRaw('
                    DATE(created_at) as fecha,
                    COUNT(*) as total_ventas,
                    SUM(total) as monto_total,
                    AVG(total) as promedio
                ')
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('fecha')
                ->get();

            $totalMonto  = $ventas->sum('monto_total');
            $totalVentas = $ventas->sum('total_ventas');

            return [
                'datos'        => $ventas,
                'total_monto'  => $totalMonto,
                'total_ventas' => $totalVentas,
                'promedio'     => $totalVentas > 0 ? $totalMonto / $totalVentas : 0,
                'labels'       => $ventas->pluck('fecha')->toArray(),
                'montos'       => $ventas->pluck('monto_total')->toArray(),
            ];
        } catch (Exception $e) {
            Log::error(ReporteRepository::class, $e->getMessage());
            return ['datos' => collect(), 'total_monto' => 0, 'total_ventas' => 0, 'promedio' => 0, 'labels' => [], 'montos' => []];
        }
    }

    // ── Ventas por forma de pago ──────────────────────────────
    public function ventasPorFormaPago(string $desde, string $hasta): array
    {
        try {
            return DB::table('ventas')
                ->whereDate('created_at', '>=', $desde)
                ->whereDate('created_at', '<=', $hasta)
                ->where('estado', 'completada')
                ->selectRaw('tipo, COUNT(*) as cantidad, SUM(total) as monto')
                ->groupBy('tipo')
                ->get()
                ->toArray();
        } catch (Exception $e) {
            Log::error(ReporteRepository::class, $e->getMessage());
            return [];
        }
    }

    // ── Productos más vendidos ────────────────────────────────
    public function productosMasVendidos(string $desde, string $hasta, int $limite = 10): array
    {
        try {
            return DB::table('venta_detalles as vd')
                ->join('ventas as v',    'v.id',  '=', 'vd.venta_id')
                ->join('productos as p', 'p.id',  '=', 'vd.producto_id')
                ->join('categorias as c','c.id',  '=', 'p.categoria_id')
                ->whereDate('v.created_at', '>=', $desde)
                ->whereDate('v.created_at', '<=', $hasta)
                ->where('v.estado', 'completada')
                ->selectRaw('
                    p.id,
                    p.nombre,
                    c.nombre as categoria,
                    p.precio_venta,
                    SUM(vd.cantidad) as unidades_vendidas,
                    SUM(vd.subtotal) as ingreso_total
                ')
                ->groupBy('p.id', 'p.nombre', 'c.nombre', 'p.precio_venta')
                ->orderByDesc('unidades_vendidas')
                ->limit($limite)
                ->get()
                ->toArray();
        } catch (Exception $e) {
            Log::error(ReporteRepository::class, $e->getMessage());
            return [];
        }
    }

    // ── Reporte de utilidades ─────────────────────────────────
    public function utilidades(string $desde, string $hasta): array
    {
        try {
            // Período anterior de igual duración para comparativa
            $dias     = (new \DateTime($desde))->diff(new \DateTime($hasta))->days + 1;
            $desdeAnt = date('Y-m-d', strtotime("$desde -$dias days"));
            $hastaAnt = date('Y-m-d', strtotime("$hasta -$dias days"));

            // Ingresos, costos y utilidad por día
            $porDia = DB::table('venta_detalles as vd')
                ->join('ventas as v',    'v.id', '=', 'vd.venta_id')
                ->join('productos as p', 'p.id', '=', 'vd.producto_id')
                ->whereDate('v.created_at', '>=', $desde)
                ->whereDate('v.created_at', '<=', $hasta)
                ->where('v.estado', 'completada')
                ->selectRaw('
                    DATE(v.created_at)                                    as fecha,
                    SUM(vd.subtotal)                                      as ingresos,
                    SUM(vd.cantidad * p.precio_compra)                    as costos,
                    SUM(vd.subtotal) - SUM(vd.cantidad * p.precio_compra) as utilidad
                ')
                ->groupBy(DB::raw('DATE(v.created_at)'))
                ->orderBy('fecha')
                ->get();

            $totalIngresos = round((float)$porDia->sum('ingresos'), 2);
            $totalCostos   = round((float)$porDia->sum('costos'),   2);
            $totalUtilidad = round($totalIngresos - $totalCostos,   2);
            $margenPct     = $totalIngresos > 0
                ? round(($totalUtilidad / $totalIngresos) * 100, 1)
                : 0;

            // Totales del período anterior
            $ant = DB::table('venta_detalles as vd')
                ->join('ventas as v',    'v.id', '=', 'vd.venta_id')
                ->join('productos as p', 'p.id', '=', 'vd.producto_id')
                ->whereDate('v.created_at', '>=', $desdeAnt)
                ->whereDate('v.created_at', '<=', $hastaAnt)
                ->where('v.estado', 'completada')
                ->selectRaw('
                    SUM(vd.subtotal)                   as ingresos,
                    SUM(vd.cantidad * p.precio_compra) as costos
                ')
                ->first();

            $ingresosAnt  = round((float)($ant->ingresos ?? 0), 2);
            $costosAnt    = round((float)($ant->costos   ?? 0), 2);
            $utilidadAnt  = round($ingresosAnt - $costosAnt,    2);
            $margenAnt    = $ingresosAnt > 0
                ? round(($utilidadAnt / $ingresosAnt) * 100, 1)
                : 0;

            // Productos más rentables
            $productos = DB::table('venta_detalles as vd')
                ->join('ventas as v',    'v.id', '=', 'vd.venta_id')
                ->join('productos as p', 'p.id', '=', 'vd.producto_id')
                ->join('categorias as c','c.id', '=', 'p.categoria_id')
                ->whereDate('v.created_at', '>=', $desde)
                ->whereDate('v.created_at', '<=', $hasta)
                ->where('v.estado', 'completada')
                ->selectRaw('
                    p.id,
                    p.nombre,
                    c.nombre                                                           as categoria,
                    SUM(vd.cantidad)                                                   as unidades,
                    SUM(vd.subtotal)                                                   as ingresos,
                    SUM(vd.cantidad * p.precio_compra)                                 as costos,
                    SUM(vd.subtotal) - SUM(vd.cantidad * p.precio_compra)              as utilidad,
                    CASE WHEN SUM(vd.subtotal) > 0
                        THEN ((SUM(vd.subtotal) - SUM(vd.cantidad * p.precio_compra))
                              / SUM(vd.subtotal)) * 100
                        ELSE 0 END                                                     as margen
                ')
                ->groupBy('p.id', 'p.nombre', 'c.nombre')
                ->orderByDesc('utilidad')
                ->limit(15)
                ->get();

            // Utilidad por categoría
            $categorias = DB::table('venta_detalles as vd')
                ->join('ventas as v',    'v.id', '=', 'vd.venta_id')
                ->join('productos as p', 'p.id', '=', 'vd.producto_id')
                ->join('categorias as c','c.id', '=', 'p.categoria_id')
                ->whereDate('v.created_at', '>=', $desde)
                ->whereDate('v.created_at', '<=', $hasta)
                ->where('v.estado', 'completada')
                ->selectRaw('
                    c.nombre                                                           as categoria,
                    SUM(vd.subtotal)                                                   as ingresos,
                    SUM(vd.cantidad * p.precio_compra)                                 as costos,
                    SUM(vd.subtotal) - SUM(vd.cantidad * p.precio_compra)              as utilidad,
                    CASE WHEN SUM(vd.subtotal) > 0
                        THEN ((SUM(vd.subtotal) - SUM(vd.cantidad * p.precio_compra))
                              / SUM(vd.subtotal)) * 100
                        ELSE 0 END                                                     as margen
                ')
                ->groupBy('c.id', 'c.nombre')
                ->orderByDesc('utilidad')
                ->get();

            return [
                'por_dia'        => $porDia,
                'total_ingresos' => $totalIngresos,
                'total_costos'   => $totalCostos,
                'total_utilidad' => $totalUtilidad,
                'margen_pct'     => $margenPct,
                'ingresos_ant'   => $ingresosAnt,
                'costos_ant'     => $costosAnt,
                'utilidad_ant'   => $utilidadAnt,
                'margen_ant'     => $margenAnt,
                'productos'      => $productos,
                'categorias'     => $categorias,
                'labels'         => $porDia->pluck('fecha')->toArray(),
                'datos_ingresos' => $porDia->pluck('ingresos')->map(fn($v) => round((float)$v, 2))->toArray(),
                'datos_costos'   => $porDia->pluck('costos')->map(fn($v) => round((float)$v, 2))->toArray(),
                'datos_utilidad' => $porDia->pluck('utilidad')->map(fn($v) => round((float)$v, 2))->toArray(),
                'desde_ant'      => $desdeAnt,
                'hasta_ant'      => $hastaAnt,
            ];
        } catch (Exception $e) {
            Log::error(ReporteRepository::class, $e->getMessage());
            return [
                'por_dia' => collect(), 'total_ingresos' => 0, 'total_costos' => 0,
                'total_utilidad' => 0, 'margen_pct' => 0, 'ingresos_ant' => 0,
                'costos_ant' => 0, 'utilidad_ant' => 0, 'margen_ant' => 0,
                'productos' => collect(), 'categorias' => collect(),
                'labels' => [], 'datos_ingresos' => [], 'datos_costos' => [], 'datos_utilidad' => [],
                'desde_ant' => '', 'hasta_ant' => '',
            ];
        }
    }

    // ── Reporte de devoluciones ───────────────────────────────
    public function devoluciones(string $desde, string $hasta): array
    {
        try {
            $porDia = DB::table('devoluciones')
                ->whereDate('created_at', '>=', $desde)
                ->whereDate('created_at', '<=', $hasta)
                ->selectRaw('
                    DATE(created_at) as fecha,
                    COUNT(*) as total_devoluciones,
                    SUM(total_devuelto) as monto_total
                ')
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('fecha')
                ->get();

            $totalMonto       = round((float)$porDia->sum('monto_total'), 2);
            $totalDevoluciones = (int)$porDia->sum('total_devoluciones');

            $ventasMonto = (float) DB::table('ventas')
                ->whereDate('created_at', '>=', $desde)
                ->whereDate('created_at', '<=', $hasta)
                ->where('estado', 'completada')
                ->sum('total');

            $tasaDevolucion = $ventasMonto > 0
                ? round(($totalMonto / $ventasMonto) * 100, 1)
                : 0;

            $masDevueltos = DB::table('devolucion_detalles as dd')
                ->join('devoluciones as d',  'd.id',  '=', 'dd.devolucion_id')
                ->join('productos as p',     'p.id',  '=', 'dd.producto_id')
                ->join('categorias as c',    'c.id',  '=', 'p.categoria_id')
                ->whereDate('d.created_at', '>=', $desde)
                ->whereDate('d.created_at', '<=', $hasta)
                ->selectRaw('
                    p.id,
                    p.nombre,
                    c.nombre as categoria,
                    SUM(dd.cantidad)  as unidades_devueltas,
                    SUM(dd.subtotal)  as monto_devuelto
                ')
                ->groupBy('p.id', 'p.nombre', 'c.nombre')
                ->orderByDesc('unidades_devueltas')
                ->limit(10)
                ->get();

            return [
                'por_dia'           => $porDia,
                'total_monto'       => $totalMonto,
                'total_devoluciones' => $totalDevoluciones,
                'tasa_devolucion'   => $tasaDevolucion,
                'mas_devueltos'     => $masDevueltos,
                'labels'            => $porDia->pluck('fecha')->toArray(),
                'montos'            => $porDia->pluck('monto_total')->map(fn($v) => round((float)$v, 2))->toArray(),
            ];
        } catch (Exception $e) {
            Log::error(ReporteRepository::class, $e->getMessage());
            return [
                'por_dia' => collect(), 'total_monto' => 0, 'total_devoluciones' => 0,
                'tasa_devolucion' => 0, 'mas_devueltos' => collect(),
                'labels' => [], 'montos' => [],
            ];
        }
    }

    // ── Reporte de inventario ─────────────────────────────────
    public function inventario(): array
    {
        try {
            $productos = DB::table('productos as p')
                ->join('categorias as c', 'c.id', '=', 'p.categoria_id')
                ->leftJoin('proveedores as pv', 'pv.id', '=', 'p.proveedor_id')
                ->where('p.activo', 1)
                ->selectRaw('
                    p.id,
                    p.nombre,
                    c.nombre as categoria,
                    pv.nombre as proveedor,
                    p.precio_compra,
                    p.precio_venta,
                    p.stock,
                    p.stock_minimo,
                    (p.precio_venta - p.precio_compra) as margen,
                    CASE
                        WHEN p.stock = 0 THEN "agotado"
                        WHEN p.stock <= p.stock_minimo THEN "bajo"
                        ELSE "ok"
                    END as estado_stock
                ')
                ->orderBy('c.nombre')
                ->orderBy('p.nombre')
                ->get();

            $valorTotal = $productos->sum(fn($p) => $p->stock * $p->precio_compra);
            $agotados   = $productos->where('estado_stock', 'agotado')->count();
            $stockBajo  = $productos->where('estado_stock', 'bajo')->count();

            return [
                'productos'   => $productos,
                'valor_total' => $valorTotal,
                'agotados'    => $agotados,
                'stock_bajo'  => $stockBajo,
                'total'       => $productos->count(),
            ];
        } catch (Exception $e) {
            Log::error(ReporteRepository::class, $e->getMessage());
            return ['productos' => collect(), 'valor_total' => 0, 'agotados' => 0, 'stock_bajo' => 0, 'total' => 0];
        }
    }

    // ── Reporte de empleados ──────────────────────────────────
    public function empleados(): array
    {
        try {
            $empleados = DB::table('empleados as e')
                ->join('usuarios as u',  'u.id', '=', 'e.usuario_id')
                ->join('roles as r',     'r.id', '=', 'u.rol_id')
                ->where('e.activo', 1)
                ->selectRaw('
                    e.id,
                    u.nombre,
                    u.apellido,
                    u.email,
                    r.nombre as rol,
                    e.puesto,
                    e.turno,
                    e.salario,
                    e.fecha_ingreso,
                    TIMESTAMPDIFF(MONTH, e.fecha_ingreso, CURDATE()) as meses_servicio
                ')
                ->orderBy('e.turno')
                ->orderBy('u.nombre')
                ->get();

            $nominaTotal = $empleados->sum('salario');

            return [
                'empleados'    => $empleados,
                'nomina_total' => $nominaTotal,
                'total'        => $empleados->count(),
                'por_turno'    => $empleados->groupBy('turno')->map->count()->toArray(),
            ];
        } catch (Exception $e) {
            Log::error(ReporteRepository::class, $e->getMessage());
            return ['empleados' => collect(), 'nomina_total' => 0, 'total' => 0, 'por_turno' => []];
        }
    }
}