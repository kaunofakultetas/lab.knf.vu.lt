<?php

// -----------------------------------------------------------
//  [*] Controller — the base of every controller
//
//  Laravel's skeleton base controller: the routing
//  controller plus the authorize, dispatch and validate
//  helpers its traits bring, and the redirect that ends a
//  login. Most of the app's controllers extend it through
//  the player and control-panel bases.
//
//  Used by:
//    - Users\BaseController and Admins\BaseController — the
//      bases of the player and control-panel controllers
//    - Users\RegisterController and Admins\AdminAuthController
//      — directly
// -----------------------------------------------------------


namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\RedirectResponse;








// -----------------------------------------------------------
// Controller
// -----------------------------------------------------------
//
// Laravel's routing controller with its three helper
// traits, and one helper of the app's own.
//
//   intendedWithin — the redirect that ends a login
//
// Used by:
//   - Users\BaseController and Admins\BaseController
//   - Users\RegisterController and Admins\AdminAuthController
// -----------------------------------------------------------

class Controller extends BaseController
{

    // authorize, dispatch and validate, for every controller
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;






    // -----------------------------------------------------------
    // intendedWithin
    // -----------------------------------------------------------
    //
    // The redirect that ends a login: to the page the visitor
    // asked for before it, which Laravel keeps in the session
    // as url.intended, when that page is one of this site's
    // under the path given — the player side's or the control
    // panel's — and to the fallback otherwise. The page is
    // taken out of the session either way, so no later
    // redirect finds it, and a page of the other side, or of
    // another site, is never resumed: for a refused POST
    // Laravel keeps the address the visitor came from, which
    // may be anywhere. Nor is a dialog: the panel and the
    // player side load those into a modal, and one a guest
    // was bounced from would come back as a bare form.
    //
    // Used by:
    //   - Users\UserAuthController — login and cp
    //   - Admins\AdminAuthController::cp_login
    // -----------------------------------------------------------

    protected function intendedWithin(string $path, string $fallback): RedirectResponse
    {
        $intended = session()->pull('url.intended');

        if (is_string($intended) && str_starts_with($intended, url($path) . '/') && ! str_contains($intended, '/dialog/')) {
            return redirect($intended);
        }

        return redirect($fallback);
    }
}
