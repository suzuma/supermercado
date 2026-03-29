<?php
/*
    autor: Noe Cazarez Camargo
    fecha: 2019-06-24
    descripcion: CLASE PARA LEER EL ARCHIVO DE CONFIGURACION
*/
namespace Core;

class ServicesContainer {
    private static $config;
    private static $dbContext = false;

    public static function setConfig(array $value) {
        self::$config = $value;
    }

    /* Configuration */
    public static function getConfig() : array{
        return self::$config;
    }

    public static function initializeDbContext() {
        if(!(self::$dbContext)) {
            DbContext::initialize();
            self::$dbContext = true;
        }
    }
}