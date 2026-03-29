<?php
/*
    autor: Noe Cazarez Camargo
    fecha: 2019-06-24
    descripcion:
*/
declare(strict_types=1);
session_start();

// Cargar variables de entorno desde .env
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value, " \t\"'");
        if ($key !== '') {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}
error_reporting(E_ALL & ~E_DEPRECATED);

/* Error to execeptions */
function exception_error_handler($severidad, $mensaje, $fichero, $línea) {
    if (!(error_reporting() & $severidad)) {
        // Este código de error no está incluido en error_reporting
        return;
    }
    throw new ErrorException($mensaje, 0, $severidad, $fichero, $línea);
}

set_error_handler("exception_error_handler");

/* Composer & PSR 4 */
require_once 'vendor/autoload.php';

/* Centralized exception handler — debe registrarse antes de cualquier otra cosa */
\Core\ExceptionHandler::register();

/* Configuration Start */
\Core\ServicesContainer::setConfig(
    require_once 'config.php'
);

/* Initialize DbContext */
\Core\ServicesContainer::initializeDbContext();

$config = \Core\ServicesContainer::getConfig();

/* Default Time Zone */
date_default_timezone_set($config['timezone']);

ini_set('memory_limit', '-1');

/* Base url */
$base_url = '';
//$base_folder = strtolower(str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']));
$base_folder = strtolower(str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']));

// Fix para servidor built-in de PHP o instalaciones en raíz
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = $base_folder === '/' ? $uri : '/' . ltrim(str_replace($base_folder, '', $uri), '/');
define('_CURRENT_URI_', $uri);

if (isset($_SERVER['HTTP_HOST']))
{
    $base_url = isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off' ? 'https' : 'http';
    $base_url .= '://'. $_SERVER['HTTP_HOST'];
    $base_url .= $base_folder;
}



define('_BASE_HTTP_', $base_url);
define('_BASE_PATH_', __DIR__ . '/');
define('_LOG_PATH_', __DIR__ . '/log/');
define('_CACHE_PATH_', __DIR__ . '/cache/');
define('_APP_PATH_', __DIR__ . '/app/');
//define('_CURRENT_URI_', str_replace($base_folder, '', $_SERVER['REQUEST_URI']));
$current_uri = '/' . ltrim(str_replace($base_folder, '', $_SERVER['REQUEST_URI']), '/');
//define('_CURRENT_URI_', strtok($current_uri, '?'));

if($config['environment'] === 'stop') {
    exit('Website is current down ..');
}

if($config['environment'] === 'prod') {
    error_reporting(0);
}
/* Configuration End */

/* Router Start */
$router = new Phroute\Phroute\RouteCollector();

require_once 'app/filters.php';
require_once 'app/routes.php';


$dispatcher = new Phroute\Phroute\Dispatcher($router->getData());

try {
    $response = $dispatcher->dispatch(
        $_SERVER['REQUEST_METHOD'],
        _CURRENT_URI_
    );
    echo $response;
} catch (\Phroute\Phroute\Exception\HttpRouteNotFoundException $e) {
    \Core\ExceptionHandler::handle($e);
} catch (\Phroute\Phroute\Exception\HttpMethodNotAllowedException $e) {
    \Core\ExceptionHandler::handle($e);
}
/* Router End */