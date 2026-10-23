<?php

// -----------------------------------------------------------
//  [*] SamlAuthAdmin — the VU single sign-on of admins
//
//  The control panel's only way in: after the SSO round
//  trip (SimpleSAMLphp, the default-sp service provider),
//  the VU user id is matched to an admin account by
//  AdminAuthController::check_admin, which also copies the
//  admin's main flag into the session. A VU user without an
//  admin account is signed in as nobody, and the redirect
//  behind the route — to the panel page asked for before
//  the login, or the dashboard — ends on the control-panel
//  login page again.
//
//  Used by:
//    - app/Http/Kernel.php — the saml_auth_admin alias
//    - routes/web.php — GET /control-panel/login, the login
//      button of the control-panel login page
// -----------------------------------------------------------


namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Admins\AdminAuthController;
use App\Models\Admin;








// -----------------------------------------------------------
// SamlAuthAdmin
// -----------------------------------------------------------
//
// One method, handle.
//
// Used by:
//   - app/Http/Kernel.php — the saml_auth_admin alias
// -----------------------------------------------------------

class SamlAuthAdmin
{






    // -----------------------------------------------------------
    // handle
    // -----------------------------------------------------------
    //
    // requireAuth does the SSO round trip — it redirects and
    // stops the request when there is no SSO session yet.
    // With one, the admin check_admin finds is signed in on
    // the admins guard; nobody, when it finds none.
    //
    // Used by:
    //   - the saml_auth_admin alias — GET /control-panel/login
    // -----------------------------------------------------------

    public function handle($request, Closure $next)
    {
        $as = new \SimpleSAML\Auth\Simple('default-sp');
        $as->requireAuth();

        $attributes = $as->getAttributes();

        Auth::guard('admins')->loginUsingId(AdminAuthController::check_admin($attributes));

        return $next($request);
    }
}
