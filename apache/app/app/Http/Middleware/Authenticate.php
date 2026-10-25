<?php

// -----------------------------------------------------------
//  [*] Authenticate — the sign-in check of the player pages
//
//  Laravel's auth middleware with the app's login page: a
//  guest who opens a page behind auth:users is sent to the
//  player login form, or gets a 401 when the request asks
//  for JSON. A player whose account was switched off after
//  signing in is signed out at the next request, and so is
//  one signed in through VU once the browser has signed out
//  of VU (SamlAuth::signedOut).
//
//  Used by:
//    - app/Http/Kernel.php — the auth alias
//    - routes/web.php — the player pages under /user
//      (auth:users)
//    - routes/api.php — /api/user (auth:sanctum)
// -----------------------------------------------------------


namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Support\Facades\Auth;








// -----------------------------------------------------------
// Authenticate
// -----------------------------------------------------------
//
// Laravel checks the guards the route names; this class
// adds the status and VU sign-out checks of the users guard
// and says where a refused guest goes.
//
//   handle     — the sign-in check, the account's status
//                and the VU sign-out
//   redirectTo — where a refused guest goes
//
// Used by:
//   - app/Http/Kernel.php — the auth alias
//   - routes/web.php and routes/api.php
// -----------------------------------------------------------

class Authenticate extends Middleware
{






    // -----------------------------------------------------------
    // handle
    // -----------------------------------------------------------
    //
    // Laravel's check that the visitor is signed in on one of
    // the route's guards, and one more on the users guard: an
    // account whose status is no longer 1 — switched off in
    // the control panel after the player signed in — is
    // signed out there and then and sent to the login page
    // with "Your account is not active", the login form's own
    // message, so the switch takes effect at once rather than
    // when the session runs out. A player signed in through
    // VU is signed out the same way, with "Your VU sign-in has
    // ended", once the browser has signed out of VU — at
    // sso.vu.lt, or in another VU service (SamlAuth::
    // signedOut).
    //
    // Used by:
    //   - the auth alias — auth:users on the player pages,
    //     auth:sanctum on /api/user
    // -----------------------------------------------------------

    public function handle($request, Closure $next, ...$guards)
    {
        $this->authenticate($request, $guards);

        $player = in_array('users', $guards, true) ? Auth::guard('users')->user() : null;

        if ($player !== null && (int) $player->status !== 1) {
            Auth::guard('users')->logout();

            return redirect()->route('login_users')->withErrors(['Your account is not active']);
        }

        if ($player !== null && SamlAuth::signedOut($request, 'users', $player->id)) {
            Auth::guard('users')->logout();
            SamlAuth::forgetSignOn('users');

            return redirect()->route('login_users')->withErrors(['Your VU sign-in has ended']);
        }

        return $next($request);
    }






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
