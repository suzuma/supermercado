<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Empleado extends Model
{
    protected $table = 'empleados';

    protected $fillable = [
        'usuario_id',
        'puesto',
        'salario',
        'fecha_ingreso',
        'turno',
        'activo',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }

    public function asistencias()
    {
        return $this->hasMany(Asistencia::class);
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', 1);
    }

    public function getTurnoLabelAttribute(): string
    {
        return match($this->turno) {
            'matutino'   => 'Matutino',
            'vespertino' => 'Vespertino',
            'nocturno'   => 'Nocturno',
            default      => '—',
        };
    }
}