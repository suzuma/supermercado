<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Proveedor extends Model
{
    protected $table = 'proveedores';

    protected $fillable = [
        'nombre',
        'contacto',
        'telefono',
        'email',
        'direccion',
        'rfc',
        'activo',
    ];

    public function productos()
    {
        return $this->hasMany(Producto::class);
    }

    public function ordenesCompra()
    {
        return $this->hasMany(OrdenCompra::class);
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', 1);
    }
}