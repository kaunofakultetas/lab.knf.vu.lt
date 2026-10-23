<?php

// -----------------------------------------------------------
//  [*] config/database.php — MySQL and Redis
//
//  The connections the app talks to: the stack's MySQL
//  (vuknflab-mysql), whose schema mysql/init.sql creates —
//  the app has no migrations — and the stack's Redis
//  (vuknflab-redis), which holds the sessions, Laravel's
//  cache and the app's own keys: the cached leaderboard,
//  the solve feed and the votes. The host, database, user
//  and password come from docker-compose.yml; the port and
//  the Redis client (predis) from the image's .env.
//
//  Used by:
//    - Laravel's database and Redis layers — every model,
//      DB:: and Redis:: call of the app
// -----------------------------------------------------------


use Illuminate\Support\Str;

return [

    // The connection the models use, from DB_CONNECTION —
    // mysql, by the .env
    'default' => env('DB_CONNECTION', 'mysql'),

    // The connections Laravel offers; only mysql is used.
    // strict is off, so Laravel sets no sql_mode but
    // NO_ENGINE_SUBSTITUTION: a grouped query may read
    // columns it does not aggregate, and an insert that
    // leaves out a NOT NULL column gets its type's empty
    // value — the empty password of the SSO accounts — while
    // an explicit NULL still fails, which is why the control
    // panel's forms check for empty fields
    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DATABASE_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => false,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DATABASE_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'prefix_indexes' => true,
        ],
    ],

    // The migrations table Laravel would keep — the schema
    // has it, but no migration exists
    'migrations' => 'migrations',

    // Redis: the client the .env picks (predis), and the
    // prefix of every key, made from APP_NAME. default, Redis
    // database 0, holds the sessions and the app's keys;
    // cache, database 1, Laravel's cache
    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],
    ],
];
