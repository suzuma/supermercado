<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrdenCompra extends Model
{
    protected $table = 'ordenes_compra';

    protected $fillable = [
        'proveedor_id',
        'usuario_id',
        'total',
        'estado',
        'fecha_entrega',
    ];

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }

    public function detalles()
    {
        return $this->hasMany(OrdenCompraDetalle::class, 'orden_id');
    }

    public function getEstadoLabelAttribute(): string
    {
        return match($this->estado) {
            'pendiente' => 'Pendiente',
            'recibida'  => 'Recibida',
            'cancelada' => 'Cancelada',
            default     => '—',
        };
    }
}