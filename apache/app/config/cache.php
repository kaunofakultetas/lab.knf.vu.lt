<?php

// -----------------------------------------------------------
//  [*] config/cache.php — Laravel's cache
//
//  Laravel's stock cache settings. The app's own cached
//  data — the leaderboard — goes to Redis directly, past
//  this cache; through it go only Laravel's own users: the
//  sessions, whose redis driver is built on this store, and
//  the api group's rate limiter. The image's .env picks the
//  Redis store; the tests use the in-memory one
//  (phpunit.xml).
//
//  Used by:
//    - Laravel's cache and rate limiter
// -----------------------------------------------------------


use Illuminate\Support\Str;

return [

    // The store, from CACHE_DRIVER — redis, by the .env
    'default' => env('CACHE_DRIVER', 'file'),

    // The stores Laravel offers; redis keeps its keys in
    // Redis database 1, the cache connection of
    // config/database.php
    'stores' => [

        'apc' => [
            'driver' => 'apc',
        ],

        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        'database' => [
            'driver' => 'database',
            'table' => 'cache',
            'connection' => null,
            'lock_connection' => null,
        ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
        ],

        'memcached' => [
            'driver' => 'memcached',
            'persistent_id' => env('MEMCACHED_PERSISTENT_ID'),
            'sasl' => [
                env('MEMCACHED_USERNAME'),
                env('MEMCACHED_PASSWORD'),
            ],
            'options' => [
                // Empty: the extension's defaults
            ],
            'servers' => [
                [
                    'host' => env('MEMCACHED_HOST', '127.0.0.1'),
                    'port' => env('MEMCACHED_PORT', 11211),
                    'weight' => 100,
                ],
            ],
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'cache',
            'lock_connection' => 'default',
        ],

        'dynamodb' => [
            'driver' => 'dynamodb',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'table' => env('DYNAMODB_CACHE_TABLE', 'cache'),
            'endpoint' => env('DYNAMODB_ENDPOINT'),
        ],

        'octane' => [
            'driver' => 'octane',
        ],
    ],

    // The prefix of every cache key, made from APP_NAME
    'prefix' => env('CACHE_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_cache_'),
];
