<?php

declare(strict_types=1);

use App\Core\Env;

return [
    /*
    |--------------------------------------------------------------------------
    | Application Name & Branding
    |--------------------------------------------------------------------------
    */
    'name' => Env::get('APP_NAME', 'Listening Community SPC'),
    'full_title' => 'Listening Community – Suicide Prevention Campaign',
    'version' => '1.0.0-phase0',

    /*
    |--------------------------------------------------------------------------
    | Application Environment & Debug Mode
    |--------------------------------------------------------------------------
    | 'production' suppresses all stack traces and sensitive error outputs.
    | 'local' or 'development' allows diagnostic traces during debugging.
    */
    'env' => Env::get('APP_ENV', 'production'),
    'debug' => (bool) Env::get('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL & Base Path
    |--------------------------------------------------------------------------
    | Local: APP_URL=http://localhost:8000, APP_BASE_PATH=/
    | Hostinger Production: APP_URL=https://teamincubation.in/LC, APP_BASE_PATH=/LC
    */
    'url' => rtrim((string) Env::get('APP_URL', 'https://teamincubation.in/LC'), '/'),
    'base_path' => '/' . trim((string) Env::get('APP_BASE_PATH', '/LC'), '/'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone & Locale
    |--------------------------------------------------------------------------
    */
    'timezone' => Env::get('APP_TIMEZONE', 'Asia/Kolkata'),
    'locale' => 'en',
];
