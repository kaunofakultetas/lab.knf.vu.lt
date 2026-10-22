<?php

// -----------------------------------------------------------
//  [*] RedirectIfAuthenticatedWithGuard — keeps the signed-in
//      off the login pages
//
//  The app's guest middleware: a visitor already signed in
//  is sent to a dashboard instead of the login, registration
//  and password pages — an admin to the control panel's, a
//  player to the player one. Someone signed in as both goes
//  to the control panel.
//
//  Used by:
//    - app/Http/Kernel.php — the guest alias
//    - routes/web.php — the player login, registration,
//      verification, reset and reminder routes, and the
//      control-panel login page
// -----------------------------------------------------------


namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;








// -----------------------------------------------------------
// RedirectIfAuthenticatedWithGuard
// -----------------------------------------------------------
//
// One method, handle; it checks both guards itself and
// takes no guard names.
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
    // through.
    //
    // Used by:
    //   - the guest alias — the routes above
    // -----------------------------------------------------------

    public function handle(Request $request, Closure $next)
    {
        if (Auth::guard('admins')->check()) {
            return redirect(route("admin_dashboard"));
        }
        if (Auth::guard('users')->check()) {
            return redirect(route("user_dashboard"));
        }

        return $next($request);
    }
}
