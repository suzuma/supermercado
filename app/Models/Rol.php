<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rol extends Model
{
    protected $table = 'roles';

    public function usuarios()
    {
        return $this->hasMany(Usuario::class);
    }

    public function permisos()
    {
        return $this->belongsToMany(Permiso::class, 'rol_permisos', 'rol_id', 'permiso_id');
    }
}