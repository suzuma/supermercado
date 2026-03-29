<?php
/*
    autor: Noe Cazarez Camargo
    fecha: 2019-06-24
    descripcion: MODELO DE LA TABLA DE USUARIOS
*/
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Usuario extends Model{
    protected $table = 'usuarios';

    protected $fillable = [
        'rol_id',
        'nombre',
        'apellido',
        'email',
        'password',
        'activo',
    ];

    protected $hidden = [
        'password',
    ];

    // Relación: un usuario pertenece a un rol
    public function rol()
    {
        return $this->belongsTo(Rol::class);
    }

    // Scope: solo usuarios activos
    public function scopeActivos($query)
    {
        return $query->where('activo', 1);
    }

    // Nombre completo
    public function getNombreCompletoAttribute(): string
    {
        return $this->nombre . ' ' . $this->apellido;
    }
}