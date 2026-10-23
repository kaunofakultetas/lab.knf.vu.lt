<?php

// -----------------------------------------------------------
//  [*] config/app.php — the application's core settings
//
//  The app's name, environment and debug switch, the URL,
//  the time zone and languages, the encryption key, and the
//  service providers and facade aliases every request and
//  artisan run boots with. Laravel 10's stock file, changed
//  in three defaults — the name, the URL and the Vilnius
//  time zone — and in the packages it adds: laravel/ui,
//  laravelcollective/html (the Form and Html facades the
//  views build their forms with) and the reCAPTCHA v3 rule
//  (josiasmontag/laravel-recaptchav3).
//
//  In production the values come from the environment the
//  vuknflab-apache service gets in docker-compose.yml —
//  APP_NAME, APP_KEY, APP_DEBUG ("false") and APP_URL — and
//  from the .env file the image carries, which sets APP_ENV
//  to production. The app also reads APP_URL itself with
//  env(), in 49 controllers, mails and views, to build its
//  links.
//
//  Used by:
//    - Laravel at boot, on every request and artisan run —
//      the providers, aliases, time zone, languages, key and
//      debug switch
// -----------------------------------------------------------


use Illuminate\Support\Facades\Facade;

return [

    // The app's name, from APP_NAME; the same variable names
    // the session cookie and the cache and Redis key prefixes
    // (config/session.php, cache.php, database.php)
    'name' => env('APP_NAME', 'VU KNF Lab'),

    // The environment's name, from APP_ENV — production, by
    // the image's .env; the tests switch it to testing
    // (phpunit.xml)
    'env' => env('APP_ENV', 'production'),

    // Debug mode shows the code and stack trace of every
    // error; off unless APP_DEBUG turns it on, and
    // docker-compose.yml sets "false"
    'debug' => (bool) env('APP_DEBUG', false),

    // The site's root URL, from APP_URL; artisan builds its
    // links from it, a web request from the request itself
    'url' => env('APP_URL', 'https://lab.knf.vu.lt'),

    // The host asset() links point at, from ASSET_URL — not
    // set, so the request's own
    'asset_url' => env('ASSET_URL'),

    // PHP's time zone, set by Laravel at boot: every date the
    // app writes — solves, logins, feedback — is Vilnius time
    'timezone' => 'Europe/Vilnius',

    // The one language of the site, and the language a
    // missing translation falls back to
    'locale' => 'en',

    'fallback_locale' => 'en',

    // The language Faker's sample data is made in, for the
    // database factories
    'faker_locale' => 'en_US',

    // The key of Laravel's encrypter — cookies included — from
    // APP_KEY, and its cipher
    'key' => env('APP_KEY'),

    'cipher' => 'AES-256-CBC',

    // Maintenance mode (artisan down) is kept in a file of
    // the container, so it ends with the container
    'maintenance' => [
        'driver' => 'file',
    ],

    // The service providers booted on every request: Laravel's
    // own, then the app's and the packages'.
    // BroadcastServiceProvider is switched off, as the app
    // broadcasts nothing; laravel/ui's provider is booted but
    // no route uses it
    'providers' => [

        // Laravel's own
        Illuminate\Auth\AuthServiceProvider::class,
        Illuminate\Broadcasting\BroadcastServiceProvider::class,
        Illuminate\Bus\BusServiceProvider::class,
        Illuminate\Cache\CacheServiceProvider::class,
        Illuminate\Foundation\Providers\ConsoleSupportServiceProvider::class,
        Illuminate\Cookie\CookieServiceProvider::class,
        Illuminate\Database\DatabaseServiceProvider::class,
        Illuminate\Encryption\EncryptionServiceProvider::class,
        Illuminate\Filesystem\FilesystemServiceProvider::class,
        Illuminate\Foundation\Providers\FoundationServiceProvider::class,
        Illuminate\Hashing\HashServiceProvider::class,
        Illuminate\Mail\MailServiceProvider::class,
        Illuminate\Notifications\NotificationServiceProvider::class,
        Illuminate\Pagination\PaginationServiceProvider::class,
        Illuminate\Pipeline\PipelineServiceProvider::class,
        Illuminate\Queue\QueueServiceProvider::class,
        Illuminate\Redis\RedisServiceProvider::class,
        Illuminate\Auth\Passwords\PasswordResetServiceProvider::class,
        Illuminate\Session\SessionServiceProvider::class,
        Illuminate\Translation\TranslationServiceProvider::class,
        Illuminate\Validation\ValidationServiceProvider::class,
        Illuminate\View\ViewServiceProvider::class,

        // The app's, and the packages'
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        App\Providers\EventServiceProvider::class,
        App\Providers\RouteServiceProvider::class,
        Laravel\Ui\UiServiceProvider::class,
        Collective\Html\HtmlServiceProvider::class,
        Lunaweb\RecaptchaV3\Providers\RecaptchaV3ServiceProvider::class,
    ],

    // The facade aliases: Laravel's, plus the forms' Form and
    // Html
    'aliases' => Facade::defaultAliases()->merge([
        'Form' => Collective\Html\FormFacade::class,
        'Html' => Collective\Html\HtmlFacade::class,
    ])->toArray(),
];
