<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permiso extends Model
{
    protected $table = 'permisos';
    public $timestamps = false;

    protected $fillable = ['slug', 'descripcion', 'modulo'];

    public function roles()
    {
        return $this->belongsToMany(Rol::class, 'rol_permisos', 'permiso_id', 'rol_id');
    }
}
