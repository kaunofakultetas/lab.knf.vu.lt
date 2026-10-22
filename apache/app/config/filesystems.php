<?php

// -----------------------------------------------------------
//  [*] config/filesystems.php — Laravel's storage disks
//
//  Laravel's stock disks, which the app hardly uses: the
//  partners' logos are moved into storage/app/public by
//  hand, never through a disk, and the site serves that
//  folder under /upload — the symlink the Dockerfile makes —
//  not under the /storage the public disk names. In
//  production the folder is _DATA/apache/public, mounted
//  read-only.
//
//  Used by:
//    - Laravel's filesystem, which nothing in the app calls
// -----------------------------------------------------------


return [

    // The disk Storage uses when none is named, from
    // FILESYSTEM_DISK — local, by the .env
    'default' => env('FILESYSTEM_DISK', 'local'),

    // The disks Laravel offers
    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],
    ],

    // The link artisan storage:link would make; nothing runs
    // it, as the Dockerfile makes public/upload instead
    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],
];
