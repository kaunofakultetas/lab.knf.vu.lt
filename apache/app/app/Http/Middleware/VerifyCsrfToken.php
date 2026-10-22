<?php

// -----------------------------------------------------------
//  [*] VerifyCsrfToken — the CSRF check of the web routes
//
//  Laravel's CSRF check: every POST, PUT, PATCH and DELETE
//  of the web group must carry the session's token, in the
//  _token field or the X-CSRF-TOKEN header, or it ends on
//  the 419 page. GET requests are never checked — and the
//  control panel's delete and switch links are GETs
//  (LAB-19).
//
//  Used by:
//    - app/Http/Kernel.php — the web group
// -----------------------------------------------------------


namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;








// -----------------------------------------------------------
// VerifyCsrfToken
// -----------------------------------------------------------
//
// Laravel's middleware with one exception that spares
// nothing.
//
// Used by:
//   - app/Http/Kernel.php — the web group
// -----------------------------------------------------------

class VerifyCsrfToken extends Middleware
{

    // The addresses spared the check. The one entry spares
    // nothing: the vote route is a GET with two more path
    // segments, and the bare path has no route
    protected $except = [
        '/user/challenge/vote'
    ];
}
