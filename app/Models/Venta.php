<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Venta extends Model
{
    protected $table = 'ventas';

    protected $fillable = [
        'usuario_id',
        'cliente_id',
        'subtotal',
        'descuento',
        'total',
        'tipo',
        'estado',
        'corte_id',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function detalles()
    {
        return $this->hasMany(VentaDetalle::class);
    }

    public function getTipoLabelAttribute(): string
    {
        return match($this->tipo) {
            'efectivo'      => 'Efectivo',
            'tarjeta'       => 'Tarjeta',
            'transferencia' => 'Transferencia',
            default         => '—',
        };
    }
}