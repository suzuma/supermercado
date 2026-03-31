<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PuntosTransaccion extends Model
{
    protected $table      = 'puntos_transacciones';
    public    $timestamps = false;

    protected $fillable = ['cliente_id', 'pedido_id', 'tipo', 'puntos', 'descripcion', 'created_at'];

    protected $casts = [
        'puntos'     => 'integer',
        'created_at' => 'datetime',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function pedido()
    {
        return $this->belongsTo(Pedido::class);
    }
}