<?php

// -----------------------------------------------------------
//  [*] bootstrap/app.php — builds the application
//
//  Creates Laravel's application — the service container,
//  rooted at the app folder unless APP_BASE_PATH says
//  otherwise — and binds the app's three kernels to
//  Laravel's contracts: the HTTP kernel, the console kernel
//  and the exception handler. Laravel 10's stock file,
//  unchanged.
//
//  Used by:
//    - public/index.php — every web request
//    - artisan — every command
//    - tests/CreatesApplication.php — every test
// -----------------------------------------------------------


// The application, which is the service container
$app = new Illuminate\Foundation\Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);

// The kernels requests and commands run through, and the
// handler of uncaught exceptions
$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

// Handed to the script that required this file
return $app;
