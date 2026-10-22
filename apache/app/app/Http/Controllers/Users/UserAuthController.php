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
//    GET  /user/logout      — sign-out
//    GET  /user/logout_saml — sign-out, from the SSO too
//
//  Used by:
//    - routes/web.php — the routes above
//    - SamlAuth (Http/Middleware/) — check_member
// -----------------------------------------------------------


namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Users\SettingsController;
use Illuminate\Support\Facades\Auth;
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
//   dashboard    — dead: no route reaches it
//   cp           — on to the dashboard after the SSO login
//   check_member — the player account of a VU user
//   logout_saml  — sign-out from the SSO too
//
// Used by:
//   - routes/web.php and SamlAuth
// -----------------------------------------------------------

class UserAuthController extends BaseController
{

    // Laravel's landing page after a login; nothing reads it,
    // as login names the dashboard itself
    protected $redirectTo = '/user/dashboard';






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
    // panel — go back to the login page with the error; a
    // good login records the time and address and goes on to
    // the page the player first asked for, or the dashboard.
    //
    // Known bug LAB-02 (low): a refused form is sent back with
    // withInput() and no arguments (UserAuthController.php:131),
    // which flashes every field, the password included, into
    // the session until the next request. Fix: flash only the
    // e-mail.
    //
    // Known bug LAB-20 (medium): the form takes any number of
    // wrong passwords — the route has no rate limit, no lockout
    // and no reCAPTCHA (routes/web.php), so a password can be
    // guessed without end. Fix: throttle the route, by address
    // and e-mail.
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
            return redirect()->route('login_users')->withErrors($validator)->withInput();
        }

        if(Auth::guard('users')->attempt(['email' => $request->input('email'), 'password' => $request->input('password')])) {
            $user = Auth::guard('users')->user();

            if ($user->status == 1) {
                User::where(['id'=>Auth::guard('users')->id()])->update(['last_login'=>date("Y-m-d H:i:s"),'last_ip'=>SettingsController::getUserIP()]);

                return redirect()->intended('/user/dashboard');
            } else {
                Auth::guard('users')->logout();

                return redirect()->route('login_users')->withErrors(['Your account is not active']);
            }
        } else {
            return redirect()->route('login_users')->withErrors(['Wrong login credentials']);
        }
    }






    // -----------------------------------------------------------
    // logout
    // -----------------------------------------------------------
    //
    // GET /user/logout
    //
    // Signs the player out of the lab and goes to the login
    // page; an SSO session at VU stays open.
    //
    // Used by:
    //   - the sign-out link of the player top bar, for local
    //     accounts (users/partials/topnav.blade.php)
    // -----------------------------------------------------------

    public function logout()
    {
        Auth::guard('users')->logout();

        return redirect('/');
    }






    // -----------------------------------------------------------
    // dashboard
    // -----------------------------------------------------------
    //
    // Dead code: the users.dashboard view without its data. The
    // dashboard route goes to ChallengesController::index.
    //
    // Used by:
    //   - nothing at the moment
    // -----------------------------------------------------------

    public function dashboard(Request $request)
    {
        return view('users.dashboard');
    }






    // -----------------------------------------------------------
    // cp
    // -----------------------------------------------------------
    //
    // GET /cp
    //
    // The end of the SSO login: the saml_auth middleware has
    // signed the player in by now, so this only goes on to the
    // dashboard.
    //
    // Used by:
    //   - the VU login button of users/login.blade.php
    // -----------------------------------------------------------

    public function cp(Request $request)
    {
        return redirect('/user/dashboard');
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
    // no password. The login time and address are recorded
    // either way.
    //
    // Known bug LAB-15 (medium): the account's status is never
    // looked at, so an account switched inactive in the
    // control panel still signs in through SamlAuth. Fix:
    // refuse inactive accounts here or in the middleware.
    //
    // Known bug LAB-16 (medium): a local player who registered
    // under a username equal to a VU user id locks that VU
    // user out — the new account takes the VU user id as
    // username (UserAuthController.php:265), hits the
    // unique username index and ends in a server error, at
    // every SSO login. Fix: give the SSO account a username
    // that is still free.
    //
    // Used by:
    //   - SamlAuth::handle (Http/Middleware/) — the SSO login
    // -----------------------------------------------------------

    public static function check_member($memberarr)
    {
        $exists = User::where(['vu_id'=>$memberarr['urn:oid:0.9.2342.19200300.100.1.1'][0]])->where('vu_id','!=', NULL)->count();

        if($exists == 0){
            $name = $memberarr['urn:oid:2.5.4.42'][0].' '.$memberarr['urn:oid:2.5.4.4'][0];

            // The SSO's affiliation and group attributes are not
            // stored; the fields that kept them are switched off
            User::create([
                'username'=>$memberarr['urn:oid:0.9.2342.19200300.100.1.1'][0],
                'vu_id'=>$memberarr['urn:oid:0.9.2342.19200300.100.1.1'][0],
                'email'=>$memberarr['urn:oid:0.9.2342.19200300.100.1.3'][0],
                'display_name'=>$name,
                'status'=>1,
            ]);
        }

        $id = User::where(['vu_id'=>$memberarr['urn:oid:0.9.2342.19200300.100.1.1'][0]])->get()->pluck('id')->first();

        User::where(['vu_id'=>$memberarr['urn:oid:0.9.2342.19200300.100.1.1'][0]])->where('vu_id','!=', NULL)->update(['last_login'=>date("Y-m-d H:i:s"), 'last_ip'=>SettingsController::getUserIP()]);

        return $id;
    }






    // -----------------------------------------------------------
    // logout_saml
    // -----------------------------------------------------------
    //
    // GET /user/logout_saml
    //
    // Signs the player out of the lab, then sends the browser
    // to the SSO's logout, which returns to the site's front
    // page.
    //
    // Used by:
    //   - the sign-out link of the player top bar, for VU
    //     accounts (users/partials/topnav.blade.php)
    // -----------------------------------------------------------

    public function logout_saml()
    {
        Auth::guard('users')->logout();
        $as = new \SimpleSAML\Auth\Simple('default-sp');

        return redirect($as->getLogoutURL(env('APP_URL').'/'));
    }
}
