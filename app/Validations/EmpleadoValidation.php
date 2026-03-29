<?php
namespace App\Validations;

use Respect\Validation\Validator as v;
use App\Helpers\ResponseHelper;

class EmpleadoValidation
{
    public static function validar(array $data)
    {
        try {
            $esNuevo = empty($data['empleado_id']);

            $validator = v::key('nombre',        v::stringType()->notEmpty())
                ->key('apellido',      v::stringType()->notEmpty())
                ->key('email',         v::email())
                ->key('rol_id',        v::intVal()->positive())
                ->key('puesto',        v::stringType()->notEmpty())
                ->key('salario',       v::numericVal()->min(0))
                ->key('fecha_ingreso', v::date('Y-m-d'))
                ->key('turno',         v::in(['matutino', 'vespertino', 'nocturno']));

            if ($esNuevo) {
                $validator = $validator->key('password', v::stringType()->notEmpty()->length(6));
            }

            $validator->assert($data);

        } catch (\Exception $e) {
            $rh = new ResponseHelper();
            $rh->setResponse(false, 'Error de validación');
            $rh->validations = $e->findMessages([
                'nombre'        => 'El nombre es requerido',
                'apellido'      => 'El apellido es requerido',
                'email'         => 'Ingresa un correo válido',
                'rol_id'        => 'Selecciona un rol',
                'puesto'        => 'El puesto es requerido',
                'salario'       => 'El salario debe ser mayor o igual a 0',
                'fecha_ingreso' => 'La fecha de ingreso es requerida',
                'turno'         => 'Selecciona un turno válido',
                'password'      => 'La contraseña debe tener mínimo 6 caracteres',
            ]);
            exit(json_encode($rh));
        }
    }
}