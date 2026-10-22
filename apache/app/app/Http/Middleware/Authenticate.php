<?php

// -----------------------------------------------------------
//  [*] Authenticate — the sign-in check of the player pages
//
//  Laravel's auth middleware with the app's login page: a
//  guest who opens a page behind auth:users is sent to the
//  player login form, or gets a 401 when the request asks
//  for JSON.
//
//  Used by:
//    - app/Http/Kernel.php — the auth alias
//    - routes/web.php — the player pages under /user
//      (auth:users)
//    - routes/api.php — /api/user (auth:sanctum, LAB-05)
// -----------------------------------------------------------


namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;








// -----------------------------------------------------------
// Authenticate
// -----------------------------------------------------------
//
// Laravel checks the guards the route names; this class
// only says where a refused guest goes.
//
// Used by:
//   - app/Http/Kernel.php — the auth alias
//   - routes/web.php and routes/api.php
// -----------------------------------------------------------

class Authenticate extends Middleware
{






    // -----------------------------------------------------------
    // redirectTo
    // -----------------------------------------------------------
    //
    // The player login form (the login_users route) for a
    // browser; nothing for a JSON request, which Laravel then
    // answers with a 401.
    //
    // Used by:
    //   - Laravel's Authenticate middleware, when a guest is
    //     refused
    // -----------------------------------------------------------

    protected function redirectTo($request)
    {
        if (! $request->expectsJson()) {
            return route('login_users');
        }
    }
}
