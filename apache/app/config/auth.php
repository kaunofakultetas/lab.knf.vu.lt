<?php

// -----------------------------------------------------------
//  [*] config/auth.php — the guards of players and admins
//
//  Two session guards over two tables: users, the players
//  (App\Models\User on cyber_users) and the default guard,
//  and admins, the control panel's accounts
//  (App\Models\Admin on cyber_admins). Both live in the one
//  session, so a browser can be signed into both sides at
//  once. Sanctum's guard is users too (config/sanctum.php).
//
//  Used by:
//    - Laravel's auth — the users and admins guards the app
//      names across its code, and the auth and auth.admin
//      middleware of routes/web.php
// -----------------------------------------------------------


return [

    // The guard Auth uses when none is named, and Laravel's
    // password broker, which the app does not use — its
    // reminders are its own (Users\RegisterController)
    'defaults' => [
        'guard' => 'users',
        'passwords' => 'users',
    ],

    // The two guards; login_redirect is a key of the app's
    // that nothing reads
    'guards' => [
        'users' => [
            'driver' => 'session',
            'provider' => 'users',
            'login_redirect' => '/',
        ],
        'admins' => [
            'driver' => 'session',
            'provider' => 'admins',
            'login_redirect' => '/control-panel',
        ],
    ],

    // Where each guard finds its accounts
    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],
        'admins' => [
            'driver' => 'eloquent',
            'model' => App\Models\Admin::class,
        ],
    ],

    // Laravel's password broker: tokens in password_resets,
    // valid for 60 minutes, one request a minute. The app
    // never calls it, so the table stays empty
    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => 'password_resets',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    // How long, in seconds, a confirmed password stays
    // confirmed for the password.confirm middleware — three
    // hours; no route uses that middleware
    'password_timeout' => 10800,
];
