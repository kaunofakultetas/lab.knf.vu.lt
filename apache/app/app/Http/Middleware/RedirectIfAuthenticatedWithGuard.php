<?php

// -----------------------------------------------------------
//  [*] RedirectIfAuthenticatedWithGuard — keeps the signed-in
//      off the login pages
//
//  The app's guest middleware: a visitor already signed in
//  is sent to a dashboard instead of the login, registration
//  and password pages — an admin to the control panel's, a
//  player to the player one. Someone signed in as both goes
//  to the control panel. A route may name the guards that
//  count, as the control panel's login page does with
//  admins: a player signed in on the player side still
//  reaches it.
//
//  Used by:
//    - app/Http/Kernel.php — the guest alias
//    - routes/web.php — the player login, registration,
//      verification, reset and reminder routes (guest), and
//      the control-panel login page (guest:admins)
// -----------------------------------------------------------


namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;








// -----------------------------------------------------------
// RedirectIfAuthenticatedWithGuard
// -----------------------------------------------------------
//
// One method, handle; with no guard names it checks both
// guards.
//
// Used by:
//   - app/Http/Kernel.php — the guest alias
// -----------------------------------------------------------

class RedirectIfAuthenticatedWithGuard
{






    // -----------------------------------------------------------
    // handle
    // -----------------------------------------------------------
    //
    // Redirects a signed-in admin to admin_dashboard and a
    // signed-in player to user_dashboard; lets a guest
    // through. The guards are the ones the route names after
    // the alias, admins and users when it names none; a
    // session of a guard left out passes as a guest.
    //
    // Used by:
    //   - the guest alias — the routes above
    // -----------------------------------------------------------

    public function handle(Request $request, Closure $next, string ...$guards)
    {
        $guards = $guards ?: ['admins', 'users'];

        if (in_array('admins', $guards, true) && Auth::guard('admins')->check()) {
            return redirect(route("admin_dashboard"));
        }
        if (in_array('users', $guards, true) && Auth::guard('users')->check()) {
            return redirect(route("user_dashboard"));
        }

        return $next($request);
    }
}
