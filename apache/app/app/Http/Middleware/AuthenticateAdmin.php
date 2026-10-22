<?php

// -----------------------------------------------------------
//  [*] AuthenticateAdmin — the sign-in check of the panel
//
//  Laravel's auth middleware with the control panel's login
//  page: a guest who opens a page behind auth.admin:admins
//  is sent to the control-panel login, or gets a 401 when
//  the request asks for JSON.
//
//  Used by:
//    - app/Http/Kernel.php — the auth.admin alias
//    - routes/web.php — every control-panel page but the
//      login ones (auth.admin:admins)
// -----------------------------------------------------------


namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;








// -----------------------------------------------------------
// AuthenticateAdmin
// -----------------------------------------------------------
//
// Laravel checks the admins guard the routes name; this
// class only says where a refused guest goes.
//
// Used by:
//   - app/Http/Kernel.php — the auth.admin alias
//   - routes/web.php — the control-panel pages
// -----------------------------------------------------------

class AuthenticateAdmin extends Middleware
{






    // -----------------------------------------------------------
    // redirectTo
    // -----------------------------------------------------------
    //
    // The control-panel login (the login_admins route) for a
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
            return route('login_admins');
        }
    }
}
