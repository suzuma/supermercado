<?php
/*
    autor: Noe Cazarez Camargo
    fecha: 2019-06-24
    descripcion: MODELO DE LA TABLA DE ROLES
*/
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Rol extends Model{
    protected $table = 'roles';

    public function usuarios()
    {
        return $this->hasMany(Usuario::class);
    }
}