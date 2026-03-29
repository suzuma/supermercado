<?php
/*
    autor: Noe Cazarez Camargo
    fecha: 2019-06-24
    descripcion: 
*/
namespace App\Middlewares;

use App\Helpers\UrlHelper,
    Core\Auth;

class AuthMiddleware {
    public static function isLoggedIn() {
        if(!Auth::isLoggedIn()) {
            UrlHelper::redirect('auth');
        }
    }
}