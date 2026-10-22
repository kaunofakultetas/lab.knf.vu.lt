<?php

// -----------------------------------------------------------
//  [*] public/index.php — the front controller
//
//  Every web request that names no file under public/ ends
//  here: Apache's DocumentRoot is public/, and
//  public/.htaccess rewrites every other path to this
//  script. It shows the maintenance page while artisan down
//  is in force, loads Composer's autoloader, builds the app
//  (bootstrap/app.php), hands the request to the HTTP
//  kernel — the global middleware, the routes, the
//  controllers — sends the response back, then lets the
//  kernel finish its after-response work. Laravel 10's
//  stock file, unchanged.
//
//  Used by:
//    - Apache — every request public/.htaccess rewrites
//    - ApacheTest (tests/Http) — the front page and the
//      pretty addresses, over HTTP
// -----------------------------------------------------------


use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

// The moment the request started; nothing in the app reads
// it
define('LARAVEL_START', microtime(true));

// The page artisan down leaves behind, shown instead of the
// app while maintenance mode is on
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Composer's class loader, for the app's classes and the
// vendor packages
require __DIR__.'/../vendor/autoload.php';

// The application, as bootstrap/app.php builds it
$app = require_once __DIR__.'/../bootstrap/app.php';

// The request, captured from PHP's globals, goes through
// the HTTP kernel; the response goes back to the browser,
// and terminate runs what waits for the response to be
// sent
$kernel = $app->make(Kernel::class);

$response = $kernel->handle(
    $request = Request::capture()
)->send();

$kernel->terminate($request, $response);
