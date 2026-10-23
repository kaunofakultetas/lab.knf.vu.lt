<?php

// -----------------------------------------------------------
//  [*] AdminAuthController — the control-panel logins
//
//  The control panel is entered through the VU single
//  sign-on only: its login page offers the SSO button, the
//  saml_auth_admin middleware signs the admin in through
//  check_admin, and the sign-out ends the SSO session too.
//  The password login is still here but no route reaches
//  it.
//
//    GET /control-panel        — the login page
//    GET /control-panel/login  — the end of the SSO login
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
//   login       — dead: the password login
//   logout      — sign-out, from the SSO too
//   cp_login    — on to the dashboard after the SSO login
//   check_admin — the admin account of a VU user
//
// Used by:
//   - routes/web.php and SamlAuthAdmin
// -----------------------------------------------------------

class AdminAuthController extends Controller
{

    // Laravel's landing page after a login; nothing reads it
    protected $redirectTo = '/control-panel/dashboard';






    // -----------------------------------------------------------
    // index
    // -----------------------------------------------------------
    //
    // GET /control-panel
    //
    // The admins.login view, whose only control is the
    // "Login with VU SSO" button. Signed-in visitors never see
    // it — the guest middleware sends them to a dashboard.
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
    // login
    // -----------------------------------------------------------
    //
    // Dead code: the password login of the admins guard, by
    // e-mail and a password of 6 or more. Its route and its
    // form are switched off.
    //
    // Used by:
    //   - nothing at the moment
    // -----------------------------------------------------------

    public function login(Request $request)
    {
        $this->validate($request, [
            'email'   => 'required|email',
            'password' => 'required|min:6'
        ]);

        $credentials = $request->only('email', 'password');

        if (Auth::guard('admins')->attempt($credentials)) {
            return redirect()->intended('/control-panel/dashboard');
        } else {
            return back();
        }
    }






    // -----------------------------------------------------------
    // logout
    // -----------------------------------------------------------
    //
    // POST /control-panel/logout
    //
    // Signs the admin out of the panel, then sends the browser
    // to the SSO's logout, which returns to the control-panel
    // login page. The session — and its main flag — stays.
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
        $as = new \SimpleSAML\Auth\Simple('default-sp');

        return redirect($as->getLogoutURL(env('APP_URL').'/control-panel'));
    }






    // -----------------------------------------------------------
    // cp_login
    // -----------------------------------------------------------
    //
    // GET /control-panel/login
    //
    // The end of the SSO login: the saml_auth_admin middleware
    // has signed the admin in by now, so this only goes on to
    // the dashboard, whose auth.admin check sends a VU user
    // without an admin account back to the login page.
    //
    // Used by:
    //   - the "Login with VU SSO" button of admins/login.blade.php
    // -----------------------------------------------------------

    public function cp_login(Request $request)
    {
        return redirect('/control-panel/dashboard');
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
