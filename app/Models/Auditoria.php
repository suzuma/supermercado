<?php
declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Auditoria extends Model
{
    public $timestamps = false;

    protected $table = 'auditoria';

    protected $fillable = ['usuario_id', 'modulo', 'accion', 'descripcion', 'referencia_id', 'ip'];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class);
    }
}
