<?php

// -----------------------------------------------------------
//  [*] VerifyCsrfToken — the CSRF check of the web routes
//
//  Laravel's CSRF check: every POST, PUT, PATCH and DELETE
//  of the web group must carry the session's token, in the
//  _token field or the X-CSRF-TOKEN header, or it ends on
//  the 419 page. GET requests are never checked, so every
//  change the control panel makes from its lists is a POST,
//  which its script sends with the token of the page's
//  csrf-token meta tag. A sign-out refused this way gets a
//  page that offers it again instead of the error page.
//
//  Used by:
//    - app/Http/Kernel.php — the web group
// -----------------------------------------------------------


namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Illuminate\Session\TokenMismatchException;








// -----------------------------------------------------------
// VerifyCsrfToken
// -----------------------------------------------------------
//
// Laravel's middleware with an empty except list, and its
// own answer to a refused sign-out.
//
//   handle — the check, and the sign-out page it may answer
//
// Used by:
//   - app/Http/Kernel.php — the web group
// -----------------------------------------------------------

class VerifyCsrfToken extends Middleware
{

    // The addresses spared the check — none, as every POST of
    // the lab carries its page's token
    protected $except = [
    ];

    // The sign-outs, whose refused token gets the page that
    // offers them again
    private const SIGN_OUTS = [
        'user/logout',
        'user/logout_saml',
        'control-panel/logout',
    ];






    // -----------------------------------------------------------
    // handle
    // -----------------------------------------------------------
    //
    // Laravel's check, but a sign-out whose token it refuses
    // gets the signout_retry page, still with status 419,
    // rather than the error page. Such a sign-out comes from
    // a page that outlived its session — a long-open tab, or
    // Redis emptied by a stack update — and nothing was signed
    // out: for the VU sign-outs the single sign-on stays open,
    // and on a shared computer the next "Login with VU SSO"
    // would sign in as the same person. The page offers the
    // sign-out again under the session's current token. It
    // waits for a press rather than sending itself, so that no
    // other site can sign a visitor out of the VU single
    // sign-on by posting here.
    //
    // Used by:
    //   - app/Http/Kernel.php — the web group, before every
    //     route of the site
    // -----------------------------------------------------------

    public function handle($request, Closure $next)
    {
        try {
            return parent::handle($request, $next);
        } catch (TokenMismatchException $e) {
            if (! $request->is(...self::SIGN_OUTS)) {
                throw $e;
            }

            return response()->view('signout_retry', ['action' => $request->url()], 419);
        }
    }
}
