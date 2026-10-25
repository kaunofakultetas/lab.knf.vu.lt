<?php

// -----------------------------------------------------------
//  [*] Controller — the base of every controller
//
//  Laravel's skeleton base controller: the routing
//  controller plus the authorize, dispatch and validate
//  helpers its traits bring, the redirect that ends a
//  login, and the refill of a refused form. Most of the
//  app's controllers extend it through the player and
//  control-panel bases.
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
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Exception\RequestExceptionInterface;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;








// -----------------------------------------------------------
// Controller
// -----------------------------------------------------------
//
// Laravel's routing controller with its three helper
// traits, and one helper of the app's own.
//
//   intendedWithin — the redirect that ends a login
//   opensByGet     — whether a GET opens an address
//   refill         — what a refused form shows again
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
    // was bounced from would come back as a bare form. Nor is
    // an address a GET does not open (opensByGet).
    //
    // Used by:
    //   - Users\UserAuthController — login and cp
    //   - Admins\AdminAuthController::cp_login
    // -----------------------------------------------------------

    protected function intendedWithin(string $path, string $fallback): RedirectResponse
    {
        $intended = session()->pull('url.intended');

        if (is_string($intended) && str_starts_with($intended, url($path) . '/') && ! str_contains($intended, '/dialog/') && $this->opensByGet($intended)) {
            return redirect($intended);
        }

        return redirect($fallback);
    }






    // -----------------------------------------------------------
    // opensByGet
    // -----------------------------------------------------------
    //
    // Whether a GET opens the address, by the site's routes. A
    // guest's refused POST leaves the address the form was
    // sent from as the page asked for, and that may answer
    // POST alone: the sign-out offered again after a session
    // ended is served at the sign-out's own address, and
    // resuming it would end the next login on a 405. An
    // address Request::create refuses outright — Symfony 7.4
    // refuses a backslash in the path, which only a hand-made
    // request leaves — opens nothing either, so such a login
    // ends on the fallback rather than a 400.
    //
    // Used by:
    //   - intendedWithin (above)
    // -----------------------------------------------------------

    private function opensByGet(string $url): bool
    {
        try {
            app('router')->getRoutes()->match(Request::create($url, 'GET'));
        } catch (HttpExceptionInterface|RequestExceptionInterface $e) {
            return false;
        }

        return true;
    }






    // -----------------------------------------------------------
    // refill
    // -----------------------------------------------------------
    //
    // The fields of a refused form to show again on the next
    // page: of the names given, those sent as text of at most
    // 255 characters, the longest any of these fields takes.
    // A refused form is flashed into the session, which lives
    // in Redis for the lab's 36 hours, so only the fields the
    // form shows are kept — never the made-up ones a hand-made
    // request adds, nor a field a megabyte long.
    //
    // Used by:
    //   - Users\UserAuthController::login
    //   - Users\RegisterController — store and forgotpassword
    // -----------------------------------------------------------

    protected function refill(Request $request, array $fields): array
    {
        return array_filter($request->only($fields), fn ($value) => is_string($value) && mb_strlen($value) <= 255);
    }
}
