<?php

// -----------------------------------------------------------
//  [*] VerifyCsrfToken — the CSRF check of the web routes
//
//  Laravel's CSRF check: every POST, PUT, PATCH and DELETE
//  of the web group must carry the session's token, in the
//  _token field or the X-CSRF-TOKEN header, or it ends on
//  the 419 page. GET requests are never checked, so every
//  change the control panel makes from its lists is a POST,
//  which its script sends with the token of the page's
//  csrf-token meta tag.
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
// Laravel's middleware with an empty except list.
//
// Used by:
//   - app/Http/Kernel.php — the web group
// -----------------------------------------------------------

class VerifyCsrfToken extends Middleware
{

    // The addresses spared the check — none, as every POST of
    // the lab carries its page's token
    protected $except = [
    ];
}
