<?php

// -----------------------------------------------------------
//  [*] AuthenticateAdmin — the sign-in check of the panel
//
//  Laravel's auth middleware with the control panel's login
//  page: a guest who opens a page behind auth.admin:admins
//  is sent to the control-panel login, or gets a 401 when
//  the request asks for JSON. An admin is signed out at the
//  next request once the browser has signed out of VU
//  (SamlAuth::signedOut), as admins sign in through VU only.
//
//  Used by:
//    - app/Http/Kernel.php — the auth.admin alias
//    - routes/web.php — every control-panel page but the
//      login ones (auth.admin:admins)
// -----------------------------------------------------------


namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Support\Facades\Auth;








// -----------------------------------------------------------
// AuthenticateAdmin
// -----------------------------------------------------------
//
// Laravel checks the admins guard the routes name; this
// class adds the VU sign-out check and says where a refused
// guest goes.
//
//   handle     — the sign-in check, and the VU sign-out
//   redirectTo — where a refused guest goes
//
// Used by:
//   - app/Http/Kernel.php — the auth.admin alias
//   - routes/web.php — the control-panel pages
// -----------------------------------------------------------

class AuthenticateAdmin extends Middleware
{






    // -----------------------------------------------------------
    // handle
    // -----------------------------------------------------------
    //
    // Laravel's check that the visitor is signed in on the
    // route's guard, and the VU sign-out: an admin whose
    // browser has signed out of VU since signing in — at
    // sso.vu.lt, or in another VU service — is signed out of
    // the panel there and then and sent to its login page with
    // "Your VU sign-in has ended" (SamlAuth::signedOut).
    //
    // Used by:
    //   - the auth.admin alias — auth.admin:admins on the
    //     control-panel pages
    // -----------------------------------------------------------

    public function handle($request, Closure $next, ...$guards)
    {
        $this->authenticate($request, $guards);

        $admin = Auth::guard('admins')->user();

        if ($admin !== null && SamlAuth::signedOut($request, 'admins', $admin->id)) {
            Auth::guard('admins')->logout();
            SamlAuth::forgetSignOn('admins');

            return redirect()->route('login_admins')->withErrors(['Your VU sign-in has ended']);
        }

        return $next($request);
    }






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
