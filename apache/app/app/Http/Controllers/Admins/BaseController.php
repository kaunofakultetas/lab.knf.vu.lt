<?php

// -----------------------------------------------------------
//  [*] BaseController — the base of the control-panel pages
//
//  Every control-panel controller but the login one extends
//  it for the data the panel's layout needs: the signed-in
//  admin's VU id and main flag, read fresh from the account
//  before each action — the menu and lists show main-only
//  parts by it — the page number of its paged lists, and
//  whether the admin may change a group or a challenge.
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
use App\Models\Challenges;
use App\Models\Groups;
use View;
use Illuminate\Pagination\Paginator;








// -----------------------------------------------------------
// BaseController
// -----------------------------------------------------------
//
//   __construct        — registers the sharing as a
//                        middleware of the controller
//   listPage           — the page a paged list opens at
//   containing         — a LIKE pattern for a search
//   mayChangeGroup     — whether the admin may change a group
//   mayChangeChallenge — whether the admin may change a
//                        challenge
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
    // The page of a paged list of the given number of rows,
    // 50 to a page by default, that the address asks for, as
    // Laravel's paginator reads its page parameter: a whole
    // number from 1 up, and the first page for anything else —
    // an exponent, a fraction, a negative, a word, or a number
    // past what an integer holds, which would break the slice
    // of the list. A page past the end gives the last page:
    // deleting the last row of the last page goes back to
    // that page, and an empty page would read "No …" without
    // links to the rest of the list.
    //
    // Used by:
    //   - the paged lists of ChallengesController,
    //     EmailsController, PagesController, UsersController
    //     and UsersGroupsController
    // -----------------------------------------------------------

    protected function listPage(int $rows, int $perPage = 50): int
    {
        return max(1, min(Paginator::resolveCurrentPage(), (int) ceil($rows / $perPage)));
    }






    // -----------------------------------------------------------
    // containing
    // -----------------------------------------------------------
    //
    // The LIKE pattern that finds the words searched for
    // anywhere in a column, as typed: the backslash, % and _
    // in them are escaped, as LIKE would read % as any text
    // and _ as any one character — a search for john_doe
    // would list johnxdoe too, and one for % everything.
    // MySQL's LIKE escapes with a backslash by default.
    //
    // Used by:
    //   - the searches of ChallengesController::index and
    //     UsersController::index
    // -----------------------------------------------------------

    protected function containing(string $words): string
    {
        return '%' . addcslashes($words, '\\%_') . '%';
    }






    // -----------------------------------------------------------
    // mayChangeGroup
    // -----------------------------------------------------------
    //
    // Whether the signed-in admin may change the group with
    // the id: a main admin may change every group, a lab admin
    // the groups they created — the rule of R_Exists, read
    // from the account. The row actions R_Exists lets through
    // for a row that does not exist ask it again of the row
    // they find, as another admin may have created that row
    // in the meantime.
    //
    // Used by:
    //   - UsersGroupsController — group_destroy and
    //     challenge_destroy
    // -----------------------------------------------------------

    protected function mayChangeGroup($group_id): bool
    {
        return Auth::guard('admins')->user()?->main == 1
            || Groups::where(['id' => $group_id, 'created_by' => Auth::guard('admins')->id()])->exists();
    }






    // -----------------------------------------------------------
    // mayChangeChallenge
    // -----------------------------------------------------------
    //
    // The same for a challenge: every challenge for a main
    // admin, the challenges they created for a lab admin.
    //
    // Used by:
    //   - ChallengesController — file_form, file_update and
    //     file_destroy
    // -----------------------------------------------------------

    protected function mayChangeChallenge($challenge_id): bool
    {
        return Auth::guard('admins')->user()?->main == 1
            || Challenges::where(['id' => $challenge_id, 'created_by' => Auth::guard('admins')->id()])->exists();
    }
}
