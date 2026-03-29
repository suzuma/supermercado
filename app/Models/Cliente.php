<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    protected $table = 'clientes';

    protected $fillable = [
        'nombre',
        'apellido',
        'email',
        'telefono',
        'direccion',
        'fecha_nacimiento',
        'rfc',
        'password',
        'activo',
    ];

    protected $hidden = ['password'];

    public function ventas()
    {
        return $this->hasMany(Venta::class);
    }

    public function pedidos()
    {
        return $this->hasMany(Pedido::class);
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', 1);
    }

    public function getNombreCompletoAttribute(): string
    {
        return $this->nombre . ' ' . $this->apellido;
    }
}