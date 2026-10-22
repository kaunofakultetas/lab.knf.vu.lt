<?php

// -----------------------------------------------------------
//  [*] config/cors.php — cross-origin requests
//
//  Laravel's stock CORS settings, which HandleCors applies
//  to the API paths and Sanctum's cookie path only: any
//  origin, method and header, no credentials. They decide
//  little in practice — the stack's endpoint already turns
//  away every request whose Origin is another site's
//  (endpoint/Caddyfile), and the one API route fails anyway
//  (LAB-05).
//
//  Used by:
//    - HandleCors, in the global stack of app/Http/Kernel.php
// -----------------------------------------------------------


return [

    // The paths the CORS headers are added to
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    // Any method, origin and header; nothing exposed, no
    // preflight caching, no cookies
    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];
