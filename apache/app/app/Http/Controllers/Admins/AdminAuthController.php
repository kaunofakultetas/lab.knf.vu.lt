<?php

// -----------------------------------------------------------
//  [*] AdminAuthController — the control-panel logins
//
//  The control panel is entered through the VU single
//  sign-on only: its login page offers the SSO button, the
//  saml_auth_admin middleware signs the admin in through
//  check_admin, and the sign-out ends the SSO session too,
//  through the sso_logout page.
//
//    GET  /control-panel        — the login page
//    GET  /control-panel/login  — the end of the SSO login
//    POST /control-panel/logout — sign-out
//
//  Used by:
//    - routes/web.php — the routes above
//    - SamlAuthAdmin (Http/Middleware/) — check_admin
// -----------------------------------------------------------


namespace App\Http\Controllers\Admins;

use Session;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Admin;
use App\Models\ChallengesSubmitted;
use Illuminate\Http\Request;








// -----------------------------------------------------------
// AdminAuthController
// -----------------------------------------------------------
//
// Extends the bare base controller, as the login page needs
// none of the panel's layout data.
//
//   index       — the login page
//   logout      — sign-out, from the SSO too
//   cp_login    — on to the page asked for, or the
//                 dashboard, after the SSO login
//   check_admin — the admin account of a VU user
//
// Used by:
//   - routes/web.php and SamlAuthAdmin
// -----------------------------------------------------------

class AdminAuthController extends Controller
{






    // -----------------------------------------------------------
    // index
    // -----------------------------------------------------------
    //
    // GET /control-panel
    //
    // The admins.login view, whose only control is the
    // "Login with VU SSO" button. A signed-in admin never sees
    // it — the guest middleware, counting admin sessions only
    // here, sends it to the admin dashboard — while a player
    // signed in on the player side does, so a lab teacher
    // trying the challenges can still reach the panel.
    //
    // Used by:
    //   - every redirect to login_admins, a refused control-
    //     panel page among them
    // -----------------------------------------------------------

    public function index()
    {
        return view('admins.login');
    }






    // -----------------------------------------------------------
    // logout
    // -----------------------------------------------------------
    //
    // POST /control-panel/logout
    //
    // Signs the admin out of the panel, forgets the page asked
    // for before a login, then hands the browser to the SSO's
    // logout, which returns to the control-panel login page.
    // The hand-over is the sso_logout page, which moves on by
    // itself, as the site's CSP would stop a redirect of the
    // sign-out form on its way to the identity provider. The
    // session — and its main flag — stays. The route asks for
    // no admin session, so a panel page whose session has
    // ended still ends the VU single sign-on: its refused
    // token gets the signout_retry page (VerifyCsrfToken),
    // whose button posts here again.
    //
    // Used by:
    //   - Post_Link() of custom_admin.js — the sign-out link of
    //     the panel's top bar (admins/partials/topnav.blade.php)
    // -----------------------------------------------------------

    public function logout()
    {
        // The plain sign-out, back to the login page without
        // leaving the SSO, is switched off
        Auth::guard('admins')->logout();
        session()->forget('url.intended');
        $as = new \SimpleSAML\Auth\Simple('default-sp');

        return view('sso_logout', ['logout_url' => $as->getLogoutURL(env('APP_URL').'/control-panel')]);
    }






    // -----------------------------------------------------------
    // cp_login
    // -----------------------------------------------------------
    //
    // GET /control-panel/login
    //
    // The end of the SSO login: the saml_auth_admin middleware
    // has signed the admin in by now, so this only goes on, to
    // the control-panel page asked for before the login or the
    // dashboard, taking that page out of the session
    // (intendedWithin). Its auth.admin check sends a VU user
    // without an admin account back to the login page.
    //
    // Used by:
    //   - the "Login with VU SSO" button of
    //     admins/login.blade.php
    // -----------------------------------------------------------

    public function cp_login(Request $request)
    {
        return $this->intendedWithin('/control-panel', route('admin_dashboard'));
    }






    // -----------------------------------------------------------
    // check_admin
    // -----------------------------------------------------------
    //
    // The id of the admin account whose vu_id is the uid the
    // SSO attributes carry, or null for a VU user who is no
    // admin. For an admin it also copies the account's main
    // flag into the session, where nothing reads it any more:
    // the checks read the account itself.
    //
    // Used by:
    //   - SamlAuthAdmin::handle (Http/Middleware/) — the SSO
    //     login
    // -----------------------------------------------------------

    public static function check_admin($memberarr)
    {
        $id = Admin::where(['vu_id'=>$memberarr['urn:oid:0.9.2342.19200300.100.1.1'][0]])->get()->pluck('id')->first();

        if($id>0){
            Session::put('main', Admin::select('main')->where(['id' => $id])->get()->pluck('main')->first());
        }

        return $id;
    }
}
