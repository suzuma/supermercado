<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DevolucionDetalle extends Model
{
    protected $table = 'devolucion_detalles';
    public $timestamps = false;

    protected $fillable = [
        'devolucion_id',
        'venta_detalle_id',
        'producto_id',
        'cantidad',
        'precio_unitario',
        'subtotal',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function ventaDetalle()
    {
        return $this->belongsTo(VentaDetalle::class);
    }
}