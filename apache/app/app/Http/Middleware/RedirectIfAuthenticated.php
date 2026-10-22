<?php

// -----------------------------------------------------------
//  [*] RedirectIfAuthenticated — an unused guest check
//
//  Laravel's guest middleware, rewritten for the app's
//  guards and then replaced: the guest alias names
//  RedirectIfAuthenticatedWithGuard, and nothing names this
//  class. Dead code, kept as found — it could not run as
//  written anyway (see handle).
//
//  Used by:
//    - nothing at the moment
// -----------------------------------------------------------


namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;








// -----------------------------------------------------------
// RedirectIfAuthenticated
// -----------------------------------------------------------
//
// One method, handle. The RouteServiceProvider import is
// left over from Laravel's version and unused.
//
// Used by:
//   - nothing at the moment
// -----------------------------------------------------------

class RedirectIfAuthenticated
{






    // -----------------------------------------------------------
    // handle
    // -----------------------------------------------------------
    //
    // Would send a signed-in admin or player to their
    // dashboard. It reads $guard, which nothing defines — the
    // guards arrive in $guards — so PHP would warn and Laravel
    // turn the warning into a server error; the cert guard and
    // the cert_dashboard route it names exist nowhere in the
    // app either.
    //
    // Used by:
    //   - nothing at the moment
    // -----------------------------------------------------------

    public function handle(Request $request, Closure $next, ...$guards)
    {
        // Laravel's loop over the given guards is switched off;
        // the three checks below replaced it
        if ($guard =='admins' && Auth::guard($guard)->check()) {
            return redirect(route("admin_dashboard"));
        } elseif ($guard =='users' && Auth::guard($guard)->check()) {
            return redirect(route("user_dashboard"));
        } elseif ($guard =='cert' && Auth::guard($guard)->check()) {
            return redirect(route("cert_dashboard"));
        }

        return $next($request);
    }
}
