<?php

// -----------------------------------------------------------
//  [*] BaseController — the base of the player pages
//
//  Every player controller extends it for one thing: the
//  data of the player layout's sidebar and top bar, shared
//  with the views before each action runs — the player's
//  rank, points and username from the cached leaderboard,
//  the player's groups and the feed of recent solves.
//  Guests go through it too: the login page and the content
//  pages extend it.
//
//  Used by:
//    - UserAuthController, ChallengesController,
//      LeaderboardController, GroupsController,
//      PageController and SettingsController (this folder)
// -----------------------------------------------------------


namespace App\Http\Controllers\Users;

use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\ChallengesHistory;
use App\Models\User;
use App\Models\Pages;
use App\Models\Groups;
use App\Models\GroupsChallenges;
use App\Models\GroupsUsers;
use View;








// -----------------------------------------------------------
// BaseController
// -----------------------------------------------------------
//
// One method, the constructor, which registers the sharing
// as a middleware of the controller.
//
// Used by:
//   - the player controllers of this folder
// -----------------------------------------------------------

class BaseController extends Controller
{






    // -----------------------------------------------------------
    // __construct
    // -----------------------------------------------------------
    //
    // Registers a controller middleware that shares the
    // layout's data before every action. Rank and points come
    // from ranking_table, the Redis copy UpdateRankingTable
    // refreshes on every request; a player without a solve has
    // neither, and then no username either. The groups are the
    // player's memberships by VU id, the feed is tasks_history
    // — the six latest solves — with how long ago each was.
    //
    // Used by:
    //   - Laravel, when it builds a player controller for a
    //     request
    // -----------------------------------------------------------

    public function __construct()
    {
        // Signing in is not required here: the routes ask for it
        // where they need it, as the login page extends this too
        $this->middleware(function ($request, $next) {
            // STEP 1: the player's rank, points and username, from
            // the cached leaderboard
            // ====================================================
            $ranking_table = json_decode(Redis::get('ranking_table') ,1);

            if(isset($ranking_table[Auth::guard('users')->id()]['user_rank'])){
                $rank = $ranking_table[Auth::guard('users')->id()]['user_rank'];

                $my_position = SettingsController::ordinal($rank);
                $my_points = $ranking_table[Auth::guard('users')->id()]['points'];
                $username = User::find(Auth::guard('users')->id())->username;
            } else {
                $my_position = NULL;
                $my_points = NULL;
                $username = NULL;
            }


            // STEP 2: the player's groups, by VU id
            // =====================================
            $vu_id = User::where(['id'=>Auth::guard('users')->id()])->get()->pluck('vu_id')->first();

            $group_list = GroupsUsers::
                selectraw("cyber_users_groups.id as id, cyber_users_groups.group_id as group_id, cyber_groups.name, cyber_groups.public_id")->
                leftJoin("cyber_groups", "cyber_groups.id", "cyber_users_groups.group_id")->
                where(['cyber_users_groups.vu_id'=>$vu_id])->
                groupBy("cyber_users_groups.group_id")->
                get()->toArray();


            // STEP 3: the feed of recent solves, each with how long
            // ago it was
            // =====================================================
            $task_history = json_decode(Redis::get('tasks_history'), 1);

            if(!is_array($task_history)){ $task_history = array(); }

            if(count($task_history)>0){
                foreach ($task_history as $kk => $vv) {
                    $task_history[$kk]['elapsed'] = SettingsController::time_elapsed_string($vv['created']);
                }
            }


            // STEP 4: everything shared
            // =========================
            View::share('my_position', $my_position);
            View::share('history_list', $task_history);
            View::share('group_list', $group_list);
            View::share('points', $my_points);
            View::share('username', $username);
            View::share('vu_id', $vu_id);

            return $next($request);
        });
    }
}
