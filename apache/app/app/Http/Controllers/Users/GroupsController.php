<?php

// -----------------------------------------------------------
//  [*] GroupsController — a study group, as players see it
//
//  The pages of a group the player belongs to — the
//  group.exists middleware checks the membership: the
//  group's challenges with the player's progress, and the
//  group's leaderboard.
//
//    GET /user/group/{public_id}             — the group's page
//    GET /user/group/leaderboard/{public_id} — its leaderboard
//
//  Used by:
//    - routes/web.php — the routes above
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
use App\Models\Challenges;
use App\Models\ChallengesHistory;
use App\Models\ChallengesFiles;
use App\Models\ChallengesCategories;
use App\Models\ChallengesSubmitted;
use App\Models\User;
use App\Models\Groups;
use App\Models\GroupsChallenges;
use App\Models\GroupsUsers;








// -----------------------------------------------------------
// GroupsController
// -----------------------------------------------------------
//
//   __construct           — the base's layout data
//   index                 — the group's page
//   group_leaderboard     — the group's leaderboard
//   get_group_leaderboard — its query
//
// Used by:
//   - routes/web.php
// -----------------------------------------------------------

class GroupsController extends BaseController
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
    // GET /user/group/{public_id}
    //
    // The users.dashboard_group view: the group's challenges,
    // hidden ones included, unsolved first and by points, with
    // whether the player solved each and its category names;
    // and the player's share of the group's points. The
    // sorting helper is declared inside the action, as in
    // ChallengesController::index, with the same limit of one
    // run per PHP process.
    //
    // Used by:
    //   - the group links of the player sidebar
    //     (users/partials/sidebar.blade.php), and the redirect
    //     after a solve opened from the group
    // -----------------------------------------------------------

    public function index(Request $request, $public_id)
    {
        $group_id = Groups::where(['public_id'=>$public_id])->get()->pluck('id')->first();
        $challenges_in_group = GroupsChallenges::where(["group_id"=>$group_id])->get()->pluck('challenge_id')->toArray();

        if (count(Challenges::wherein("id", $challenges_in_group)->orderby('points')->get()->toArray()) > 0) {
            $arr = Challenges::wherein("id", $challenges_in_group)->orderby('points')->get()->toArray();

            foreach ($arr as $k => $v) {
                $arr[$k]['public_id'] = $v['public_id'];

                $arr[$k]['solved'] = ChallengesSubmitted::where('user_id', '=', Auth::guard('users')->id())->where('challenge_id', '=', $v['id'])->count();

                $arr[$k]['categories'] = implode(", ", ChallengesCategories::
                    where(['challenge_id' => $arr[$k]['id']])
                    ->leftJoin('cyber_categories', 'cyber_categories.id', '=', 'cyber_challenges_categories.cat_id')
                    ->get()->pluck('name')->toArray());
            }
        } else {
            $arr = array();
        }






        // -----------------------------------------------------------
        // aasort
        // -----------------------------------------------------------
        //
        // The sorting helper of ChallengesController::index, a
        // copy declared under the same global name; the two
        // never run in one request.
        //
        // Used by:
        //   - index (around it) — unsolved challenges first
        // -----------------------------------------------------------

        function aasort(&$array, $key)
        {
            $sorter = array();
            $ret = array();
            reset($array);
            foreach ($array as $ii => $va) {
                $sorter[$ii] = $va[$key];
            }
            asort($sorter);
            foreach ($sorter as $ii => $va) {
                $ret[$ii] = $array[$ii];
            }
            $array = $ret;
        }

        aasort($arr, "solved");

        return view('users.dashboard_group', [
            'recordlist' => $arr,
            'solved_percent'=>Groups::groupPointsPercent($group_id),
            'public_id'=>$public_id
        ]);
    }






    // -----------------------------------------------------------
    // group_leaderboard
    // -----------------------------------------------------------
    //
    // GET /user/group/leaderboard/{public_id}
    //
    // The users.groups_leaderboard view: the ranking of
    // get_group_leaderboard with ordinal positions and how
    // long ago each last solve was, under the group's name.
    //
    // Used by:
    //   - the leaderboard links of a group's page and of the
    //     leaderboards
    // -----------------------------------------------------------

    public function group_leaderboard(Request $request, $public_id)
    {
        $group_id = Groups::select("id")->where(['public_id'=>$public_id])->get()->pluck('id')->first();

        $challenges = GroupsChallenges::select("challenge_id")->where(["group_id"=>$group_id])->get()->pluck("challenge_id")->toArray();

        $ranking_table = json_decode(json_encode(GroupsController::get_group_leaderboard($challenges)), 1);

        $ordinalUsers = collect($ranking_table)->map(function($user) {
            $user['position'] = SettingsController::ordinal($user['position']);
            return $user;
        });

        $ordinalUsers = collect($ordinalUsers)->map(function($user) {
            $user['last_challenge'] = SettingsController::time_elapsed_string($user['last_challenge']);
            return $user;
        });

        return view('users.groups_leaderboard', [
            'ranking_table'=>$ordinalUsers,
            'group_name'=>Groups::select("name")->where(['public_id'=>$public_id])->get()->pluck('name')->first(),
            'group_id'=>$group_id
        ]);
    }






    // -----------------------------------------------------------
    // get_group_leaderboard
    // -----------------------------------------------------------
    //
    // Every player with a solve of one of the given challenges
    // — members or not — ranked by their lab-wide points, not
    // by the group's. The last challenge is the player's
    // latest solve of them, which also orders players with
    // the same points, earliest first.
    //
    // Used by:
    //   - group_leaderboard (above)
    // -----------------------------------------------------------

    public static function get_group_leaderboard($challenges)
    {
        $data = DB::table('cyber_users')
            ->leftJoin('cyber_challenges_submitted', 'cyber_challenges_submitted.user_id', '=', 'cyber_users.id')
            ->whereIn('cyber_challenges_submitted.challenge_id', $challenges)
            ->groupBy('cyber_users.id')
            ->orderByDesc('cyber_users.points')
            ->orderBy('last_challenge')
            ->selectRaw('row_number() OVER (ORDER BY `cyber_users`.`points` DESC) AS `position`, `cyber_users`.`id` AS `id`, `cyber_users`.`points` AS `points`, `cyber_users`.`email` AS `email`, `cyber_users`.`username` AS `username`, `cyber_users`.`user_type` AS `user_type`, MAX(`cyber_challenges_submitted`.`created`) AS `last_challenge`')
            ->get()->toArray();

        return $data;
    }
}
