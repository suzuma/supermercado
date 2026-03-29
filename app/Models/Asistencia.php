<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asistencia extends Model
{
    protected $table = 'asistencias';

    protected $fillable = [
        'empleado_id',
        'fecha',
        'hora_entrada',
        'hora_salida',
        'observacion',
        'registrado_por',
    ];

    public function empleado()
    {
        return $this->belongsTo(Empleado::class);
    }

    public function registrador()
    {
        return $this->belongsTo(Usuario::class, 'registrado_por');
    }
}