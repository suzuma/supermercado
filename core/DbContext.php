<?php
/*
    autor: Noe Cazarez Camargo
    fecha: 2019-06-24
    descripcion: CLASE PARA LA CREACION DE LA INTANCIA DEL OBJETO QUE NOS CONECTA CON LA BASE DE DATOS
*/
namespace Core;

use Illuminate\Database\Capsule\Manager as Capsule;

class DbContext {
    public static function initialize() {
        try {
            $config = ServicesContainer::getConfig();
            $capsule = new Capsule;
            $capsule->addConnection($config['database']);
            $capsule->setAsGlobal();
            $capsule->bootEloquent();
        } catch(\Exception $e) {
            Log::error(
                DbContext::class,
                $e->getMessage()
            );
        }
    }
}