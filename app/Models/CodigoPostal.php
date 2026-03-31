<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CodigoPostal extends Model
{
    protected $table    = 'codigos_postales';
    public    $timestamps = false;
    protected $fillable = ['cp', 'colonia', 'municipio', 'estado', 'ciudad'];
}