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
//  player page asked for before the login, or the
//  dashboard. An account switched inactive in the control
//  panel is turned back to the login page instead, and
//  whoever was signed in on the browser is signed out.
//
//  A VU sign-in ends with the VU sign-on: SimpleSAMLphp is
//  asked to call recordSignOut whenever it signs the
//  browser out of default-sp — the sign-out of sso.vu.lt or
//  of another VU service passed on to the lab, the lab's own
//  sign-outs, and the sign-on a new VU login replaces —
//  which leaves a cookie holding the time. On the next page
//  the auth middleware of either side ends a session signed
//  in through VU before that time (signedOut); a VU sign-in
//  made after it stays, so the cookie is never removed, and
//  a password sign-in is no VU sign-in (forgetSignOn). The
//  sign-on running out of time ends nothing: the lab
//  session keeps its own 36 hours.
//
//  Used by:
//    - app/Http/Kernel.php — the saml_auth alias
//    - routes/web.php — GET /cp, the "Login with VU SSO"
//      button of the player login page
//    - SamlAuthAdmin, Authenticate and AuthenticateAdmin —
//      the VU sign-out
// -----------------------------------------------------------


namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Users\UserAuthController;
use Symfony\Component\HttpFoundation\Cookie;








// -----------------------------------------------------------
// SamlAuth
// -----------------------------------------------------------
//
//   handle          — the VU sign-in of a player
//   watchSignOut    — ties a VU sign-in to the VU sign-on
//   recordSignOut   — SimpleSAMLphp's call at a VU sign-out
//   signedOutCookie — the cookie that call leaves
//   signedOut       — whether a session's VU sign-on ended
//   forgetSignOn    — a session no longer signed in by VU
//
// Used by:
//   - app/Http/Kernel.php — the saml_auth alias
//   - SamlAuthAdmin, Authenticate and AuthenticateAdmin
// -----------------------------------------------------------

class SamlAuth
{

    // The cookie a VU sign-out leaves: __Host- keeps any other
    // host under vu.lt from setting it
    public const SIGNED_OUT_COOKIE = '__Host-vu_knf_lab_vu_signed_out';






    // -----------------------------------------------------------
    // handle
    // -----------------------------------------------------------
    //
    // requireAuth does the SSO round trip — it redirects and
    // stops the request when there is no SSO session yet.
    // With one, the player account UserAuthController::
    // check_member finds or creates for the attributes is
    // signed in and tied to the VU sign-on (watchSignOut).
    // For an inactive account check_member returns no id: the
    // visitor goes to the login page with the message the
    // login form gives an inactive account, and the player
    // signed in on the browser before, if any, is signed out,
    // so the refusal is what the visitor sees.
    //
    // Used by:
    //   - the saml_auth alias — GET /cp
    // -----------------------------------------------------------

    public function handle($request, Closure $next)
    {
        $as = new \SimpleSAML\Auth\Simple('default-sp');
        $as->requireAuth();

        $attributes = $as->getAttributes();

        $id = UserAuthController::check_member($attributes);

        if ($id === null) {
            Auth::guard('users')->logout();
            self::forgetSignOn('users');

            return redirect()->route('login_users')->withErrors(['Your account is not active']);
        }

        Auth::guard('users')->loginUsingId($id);
        self::watchSignOut('users', (int) $id);

        return $next($request);
    }






    // -----------------------------------------------------------
    // watchSignOut
    // -----------------------------------------------------------
    //
    // Ties the account just signed in on the guard given to
    // the VU sign-on: the session notes the account and the
    // time under vu_sign_on, and SimpleSAMLphp's session gets
    // recordSignOut as a logout handler of default-sp, once —
    // SimpleSAMLphp keeps it with the sign-on and calls it
    // whenever it signs the browser out.
    //
    // Used by:
    //   - handle (above)
    //   - SamlAuthAdmin::handle
    // -----------------------------------------------------------

    public static function watchSignOut(string $guard, int $id): void
    {
        session(["vu_sign_on.{$guard}" => ['id' => $id, 'at' => time()]]);

        $session = \SimpleSAML\Session::getSessionFromRequest();
        $handlers = $session->getAuthState('default-sp')['LogoutHandlers'] ?? [];

        if (! in_array([self::class, 'recordSignOut'], $handlers, true)) {
            $session->registerLogoutHandler('default-sp', self::class, 'recordSignOut');
        }
    }






    // -----------------------------------------------------------
    // recordSignOut
    // -----------------------------------------------------------
    //
    // Runs inside SimpleSAMLphp, not Laravel, as it signs the
    // browser out of default-sp — the identity provider's
    // sign-out passed on to the lab, the lab's own, or the
    // sign-on a new VU login replaces — and leaves the
    // signed-out cookie on the answer, so the browser's next
    // page of the lab ends the sessions signed in through VU
    // before it.
    //
    // Used by:
    //   - SimpleSAMLphp — the logout handler watchSignOut
    //     registers
    // -----------------------------------------------------------

    public static function recordSignOut(): void
    {
        header('Set-Cookie: ' . self::signedOutCookie(), false);
    }






    // -----------------------------------------------------------
    // signedOutCookie
    // -----------------------------------------------------------
    //
    // The cookie recordSignOut leaves, holding the Unix time
    // of the sign-out: for the whole site and its host alone,
    // sent over HTTPS only, unseen by scripts, kept 36 hours,
    // as long as a lab session lasts, and allowed across sites
    // (SameSite=None), as SimpleSAMLphp's own cookies are, so
    // an identity provider that signs the services out in
    // frames reaches it too.
    //
    // Used by:
    //   - recordSignOut (above)
    // -----------------------------------------------------------

    public static function signedOutCookie(): Cookie
    {
        return Cookie::create(self::SIGNED_OUT_COOKIE, (string) time(), time() + 36 * 3600, '/', null, true, true, false, Cookie::SAMESITE_NONE);
    }






    // -----------------------------------------------------------
    // signedOut
    // -----------------------------------------------------------
    //
    // Whether the account signed in on the guard given came
    // through VU (watchSignOut noted it, and when, in the
    // session) and the browser has signed out of VU since:
    // the signed-out cookie holds a later time. A local
    // account, or a VU sign-in made after the sign-out — on
    // either side — is never ended.
    //
    // Used by:
    //   - Authenticate::handle and AuthenticateAdmin::handle
    // -----------------------------------------------------------

    public static function signedOut(Request $request, string $guard, int $id): bool
    {
        $out = $request->cookies->get(self::SIGNED_OUT_COOKIE);
        $on = session("vu_sign_on.{$guard}");

        return is_numeric($out) && is_array($on) && (int) ($on['id'] ?? 0) === $id && (int) ($on['at'] ?? 0) < (int) $out;
    }






    // -----------------------------------------------------------
    // forgetSignOn
    // -----------------------------------------------------------
    //
    // Forgets that the guard's session came through VU: at its
    // sign-out, at a password sign-in — no VU sign-in, though
    // the account may be a VU one an admin gave a password —
    // and when a VU sign-out ends it.
    //
    // Used by:
    //   - handle (above) and SamlAuthAdmin::handle — a refused
    //     VU login
    //   - Authenticate::handle and AuthenticateAdmin::handle
    //   - Users\UserAuthController — login, logout, logout_saml
    //   - Admins\AdminAuthController::logout
    // -----------------------------------------------------------

    public static function forgetSignOn(string $guard): void
    {
        session()->forget("vu_sign_on.{$guard}");
    }
}
