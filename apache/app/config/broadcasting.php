<?php

// -----------------------------------------------------------
//  [*] config/broadcasting.php — event broadcasting
//
//  Laravel's stock broadcasting settings. The app broadcasts
//  nothing and BroadcastServiceProvider is switched off;
//  the image's .env names the log driver, which would only
//  write broadcasts to the log.
//
//  Used by:
//    - Laravel's broadcasting, which nothing in the app
//      calls
// -----------------------------------------------------------


return [

    // The driver, from BROADCAST_DRIVER — log, by the .env
    'default' => env('BROADCAST_DRIVER', 'null'),

    // The drivers Laravel offers, none of them configured for
    // a real service
    'connections' => [

        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                'host' => env('PUSHER_HOST') ?: 'api-'.env('PUSHER_APP_CLUSTER', 'mt1').'.pusher.com',
                'port' => env('PUSHER_PORT', 443),
                'scheme' => env('PUSHER_SCHEME', 'https'),
                'encrypted' => true,
                'useTLS' => env('PUSHER_SCHEME', 'https') === 'https',
            ],
            'client_options' => [
                // Empty: Guzzle's defaults
            ],
        ],

        'ably' => [
            'driver' => 'ably',
            'key' => env('ABLY_KEY'),
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],
    ],
];
