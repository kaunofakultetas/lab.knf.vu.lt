<?php

// -----------------------------------------------------------
//  [*] UserAuthController — the player logins
//
//  The two ways a player signs in, and out: the login form
//  of local accounts — e-mail and password, active accounts
//  only — and the VU single sign-on, whose middleware calls
//  check_member to find or create the player account of a
//  VU user. Both record the login time and address.
//
//    GET  /                 — the login page
//    POST /login            — the login form
//    GET  /cp               — the end of the SSO login
//    POST /user/logout      — sign-out
//    POST /user/logout_saml — sign-out, from the SSO too
//
//  Used by:
//    - routes/web.php — the routes above
//    - SamlAuth (Http/Middleware/) — check_member
// -----------------------------------------------------------


namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Users\SettingsController;
use App\Http\Middleware\SamlAuth;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;








// -----------------------------------------------------------
// UserAuthController
// -----------------------------------------------------------
//
//   index        — the login page
//   login        — the login form
//   logout       — sign-out
//   cp           — on to the page asked for, or the
//                  dashboard, after the SSO login
//   check_member — the player account of a VU user
//   logout_saml  — sign-out from the SSO too
//
// Used by:
//   - routes/web.php and SamlAuth
// -----------------------------------------------------------

class UserAuthController extends BaseController
{






    // -----------------------------------------------------------
    // index
    // -----------------------------------------------------------
    //
    // GET /
    //
    // The users.login view: the login form and the VU login
    // button. Signed-in visitors never see it — the guest
    // middleware sends them to their dashboard.
    //
    // Used by:
    //   - the site's front page, and every redirect to
    //     login_users
    // -----------------------------------------------------------

    public function index()
    {
        return view('users.login');
    }






    // -----------------------------------------------------------
    // login
    // -----------------------------------------------------------
    //
    // POST /login
    //
    // Signs a local account in by e-mail and password. A
    // refused form, wrong credentials and an inactive account
    // — one not verified yet, or switched off in the control
    // panel — go back to the login page with the error and the
    // e-mail refilled: the e-mail alone (refill), so the
    // password never sits in the session, nor anything else a
    // hand-made request adds. An account never verified is
    // mailed a fresh verification link, as the first may have
    // expired, but no more than one every 15 minutes
    // (RegisterController::send_verification), so signing in
    // again and again mails no flood of links to its address.
    // A good login records the time and address, forgets any
    // VU sign-in noted for the session — a password sign-in is
    // none, though a VU account an admin gave a password may
    // make one (SamlAuth::forgetSignOn) — and goes on to the
    // player page first asked for, or the dashboard
    // (intendedWithin, which never resumes a page elsewhere).
    //
    // Never verified means a verification token and no login
    // ever: an account switched off before 2026-10-23, when
    // the control panel's switch still left the token in
    // place, holds one too, and a fresh link would let its
    // owner switch it back on.
    //
    // By design the form takes any number of wrong passwords:
    // the route has no rate limit, no lockout and no reCAPTCHA.
    //
    // Used by:
    //   - the login form of users/login.blade.php
    // -----------------------------------------------------------

