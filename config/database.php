<?php

declare(strict_types=1);

use App\Core\Env;

return [
    /*
    |--------------------------------------------------------------------------
    | Default Database Connection
    |--------------------------------------------------------------------------
    */
    'default' => 'mysql',

    /*
    |--------------------------------------------------------------------------
    | Dedicated LC-SPC MySQL Database Configuration
    |--------------------------------------------------------------------------
    | Target Hostinger DB: u806388046_LC
    | Target Hostinger User: u806388046_LC_SPC
    | The password must be provided via the environment and never committed to Git.
    */
    'host' => Env::get('DB_HOST', '127.0.0.1'),
    'port' => (int) Env::get('DB_PORT', 3306),
    'database' => Env::get('DB_DATABASE', 'u806388046_LC'),
    'username' => Env::get('DB_USERNAME', 'u806388046_LC_SPC'),
    'password' => (string) Env::get('DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => false,
    ],
];
