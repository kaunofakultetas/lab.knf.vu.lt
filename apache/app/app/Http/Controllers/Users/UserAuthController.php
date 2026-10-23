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
    // panel — go back to the login page with the error, an
    // account never verified with a fresh verification link
    // mailed, as the first may have expired; a good login
    // records the time and address and goes on to the page
    // the player first asked for, or the dashboard. A refused
    // form comes back with the e-mail refilled; the password
    // is left out, so it never sits in the session.
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
            return redirect()->route('login_users')->withErrors($validator)->withInput($request->except('password'));
        }

        if(Auth::guard('users')->attempt(['email' => $request->input('email'), 'password' => $request->input('password')])) {
            $user = Auth::guard('users')->user();

            if ($user->status == 1) {
                User::where(['id'=>Auth::guard('users')->id()])->update(['last_login'=>date("Y-m-d H:i:s"),'last_ip'=>SettingsController::getUserIP()]);

                return redirect()->intended('/user/dashboard');
            } else {
                Auth::guard('users')->logout();

                if (! empty($user->verify_token)) {
                    RegisterController::send_verification($user);

                    return redirect()->route('login_users')->withErrors(['Your account is not verified yet. We sent you a new verification link.']);
                }

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
    // POST /user/logout
    //
    // Signs the player out of the lab and goes to the login
    // page; an SSO session at VU stays open. A password
    // reminder mailed before stays good, as the User model
    // keeps Laravel's logout off remember_token.
    //
    // Used by:
    //   - Post_Link() of custom.js — the sign-out link of the
    //     player top bar, for local accounts
    //     (users/partials/topnav.blade.php)
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
    // no password. When a local player has registered that
    // username already, the account takes it with the first
    // free numbered suffix instead. The login time and address
    // are recorded. An account switched inactive in the
    // control panel gets null instead, and its login is not
    // recorded — as the login form refuses an inactive local
    // account.
    //
    // Used by:
    //   - SamlAuth::handle (Http/Middleware/) — the SSO login
    // -----------------------------------------------------------

    public static function check_member($memberarr)
    {
        $exists = User::where(['vu_id'=>$memberarr['urn:oid:0.9.2342.19200300.100.1.1'][0]])->where('vu_id','!=', NULL)->count();

        if($exists == 0){
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
    // Signs the player out of the lab, then sends the browser
    // to the SSO's logout, which returns to the site's front
    // page.
    //
    // Used by:
    //   - Post_Link() of custom.js — the sign-out link of the
    //     player top bar, for VU accounts
    //     (users/partials/topnav.blade.php)
    // -----------------------------------------------------------

    public function logout_saml()
    {
        Auth::guard('users')->logout();
        $as = new \SimpleSAML\Auth\Simple('default-sp');

        return redirect($as->getLogoutURL(env('APP_URL').'/'));
    }
}
