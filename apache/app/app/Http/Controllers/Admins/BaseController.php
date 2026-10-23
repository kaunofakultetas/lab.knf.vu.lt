<?php

// -----------------------------------------------------------
//  [*] BaseController — the base of the control-panel pages
//
//  Every control-panel controller but the login one extends
//  it for the data the panel's layout needs: the signed-in
//  admin's VU id and main flag, read fresh from the account
//  before each action — the menu and lists show main-only
//  parts by it.
//
//  Used by:
//    - CategoriesController, ChallengesController,
//      EmailsController, LeaderboardController,
//      PagesController, PartnersController,
//      SettingsController, UsersController and
//      UsersGroupsController (this folder)
// -----------------------------------------------------------


namespace App\Http\Controllers\Admins;

use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Settings;
use App\Models\ChallengesHistory;
use App\Models\Admin;
use View;








// -----------------------------------------------------------
// BaseController
// -----------------------------------------------------------
//
// One method, the constructor, which registers the sharing
// as a middleware of the controller.
//
// Used by:
//   - the control-panel controllers of this folder
// -----------------------------------------------------------

class BaseController extends Controller
{






    // -----------------------------------------------------------
    // __construct
    // -----------------------------------------------------------
    //
    // Registers a controller middleware that shares vu_id and
    // main with the views before every action. The views and
    // the perms and r_exists checks all read the account's
    // flag, so a demotion takes effect at once everywhere.
    //
    // Used by:
    //   - Laravel, when it builds a control-panel controller
    //     for a request
    // -----------------------------------------------------------

    public function __construct()
    {
        // Signing in is required by the routes, not here
        $this->middleware(function ($request, $next) {
            $vu_id = Admin::where(['id'=>Auth::guard('admins')->id()])->get()->pluck('vu_id')->first();
            $main = Admin::where(['id'=>Auth::guard('admins')->id()])->get()->pluck('main')->first();

            // The username is no longer shared
            View::share('vu_id', $vu_id);
            View::share('main', $main);

            return $next($request);
        });
    }
}
