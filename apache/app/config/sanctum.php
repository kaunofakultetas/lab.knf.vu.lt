<?php

// -----------------------------------------------------------
//  [*] config/sanctum.php — Laravel Sanctum
//
//  The settings of Sanctum, installed with the skeleton for
//  its /api/user route. Nothing of the lab uses Sanctum:
//  no token is ever issued, and the one route behind it
//  fails, as the guard this file names does not exist
//  (LAB-05).
//
//  Used by:
//    - Sanctum's guard — auth:sanctum on /api/user
//      (routes/api.php)
// -----------------------------------------------------------


use Laravel\Sanctum\Sanctum;

return [

    // The hosts whose requests Sanctum would authenticate by
    // the session cookie: the local development hosts and the
    // APP_URL host, unless SANCTUM_STATEFUL_DOMAINS lists
    // others
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        Sanctum::currentApplicationUrlWithPort()
    ))),

    // The guards Sanctum tries before tokens: web, a guard
    // config/auth.php does not define (LAB-05)
    'guard' => ['web'],

    // Minutes before a token expires — never
    'expiration' => null,

    // The middleware Sanctum's stateful requests would run
    // through, the app's own versions
    'middleware' => [
        'verify_csrf_token' => App\Http\Middleware\VerifyCsrfToken::class,
        'encrypt_cookies' => App\Http\Middleware\EncryptCookies::class,
    ],
];
