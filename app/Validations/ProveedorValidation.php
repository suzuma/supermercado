<?php
namespace App\Validations;

use Respect\Validation\Validator as v;
use App\Helpers\ResponseHelper;

class ProveedorValidation
{
    public static function validar(array $data)
    {
        try {
            $v = v::key('nombre', v::stringType()->notEmpty()->length(1, 120));

            if (!empty($data['email'])) {
                $v = $v->key('email', v::email());
            }

            if (!empty($data['rfc'])) {
                $v = $v->key('rfc', v::stringType()->length(12, 13));
            }

            $v->assert($data);
        } catch (\Exception $e) {
            $rh = new ResponseHelper();
            $rh->setResponse(false, 'Error de validación');
            $rh->validations = $e->findMessages([
                'nombre' => 'El nombre del proveedor es requerido',
                'email'  => 'Ingresa un correo válido',
                'rfc'    => 'El RFC debe tener 12 o 13 caracteres',
            ]);
            exit(json_encode($rh));
        }
    }
}