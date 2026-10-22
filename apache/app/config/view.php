<?php

// -----------------------------------------------------------
//  [*] config/view.php — the Blade views
//
//  Where the views are and where Blade keeps them compiled.
//  In production the compiled folder is a tmpfs
//  (docker-compose.yml), so each container compiles its
//  views afresh.
//
//  Used by:
//    - Laravel's view factory — every view() of the app
// -----------------------------------------------------------


return [

    // The folders the views are looked up in
    'paths' => [
        resource_path('views'),
    ],

    // The folder of the compiled views, from
    // VIEW_COMPILED_PATH or else storage/framework/views
    'compiled' => env(
        'VIEW_COMPILED_PATH',
        realpath(storage_path('framework/views'))
    ),
];
