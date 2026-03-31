<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Merma extends Model
{
    protected $table      = 'merma';
    public    $timestamps = false;

    protected $fillable = ['producto_id', 'cantidad', 'motivo', 'notas', 'usuario_id', 'created_at'];

    protected $casts = [
        'cantidad'   => 'float',
        'created_at' => 'datetime',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }
}