<?php

// -----------------------------------------------------------
//  [*] Handler — what becomes of an uncaught exception
//
//  Laravel's exception handler with nothing of the app's
//  own. An exception is logged to storage/logs/laravel.log
//  unless it is one of Laravel's routine ones — an HTTP
//  error such as a 404, a failed validation, a refused
//  sign-in, a CSRF mismatch. The visitor gets the lab's own
//  error page for the status (resources/views/errors), or
//  Laravel's for a status the lab has no page for, with
//  nothing of the exception while APP_DEBUG is off, as in
//  production.
//
//  Used by:
//    - bootstrap/app.php — bound as the exception handler
// -----------------------------------------------------------


namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;








// -----------------------------------------------------------
// Handler
// -----------------------------------------------------------
//
// Reports and renders through Laravel's handler unchanged:
// the lists below hold nothing but the password fields, and
// the one reporting callback does nothing.
//
// Used by:
//   - bootstrap/app.php — bound as the exception handler
//   - Laravel's HTTP and console kernels — every uncaught
//     exception
// -----------------------------------------------------------

class Handler extends ExceptionHandler
{

    // Log levels per exception class — none, so every report
    // is logged at Laravel's default, error
    protected $levels = [
    ];

    // The exception classes never logged, on top of Laravel's
    // routine ones — none
    protected $dontReport = [
    ];

    // The inputs never flashed back to the form after a failed
    // validation. The login and registration forms flash
    // their input themselves, past this list (LAB-02)
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];






    // -----------------------------------------------------------
    // register
    // -----------------------------------------------------------
    //
    // Hooks a reporting callback that does nothing and returns
    // nothing, so every report goes on to Laravel's logging.
    //
    // Used by:
    //   - Laravel's handler constructor, when the container
    //     builds the handler
    // -----------------------------------------------------------

    public function register()
    {
        $this->reportable(function (Throwable $e) {
        });
    }
}
