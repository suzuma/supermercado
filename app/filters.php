<?php
// Auth Filter — también valida CSRF en peticiones POST
$router->filter('auth', function(){
    \App\Middlewares\AuthMiddleware::isLoggedIn();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        \Core\Csrf::validateOrFail();
    }
});

// CSRF Filter — para rutas POST fuera del grupo auth (tienda, login público)
$router->filter('csrf', function(){
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        \Core\Csrf::validateOrFail();
    }
});

// System Role Permission
$router->filter('isAdmin', function(){
    if(!\App\Middlewares\RoleMiddleware::isAdmin()){
        \App\Helpers\UrlHelper::redirect('');
    };
});

$router->filter('isSeller', function(){
    if(!\App\Middlewares\RoleMiddleware::isSeller()){
        \App\Helpers\UrlHelper::redirect('');
    };
});

$router->filter('isAnalyst', function(){
    if(!\App\Middlewares\RoleMiddleware::isAnalyst()){
        \App\Helpers\UrlHelper::redirect('');
    };
});

// Filtro RBAC dinámico — uso: ['before' => 'can:ventas.ver']
$router->filter('can', function(string $slug){
    if(!\App\Middlewares\RoleMiddleware::can($slug)){
        \App\Helpers\UrlHelper::redirect('home');
    };
});