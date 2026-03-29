<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CorteCaja extends Model
{
    protected $table      = 'cortes_caja';
    public    $timestamps = false;

    protected $fillable = [
        'usuario_id',
        'fondo_inicial',
        'total_efectivo',
        'total_tarjeta',
        'total_transferencia',
        'total_ventas',
        'num_ventas',
        'efectivo_esperado',
        'efectivo_contado',
        'diferencia',
        'observaciones',
    ];

    protected $casts = [
        'usuario_id'          => 'integer',
        'fondo_inicial'       => 'float',
        'total_efectivo'      => 'float',
        'total_tarjeta'       => 'float',
        'total_transferencia' => 'float',
        'total_ventas'        => 'float',
        'num_ventas'          => 'integer',
        'efectivo_esperado'   => 'float',
        'efectivo_contado'    => 'float',
        'diferencia'          => 'float',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }

    public function ventas()
    {
        return $this->hasMany(Venta::class, 'corte_id');
    }
}