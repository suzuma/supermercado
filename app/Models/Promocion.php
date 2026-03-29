<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Promocion extends Model
{
    protected $table = 'promociones';

    protected $fillable = [
        'producto_id',
        'nombre',
        'tipo',
        'valor',
        'cantidad_min',
        'fecha_inicio',
        'fecha_fin',
        'activo',
    ];

    protected $dates = ['fecha_inicio', 'fecha_fin'];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    // Scope para promociones vigentes hoy
    public function scopeVigentes($query)
    {
        return $query->where('activo', 1)
            ->where('fecha_inicio', '<=', date('Y-m-d'))
            ->where('fecha_fin',    '>=', date('Y-m-d'));
    }

    // Calcular precio final según tipo de promoción
    public function calcularPrecio(float $precioOriginal, int $cantidad = 1): array
    {
        $precioFinal  = $precioOriginal;
        $subtotal     = $precioOriginal * $cantidad;
        $ahorro       = 0;
        $descripcion  = '';

        switch ($this->tipo) {
            case 'porcentaje':
                $precioFinal = round($precioOriginal * (1 - $this->valor / 100), 2);
                $subtotal    = $precioFinal * $cantidad;
                $ahorro      = ($precioOriginal - $precioFinal) * $cantidad;
                $descripcion = $this->valor . '% off';
                break;

            case 'precio_fijo':
                $precioFinal = (float)$this->valor;
                $subtotal    = $precioFinal * $cantidad;
                $ahorro      = ($precioOriginal - $precioFinal) * $cantidad;
                $descripcion = 'Precio especial $' . number_format($precioFinal, 2);
                break;

            case '2x1':
                // Por cada 2 unidades, solo se cobra 1
                $unidadesCobradas = ceil($cantidad / 2);
                $subtotal         = $precioOriginal * $unidadesCobradas;
                $ahorro           = $precioOriginal * ($cantidad - $unidadesCobradas);
                $descripcion      = '2x1';
                break;

            case 'cantidad_minima':
                if ($cantidad >= $this->cantidad_min) {
                    $precioFinal = round($precioOriginal * (1 - $this->valor / 100), 2);
                    $subtotal    = $precioFinal * $cantidad;
                    $ahorro      = ($precioOriginal - $precioFinal) * $cantidad;
                    $descripcion = $this->valor . '% off comprando ' . $this->cantidad_min . '+';
                } else {
                    $subtotal    = $precioOriginal * $cantidad;
                    $descripcion = 'Compra ' . $this->cantidad_min . '+ para ' . $this->valor . '% off';
                }
                break;
        }

        return [
            'precio_original' => $precioOriginal,
            'precio_final'    => $precioFinal,
            'subtotal'        => round($subtotal, 2),
            'ahorro'          => round($ahorro, 2),
            'descripcion'     => $descripcion,
            'tiene_promo'     => $ahorro > 0,
        ];
    }
}