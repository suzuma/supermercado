<?php
/*
    autor: Noe Cazarez Camargo
    fecha: 2019-06-24
    descripcion: MODELO DE LA TABLA DE PRODUCTOS
*/
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $table = 'productos';

    protected $fillable = [
        'categoria_id',
        'proveedor_id',
        'nombre',
        'descripcion',
        'precio_compra',
        'precio_venta',
        'stock',
        'stock_minimo',
        'codigo_barras',
        'imagen',
        'activo',
        'venta_por_peso',
        'unidad_peso',
        'fecha_caducidad',
    ];

    protected $casts = [
        'venta_por_peso'  => 'boolean',
        'stock'           => 'float',
        'stock_minimo'    => 'float',
        'fecha_caducidad' => 'date:Y-m-d',
    ];

    /** Etiqueta de precio lista para mostrar: "$180.00 / kg" o "$25.00" */
    public function getPrecioEtiquetaAttribute(): string
    {
        $precio = number_format((float)$this->precio_venta, 2);
        if ($this->venta_por_peso) {
            return '$' . $precio . ' / ' . $this->unidad_peso;
        }
        return '$' . $precio;
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', 1);
    }

    public function scopeStockBajo($query)
    {
        return $query->whereColumn('stock', '<=', 'stock_minimo');
    }

    public function scopeProximosVencer($query, int $dias = 30)
    {
        $hoy   = date('Y-m-d');
        $limite = date('Y-m-d', strtotime("+{$dias} days"));
        return $query->whereNotNull('fecha_caducidad')
                     ->where('fecha_caducidad', '<=', $limite);
    }

    public function getStockBajoAttribute(): bool
    {
        return $this->stock <= $this->stock_minimo;
    }

    /** Días restantes hasta la caducidad. Negativo si ya venció. null si no tiene fecha. */
    public function getDiasParaVencerAttribute(): ?int
    {
        if (!$this->fecha_caducidad) return null;
        $hoy = new \DateTime(date('Y-m-d'));
        $cad = new \DateTime($this->fecha_caducidad->format('Y-m-d'));
        return (int)$hoy->diff($cad)->days * ($cad >= $hoy ? 1 : -1);
    }

    /** 'vencido' | 'critico' (≤7 días) | 'proximo' (≤30 días) | 'ok' | null */
    public function getEstadoCaducidadAttribute(): ?string
    {
        $dias = $this->dias_para_vencer;
        if ($dias === null) return null;
        if ($dias < 0)  return 'vencido';
        if ($dias <= 7) return 'critico';
        if ($dias <= 30) return 'proximo';
        return 'ok';
    }
}