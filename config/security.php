<?php

declare(strict_types=1);

use App\Core\Env;

return [
    /*
    |--------------------------------------------------------------------------
    | Session Security Settings
    |--------------------------------------------------------------------------
    */
    'session' => [
        'name' => Env::get('SESSION_NAME', 'LCSPC_SESSION'),
        'lifetime' => (int) Env::get('SESSION_LIFETIME', 7200),
        'path' => '/',
        'domain' => '',
        'secure' => Env::get('APP_ENV') === 'production',
        'httponly' => true,
        'samesite' => 'Lax',
    ],

    /*
    |--------------------------------------------------------------------------
    | CSRF Protection
    |--------------------------------------------------------------------------
    */
    'csrf' => [
        'token_name' => '_csrf_token',
        'header_name' => 'X-CSRF-TOKEN',
    ],

    /*
    |--------------------------------------------------------------------------
    | Security HTTP Response Headers
    |--------------------------------------------------------------------------
    */
    'headers' => [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(self)',
        // Content-Security-Policy: Compatible with modern HTML/CSS/JS without breaking inline scripts
        'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:; connect-src 'self';",
    ],
];
