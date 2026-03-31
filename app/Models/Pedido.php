<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pedido extends Model
{
    protected $table = 'pedidos';

    protected $fillable = [
        'cliente_id',
        'usuario_id',
        'total',
        'descuento',
        'cupon_id',
        'puntos_usados',
        'estado',
        'direccion_entrega',
        'fecha_entrega',
    ];

    protected $casts = [
        'puntos_usados' => 'integer',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }

    public function detalles()
    {
        return $this->hasMany(PedidoDetalle::class);
    }
}