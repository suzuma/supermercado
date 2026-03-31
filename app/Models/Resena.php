<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Resena extends Model
{
    protected $table      = 'resenas';
    protected $fillable   = ['cliente_id', 'producto_id', 'calificacion', 'comentario', 'activo'];
    public    $timestamps = false;

    protected $dates = ['created_at'];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }
}