<?php

// -----------------------------------------------------------
//  [*] SamlAuth — the VU single sign-on of players
//
//  Signs a player in through VU's single sign-on
//  (SimpleSAMLphp, the default-sp service provider): a
//  visitor without an SSO session is sent to the VU login
//  and comes back here. The VU user id the identity
//  provider returns is matched to a player account —
//  created on the first visit — which is signed in on the
//  users guard; the route behind, /cp, then opens the
//  dashboard.
//
//  Used by:
//    - app/Http/Kernel.php — the saml_auth alias
//    - routes/web.php — GET /cp, the "VU login" button of
//      the player login page
// -----------------------------------------------------------


namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Users\UserAuthController;
use App\Models\User;








// -----------------------------------------------------------
// SamlAuth
// -----------------------------------------------------------
//
// One method, handle.
//
// Used by:
//   - app/Http/Kernel.php — the saml_auth alias
// -----------------------------------------------------------

class SamlAuth
{






    // -----------------------------------------------------------
    // handle
    // -----------------------------------------------------------
    //
    // requireAuth does the SSO round trip — it redirects and
    // stops the request when there is no SSO session yet.
    // With one, the player account UserAuthController::
    // check_member finds or creates for the attributes is
    // signed in.
    //
    // Known bug LAB-15 (medium): an account switched inactive
    // in the control panel still signs in — whatever id
    // check_member returns is signed in here
    // (SamlAuth.php:85), and neither looks at the status the
    // local login form checks. Fix: refuse inactive accounts
    // in check_member or here.
    //
    // Used by:
    //   - the saml_auth alias — GET /cp
    // -----------------------------------------------------------

    public function handle($request, Closure $next)
    {
        $as = new \SimpleSAML\Auth\Simple('default-sp');
        $as->requireAuth();

        $attributes = $as->getAttributes();

        Auth::guard('users')->loginUsingId(UserAuthController::check_member($attributes));

        return $next($request);
    }
}
