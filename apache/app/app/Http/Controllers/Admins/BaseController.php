<?php

// -----------------------------------------------------------
//  [*] BaseController — the base of the control-panel pages
//
//  Every control-panel controller but the login one extends
//  it for the data the panel's layout needs: the signed-in
//  admin's VU id and main flag, read fresh from the account
//  before each action — the menu and lists show main-only
//  parts by it — and the page number of its paged lists.
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
use Illuminate\Pagination\Paginator;








// -----------------------------------------------------------
// BaseController
// -----------------------------------------------------------
//
//   __construct — registers the sharing as a middleware of
//                 the controller
//   listPage    — the page a paged list opens at
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






    // -----------------------------------------------------------
    // listPage
    // -----------------------------------------------------------
    //
    // The page of a paged list the address asks for, as
    // Laravel's paginator reads its page parameter: a whole
    // number from 1 up, and the first page for anything else —
    // an exponent, a fraction, a negative, a word, or a number
    // past what an integer holds, which would break the slice
    // of the list. It is capped at a million pages, far beyond
    // any list here, so the slice's offset stays an integer.
    //
    // Known bug LAB-66 (low): a page past the end of a list is
    // handed out as it is, and the lists show "No …" without
    // page links for an empty page, as after deleting the last
    // row of the last page. Fix: give the last page instead.
    //
    // Used by:
    //   - the paged lists of ChallengesController,
    //     EmailsController, PagesController, UsersController
    //     and UsersGroupsController
    // -----------------------------------------------------------

    protected function listPage(): int
    {
        return min(Paginator::resolveCurrentPage(), 1000000);
    }
}
