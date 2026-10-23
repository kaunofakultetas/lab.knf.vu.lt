<?php

// -----------------------------------------------------------
//  [*] config/sanctum.php — Laravel Sanctum
//
//  The settings of Sanctum, installed with the skeleton for
//  its /api/user route. Nothing of the lab uses Sanctum: no
//  token is ever issued, and the api group starts no
//  session, so the one route behind it turns every client
//  away with a 401.
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

    // The guard Sanctum tries before tokens: the players'
    // users guard, which finds no one without a session
    'guard' => ['users'],

    // Minutes before a token expires — never
    'expiration' => null,

    // The middleware Sanctum's stateful requests would run
    // through, the app's own versions
    'middleware' => [
        'verify_csrf_token' => App\Http\Middleware\VerifyCsrfToken::class,
        'encrypt_cookies' => App\Http\Middleware\EncryptCookies::class,
    ],
];
