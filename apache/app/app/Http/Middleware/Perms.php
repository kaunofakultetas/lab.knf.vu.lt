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
    // Passes the request when the session's main flag is 1 —
    // the flag the SSO login copies from the admin's account
    // (AdminAuthController::check_admin) — and redirects back
    // with the error otherwise.
    //
    // Known bug LAB-28 (medium): the flag is read from the
    // session, never from the account (Perms.php:73), so
    // an admin whose main rights are taken away keeps them
    // until signing in again. Fix: read the flag from the
    // signed-in admin's row.
    //
    // Used by:
    //   - the perms alias — the main-only routes
    // -----------------------------------------------------------

    public function handle($request, Closure $next)
    {
        if(Session::get('main') == 1){
            return $next($request);
        } else {
            return back()->withErrors(['You don\'t have permissions to do this!']);
        }
    }
}
