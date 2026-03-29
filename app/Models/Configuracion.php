<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Configuracion extends Model
{
    protected $table    = 'configuracion';
    protected $fillable = ['clave', 'valor'];

    // Obtener valor por clave
    public static function get(string $clave, string $default = ''): string
    {
        $row = static::where('clave', $clave)->first();
        return $row ? (string)$row->valor : $default;
    }

    // Guardar valor por clave
    public static function set(string $clave, string $valor): void
    {
        static::updateOrCreate(['clave' => $clave], ['valor' => $valor]);
    }
}