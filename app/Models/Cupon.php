<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cupon extends Model
{
    protected $table    = 'cupones';
    protected $fillable = [
        'codigo', 'descripcion', 'tipo', 'valor',
        'monto_minimo', 'usos_max', 'usos_actual',
        'fecha_inicio', 'fecha_fin', 'activo',
    ];

    public function pedidos()
    {
        return $this->hasMany(Pedido::class);
    }

    public function calcularDescuento(float $total): float
    {
        if ($this->tipo === 'porcentaje') {
            return round($total * ((float)$this->valor / 100), 2);
        }
        return min(round((float)$this->valor, 2), $total);
    }
}