    public function login(Request $request, Response $response)
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:255']
        ]);

        if ($validator->fails()) {
            return redirect()->route('login_users')->withErrors($validator)->withInput($this->refill($request, ['email']));
        }

        if(Auth::guard('users')->attempt(['email' => $request->input('email'), 'password' => $request->input('password')])) {
            $user = Auth::guard('users')->user();

            if ($user->status == 1) {
                User::where(['id'=>Auth::guard('users')->id()])->update(['last_login'=>date("Y-m-d H:i:s"),'last_ip'=>SettingsController::getUserIP()]);
                SamlAuth::forgetSignOn('users');

                return $this->intendedWithin('/user', route('user_dashboard'));
            } else {
                Auth::guard('users')->logout();

                if (! empty($user->verify_token) && empty($user->last_login)) {
                    $error = RegisterController::send_verification($user)
                        ? 'Your account is not verified yet. We sent you a new verification link.'
                        : 'Your account is not verified yet. Use the verification link we mailed you.';

                    return redirect()->route('login_users')->withErrors([$error])->withInput($this->refill($request, ['email']));
                }

                return redirect()->route('login_users')->withErrors(['Your account is not active'])->withInput($this->refill($request, ['email']));
            }
        } else {
            return redirect()->route('login_users')->withErrors(['Wrong login credentials'])->withInput($this->refill($request, ['email']));
        }
    }






    // -----------------------------------------------------------
    // logout
    // -----------------------------------------------------------
    //
    // POST /user/logout
    //
    // Signs the player out of the lab and goes to the login
    // page; an SSO session at VU stays open. The page asked
    // for before a login is forgotten, so the next person to
    // sign in on the computer is not sent there, and the VU
    // sign-in noted for the session with it (SamlAuth::
    // forgetSignOn). A password reminder mailed before stays
    // good, as the User model keeps Laravel's logout off
    // remember_token.
    //
    // Used by:
    //   - Post_Link() of custom.js — the sign-out link of the
    //     player top bar, for local accounts
    //     (users/partials/topnav.blade.php)
    // -----------------------------------------------------------

    public function logout()
    {
        Auth::guard('users')->logout();
        SamlAuth::forgetSignOn('users');
        session()->forget('url.intended');

        return redirect('/');
    }






    // -----------------------------------------------------------
    // cp
    // -----------------------------------------------------------
    //
    // GET /cp
    //
    // The end of the SSO login: the saml_auth middleware has
    // signed the player in by now, so this only goes on, to
    // the player page asked for before the login — a shared
    // challenge link, say — or the dashboard, taking that
    // page out of the session (intendedWithin).
    //
    // Used by:
    //   - the VU login button of users/login.blade.php
    // -----------------------------------------------------------

    public function cp(Request $request)
    {
        return $this->intendedWithin('/user', route('user_dashboard'));
    }






    // -----------------------------------------------------------
    // check_member
    // -----------------------------------------------------------
    //
    // The id of the player account of the VU user the SSO
    // attributes describe — the uid, given name, surname and
    // mail attributes, by their OIDs. A VU user without an
    // account gets one, active at once: the VU user id as both
    // vu_id and username, the full name as display name, and
    // no password. When a local player has registered that
    // username already, the account takes it with the first
    // free numbered suffix instead. The login time and address
    // are recorded. An account switched inactive in the
    // control panel gets null instead, and its login is not
    // recorded — as the login form refuses an inactive local
    // account.
    //
    // A first login creates the account under a MySQL named
    // lock, counting the VU user's accounts again once it
    // holds it, as vu_id has no unique key: the same first
    // login sent twice at once — two tabs, a double click on
    // the login button — waits for the other one, then finds
    // the account it made, so the student gets one account
    // and no clash over the username.
    //
    // Used by:
    //   - SamlAuth::handle (Http/Middleware/) — the SSO login
    // -----------------------------------------------------------

    public static function check_member($memberarr)
    {
        $exists = User::where(['vu_id'=>$memberarr['urn:oid:0.9.2342.19200300.100.1.1'][0]])->where('vu_id','!=', NULL)->count();

        if($exists == 0){
            // Should the lock not come within 10 seconds, the
            // login goes on without it
            DB::select('SELECT GET_LOCK(?, 10)', ['cyber_users_first_vu_login']);

            try {
                if (User::where(['vu_id'=>$memberarr['urn:oid:0.9.2342.19200300.100.1.1'][0]])->where('vu_id','!=', NULL)->doesntExist()) {
                    $name = $memberarr['urn:oid:2.5.4.42'][0].' '.$memberarr['urn:oid:2.5.4.4'][0];

                    // The username must be free: a local player may have
                    // registered the VU user id as one
                    $username = $memberarr['urn:oid:0.9.2342.19200300.100.1.1'][0];
                    for ($n = 2; User::where(['username' => $username])->exists(); $n++) {
                        $username = $memberarr['urn:oid:0.9.2342.19200300.100.1.1'][0] . '_' . $n;
                    }

                    // The SSO's affiliation and group attributes are not
                    // stored; the fields that kept them are switched off
                    User::create([
                        'username'=>$username,
                        'vu_id'=>$memberarr['urn:oid:0.9.2342.19200300.100.1.1'][0],
                        'email'=>$memberarr['urn:oid:0.9.2342.19200300.100.1.3'][0],
                        'display_name'=>$name,
                        'status'=>1,
                    ]);
                }
            } finally {
                DB::select('SELECT RELEASE_LOCK(?)', ['cyber_users_first_vu_login']);
            }
        }

        $id = User::where(['vu_id'=>$memberarr['urn:oid:0.9.2342.19200300.100.1.1'][0]])->get()->pluck('id')->first();

        if (User::where(['id' => $id])->value('status') != 1) {
            return null;
        }

        User::where(['vu_id'=>$memberarr['urn:oid:0.9.2342.19200300.100.1.1'][0]])->where('vu_id','!=', NULL)->update(['last_login'=>date("Y-m-d H:i:s"), 'last_ip'=>SettingsController::getUserIP()]);

        return $id;
    }






    // -----------------------------------------------------------
    // logout_saml
    // -----------------------------------------------------------
    //
    // POST /user/logout_saml
    //
    // Signs the player out of the lab, forgets the page asked
    // for before a login and the VU sign-in noted for the
    // session, as logout does, then hands the browser to the
    // SSO's logout, which returns to the site's front page.
    // The hand-over is the sso_logout page, which moves on by
    // itself: a redirect of the sign-out form to the identity
    // provider would be stopped by the site's CSP, which lets
    // a form's navigation reach the site only. Sent from a
    // page whose lab session has ended, the sign-out's token
    // is refused, and the signout_retry page (VerifyCsrfToken)
    // offers it again, so the VU single sign-on still ends.
    //
    // SimpleSAMLphp tells sso.vu.lt about the sign-out only
    // while it still holds the VU login, a PHP session file:
    // the image keeps such a file for 36 hours, where PHP's
    // default would delete it after 24 minutes
    // (conf.d/session.ini, apache/Dockerfile), and the folder
    // SimpleSAMLphp keeps them in is mounted from the host, so
    // that a redeploy keeps them too.
    //
    // Used by:
    //   - Post_Link() of custom.js — the sign-out link of the
    //     player top bar, for VU accounts
    //     (users/partials/topnav.blade.php)
    // -----------------------------------------------------------

    public function logout_saml()
    {
        Auth::guard('users')->logout();
        SamlAuth::forgetSignOn('users');
        session()->forget('url.intended');
        $as = new \SimpleSAML\Auth\Simple('default-sp');

        return view('sso_logout', ['logout_url' => $as->getLogoutURL(env('APP_URL').'/')]);
    }
}
