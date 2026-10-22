<?php

// -----------------------------------------------------------
//  [*] LeaderboardController — the player leaderboard
//
//  The lab-wide ranking, read from the Redis copy of the
//  cyber_users_ranks view: every player with a solve, by
//  points, with an ordinal rank and how long ago the
//  player's last challenge was — which is not always the
//  latest solve (LAB-01, in the view).
//
//  Used by:
//    - routes/web.php — GET /user/leaderboard
// -----------------------------------------------------------


namespace App\Http\Controllers\Users;

use App\Http\Controllers\Users\BaseController;
use App\Http\Controllers\Users\SettingsController;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Partners;
use App\Models\Categories;
use App\Models\Challenges;
use App\Models\ChallengesHistory;
use App\Models\ChallengesFiles;
use App\Models\ChallengesCategories;
use App\Models\ChallengesSubmitted;








// -----------------------------------------------------------
// LeaderboardController
// -----------------------------------------------------------
//
//   __construct — the base's layout data
//   index       — the leaderboard page
//
// Used by:
//   - routes/web.php — GET /user/leaderboard
// -----------------------------------------------------------

class LeaderboardController extends BaseController
{






    // -----------------------------------------------------------
    // __construct
    // -----------------------------------------------------------
    //
    // Only runs the base constructor, which shares the
    // layout's data.
    //
    // Used by:
    //   - Laravel, when it builds the controller for a request
    // -----------------------------------------------------------

    public function __construct()
    {
        parent::__construct();
    }






    // -----------------------------------------------------------
    // index
    // -----------------------------------------------------------
    //
    // GET /user/leaderboard
    //
    // The users.leaderboard view with ranking_table, its ranks
    // turned into ordinals and the last solves into how long
    // ago they were.
    //
    // Used by:
    //   - the Leaderboard links of the player top bar
    //     (users/partials/topnav.blade.php) and of the group
    //     leaderboard
    // -----------------------------------------------------------

    public function index(Request $request)
    {
        $ranking_table = json_decode(Redis::get('ranking_table') ,1);

        $ordinalUsers = collect($ranking_table)->map(function($user) {
            $user['user_rank'] = SettingsController::ordinal($user['user_rank']);
            return $user;
        });

        $ordinalUsers = collect($ordinalUsers)->map(function($user) {
            $user['last_challenge'] = SettingsController::time_elapsed_string($user['last_challenge']);
            return $user;
        });

        return view('users.leaderboard', ['ranking_table'=>$ordinalUsers]);
    }
}
