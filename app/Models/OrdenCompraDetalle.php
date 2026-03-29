<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrdenCompraDetalle extends Model
{
    protected $table = 'orden_compra_detalles';
    public $timestamps = false;

    protected $fillable = [
        'orden_id',
        'producto_id',
        'cantidad',
        'precio_unitario',
        'subtotal',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }
}