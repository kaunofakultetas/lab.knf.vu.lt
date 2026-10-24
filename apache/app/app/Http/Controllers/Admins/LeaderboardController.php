<?php

// -----------------------------------------------------------
//  [*] LeaderboardController — the control-panel dashboard
//
//  The page every admin lands on: the lab's counts —
//  players, players who ever signed in, players with a
//  solve, groups — the top ten of the leaderboard and the
//  fifteen latest solves. It keeps its own copies of the
//  player side's rank and time helpers.
//
//    GET /control-panel/dashboard — the dashboard
//
//  Used by:
//    - routes/web.php — the route above
// -----------------------------------------------------------


namespace App\Http\Controllers\Admins;

use App\Http\Controllers\Admins\BaseController;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Groups;
use App\Models\ChallengesSubmitted;
use App\Models\ChallengesHistory;








// -----------------------------------------------------------
// LeaderboardController
// -----------------------------------------------------------
//
//   __construct         — the base's layout data
//   dashboard           — the dashboard
//   ordinal             — a rank with its suffix
//   time_elapsed_string — a date as how long ago it was
//
// Used by:
//   - routes/web.php
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
    // dashboard
    // -----------------------------------------------------------
    //
    // GET /control-panel/dashboard
    //
    // The admins.dashboard view: the counts, the first ten
    // rows of ranking_table with ordinal ranks and how long
    // ago each last solve was, and the fifteen latest solves
    // with player, challenge and the challenge's points as
    // they stand now. Both times are counted from Unix
    // seconds, as the local times repeat an hour when the
    // clocks go back in October. The players with a solve and
    // the latest solves count only accounts and challenges
    // that still exist, so solves left behind by an account
    // deleted before its solves went with it count for no one.
    // The view never shows the count of team accounts it is
    // handed, and a standings list of every ranked player is
    // built too, and thrown away.
    //
    // Used by:
    //   - the Home link of the panel's sidebar, the end of the
    //     SSO login, and the guest middleware's redirect
    // -----------------------------------------------------------

    public function dashboard(Request $request)
    {
        // The ranking is refreshed by UpdateRankingTable on every
        // request, so this action no longer refreshes it itself
        $th = ChallengesHistory::selectRaw("cyber_challenges_history.*, cyber_users.username, cyber_challenges.name, cyber_challenges.points")
            ->join('cyber_users', 'cyber_users.id', 'cyber_challenges_history.user_id')
            ->join('cyber_challenges', 'cyber_challenges.id', 'cyber_challenges_history.challenge_id')
            ->orderBy('id', 'desc')
            ->take(15)
            ->get()
            ->toArray();

        if(!is_array($th)){ $th = array(); }

        $th = collect($th)->map(function($user) {
            $user['created'] = $this->time_elapsed_string('@' . $user['created_time']);
            return $user;
        });

        $ranking_table = json_decode(Redis::get('ranking_table') ,1);
        $rtt = json_decode(Redis::get('ranking_table') ,1);
        $ranking_table = array_slice($ranking_table, 0, 10, true);

        $arrr = [];

        foreach($rtt as $k => $v){
            $arrr['standings'][] = ['pos'=>$v['user_rank'], 'team'=>$v['username'], 'score'=>$v['points']];
        }

        $ordinalUsers = collect($ranking_table)->map(function($user) {
            $user['user_rank'] = $this->ordinal($user['user_rank']);
            return $user;
        });

        $last_times = ChallengesHistory::lastTimes();

        // Every answer books a history row; a solve without one
        // falls back to its local time
        $ordinalUsers = collect($ordinalUsers)->map(function($user) use ($last_times) {
            $user['last_challenge'] = $this->time_elapsed_string(isset($last_times[$user['user_id']]) ? '@' . $last_times[$user['user_id']] : $user['last_challenge']);
            return $user;
        });

        return view('admins.dashboard', [
            'registered_users'=>User::all()->count(),
            'registered_teams'=>User::where(['user_type'=>2])->count(),
            'loggedin_users'=>User::whereNotNull('last_login')->count(),
            'solvedone_users'=>ChallengesSubmitted::join('cyber_users', 'cyber_users.id', 'cyber_challenges_submitted.user_id')->distinct()->count('cyber_challenges_submitted.user_id'),
            'total_groups'=>Groups::all()->count(),
            'ranking_table'=>$ordinalUsers,
            'latest_solved'=>$th
        ]);
    }






    // -----------------------------------------------------------
    // ordinal
    // -----------------------------------------------------------
    //
    // A copy of Users\SettingsController::ordinal: the number
    // with its English suffix.
    //
    // Used by:
    //   - dashboard (above) — the top ten's ranks
    // -----------------------------------------------------------

    public static function ordinal($number)
    {
        $ends = array('th','st','nd','rd','th','th','th','th','th','th');
        if ((($number % 100) >= 11) && (($number%100) <= 13))
            return $number. 'th';
        else
            return $number. $ends[$number % 10];
    }






    // -----------------------------------------------------------
    // time_elapsed_string
    // -----------------------------------------------------------
    //
    // A copy of Users\SettingsController::time_elapsed_string:
    // how long ago a date was, in English.
    //
    // Used by:
    //   - dashboard (above) — the latest solves and the top
    //     ten's last solves
    // -----------------------------------------------------------

    public static function time_elapsed_string($datetime, $full = false)
    {
        $now = new \DateTime();
        $ago = new \DateTime($datetime);
        $diff = $now->diff($ago);

        // DateInterval has no weeks, so they are kept apart
        $weeks = floor($diff->d / 7);
        $diff->d -= $weeks * 7;

        $string = array(
            'y' => 'year',
            'm' => 'month',
            'w' => 'week',
            'd' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second'
        );
        foreach ($string as $k => &$v) {
            $count = $k == 'w' ? $weeks : $diff->$k;
            if ($count) {
                $v = $count . ' ' . $v . ($count > 1 ? 's' : '');
            } else {
                unset($string[$k]);
            }
        }

        if (! $full)
            $string = array_slice($string, 0, 1);
        return $string ? implode(', ', $string) . ' ago' : 'just now';
    }
}
