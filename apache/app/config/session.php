<?php

// -----------------------------------------------------------
//  [*] config/session.php — the session and its cookie
//
//  The one session players and admins share, kept in Redis
//  by the image's .env and in memory in the tests
//  (phpunit.xml). A session lasts SESSION_LIFETIME minutes
//  of inactivity — 2160 in the .env, 36 hours. Its cookie
//  is named after APP_NAME, sent on every path of the site,
//  hidden from scripts, and SameSite=Lax — which still lets
//  a top-level link from another site carry it, so the
//  control panel makes no change by GET.
//
//  Used by:
//    - StartSession, in the web group of app/Http/Kernel.php
// -----------------------------------------------------------


use Illuminate\Support\Str;

return [

    // Where sessions are kept, from SESSION_DRIVER — redis,
    // by the .env
    'driver' => env('SESSION_DRIVER', 'file'),

    // Minutes of inactivity a session survives, from
    // SESSION_LIFETIME, and whether it ends with the browser
    // — it does not
    'lifetime' => env('SESSION_LIFETIME', 120),

    'expire_on_close' => false,

    // The session data is stored unencrypted
    'encrypt' => false,

    // The folder of the file driver, unused
    'files' => storage_path('framework/sessions'),

    // The Redis connection of the redis driver, from
    // SESSION_CONNECTION — not set, so the default one
    'connection' => env('SESSION_CONNECTION'),

    // The table of the database driver, unused
    'table' => 'sessions',

    // The cache store of the cache-based drivers, from
    // SESSION_STORE — not set
    'store' => env('SESSION_STORE'),

    // The odds that a request sweeps old sessions, for the
    // drivers that need it; Redis expires them itself
    'lottery' => [2, 100],

    // The cookie's name, from SESSION_COOKIE or else APP_NAME.
    // Known bug LAB-88 (low): another host under vu.lt may set
    // a cookie of this name for the whole domain, with a
    // longer path the browser sends first, and the victim's
    // pages then run in the attacker's session. Fix: the
    // __Host- prefix, which only the lab's own host can set.
    'cookie' => env(
        'SESSION_COOKIE',
        Str::slug(env('APP_NAME', 'laravel'), '_').'_session'
    ),

    // The cookie's path and domain: the whole site, its own
    // host
    'path' => '/',

    'domain' => env('SESSION_DOMAIN'),

    // The Secure flag, on unless SESSION_SECURE_COOKIE says
    // otherwise: the site is served over https only, and a
    // browser sends a cookie without the flag on a plain http
    // request too — the session and XSRF-TOKEN cookies
    // included
    'secure' => env('SESSION_SECURE_COOKIE', true),

    // Scripts cannot read the cookie
    'http_only' => true,

    // Cross-site requests carry the cookie only on top-level
    // GET navigation
    'same_site' => 'lax',
];
