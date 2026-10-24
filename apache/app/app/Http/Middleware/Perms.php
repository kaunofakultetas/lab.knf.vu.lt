<?php

// -----------------------------------------------------------
//  [*] Perms — the main-admin check of the control panel
//
//  Keeps the main-only parts of the control panel — the
//  categories, partners, pages, mail templates and settings,
//  account edits, status and deletion, challenge status — to
//  main admins. Anyone else is sent back to the page they
//  came from with "You don't have permissions to do this!".
//
//  Used by:
//    - app/Http/Kernel.php — the perms alias
//    - routes/web.php — the main-only control-panel routes
// -----------------------------------------------------------


namespace App\Http\Middleware;

use Closure;
use Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;








// -----------------------------------------------------------
// Perms
// -----------------------------------------------------------
//
// One method, handle.
//
// Used by:
//   - app/Http/Kernel.php — the perms alias
// -----------------------------------------------------------

class Perms
{






    // -----------------------------------------------------------
    // handle
    // -----------------------------------------------------------
    //
    // Passes the request when the signed-in admin's account
    // is a main admin's, and redirects back with the error
    // otherwise. The flag is read from the account on every
    // request, so rights taken away end at once, not at the
    // next sign-in.
    //
    // Known bug LAB-81 (low): back() from a page the browser
    // reached from a main-only page is that page again,
    // refused again, so a demoted main admin who presses
    // anything on a main-only page is redirected to it until
    // the browser gives up. Fix: send a refused page to the
    // dashboard.
    //
    // Used by:
    //   - the perms alias — the main-only routes
    // -----------------------------------------------------------

    public function handle($request, Closure $next)
    {
        if(Auth::guard('admins')->user()?->main == 1){
            return $next($request);
        } else {
            return back()->withErrors(['You don\'t have permissions to do this!']);
        }
    }
}
