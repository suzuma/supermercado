<?php
/*
    autor: Noe Cazarez Camargo
    fecha: 2019-06-24
    descripcion: 
*/
return [
    /* Database access */
    'database' => [
        'driver'    => 'mysql',
        'host'      => $_ENV['DB_HOST']     ?? '127.0.0.1',
        'database'  => $_ENV['DB_DATABASE'] ?? '',
        'username'  => $_ENV['DB_USERNAME'] ?? 'root',
        'password'  => $_ENV['DB_PASSWORD'] ?? '',
        'charset'   => 'utf8',
        'collation' => 'utf8_unicode_ci',
        'prefix'    => '',
    ],

    /* Session configuration */
    'session-time' => (int) ($_ENV['SESSION_TIME'] ?? 10), // hours
    'session-name' => $_ENV['SESSION_NAME'] ?? 'application-auth',

    /* Secret key */
    'secret-key' => $_ENV['APP_SECRET'] ?? '',

    /* Environment — Options: dev, prod, stop */
    'environment' => $_ENV['APP_ENV'] ?? 'dev',

    /* Timezone */
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'America/Hermosillo',

    /* Cache */
    'cache' => filter_var($_ENV['APP_CACHE'] ?? false, FILTER_VALIDATE_BOOLEAN),

    'company_name' => $_ENV['COMPANY_NAME'] ?? '',
];