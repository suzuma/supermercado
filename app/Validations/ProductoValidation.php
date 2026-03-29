<?php
namespace App\Validations;

use Respect\Validation\Validator as v;
use App\Helpers\ResponseHelper;

class ProductoValidation
{
    public static function validar(array $model)
    {
        try {
            $v = v::key('nombre',        v::stringType()->notEmpty()->length(1, 150))
                ->key('categoria_id',    v::intVal()->positive())
                ->key('precio_compra',   v::numericVal()->min(0))
                ->key('precio_venta',    v::numericVal()->positive())
                ->key('stock',           v::numericVal()->min(0))
                ->key('stock_minimo',    v::numericVal()->min(0));

            // unidad_peso solo se valida cuando el producto es por peso
            if (!empty($model['venta_por_peso'])) {
                $v = $v->key('unidad_peso', v::in(['g', 'kg', 'lb']));
            }

            $v->assert($model);
        } catch (\Exception $e) {
            $rh = new ResponseHelper();
            $rh->setResponse(false, 'Error de validación');
            $rh->validations = $e->findMessages([
                'nombre'        => 'El nombre es requerido (máx. 150 caracteres)',
                'categoria_id'  => 'Selecciona una categoría',
                'precio_compra' => 'El precio de compra debe ser mayor o igual a 0',
                'precio_venta'  => 'El precio de venta es requerido',
                'stock'         => 'El stock debe ser mayor o igual a 0',
                'stock_minimo'  => 'El stock mínimo debe ser mayor o igual a 0',
                'unidad_peso'   => 'La unidad de peso no es válida',
            ]);
            exit(json_encode($rh));
        }
    }
}