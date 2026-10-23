<?php

// -----------------------------------------------------------
//  [*] SettingsController — the player side's helpers
//
//  A controller in name only: no route reaches it, and its
//  four static helpers serve the player side — the
//  visitor's address, a date told as how long ago it was,
//  the refresh of the cached leaderboard, and English
//  ordinals for ranks. The control panel keeps its own
//  copies (Admins\SettingsController,
//  Admins\LeaderboardController).
//
//  Used by:
//    - the player controllers of this folder
//    - UpdateRankingTable (Http/Middleware/)
// -----------------------------------------------------------


namespace App\Http\Controllers\Users;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;








// -----------------------------------------------------------
// SettingsController
// -----------------------------------------------------------
//
//   getUserIP           — the visitor's address
//   time_elapsed_string — a date as how long ago it was
//   update_rank_table   — refreshes the cached leaderboard
//   ordinal             — a rank with its suffix
//
// Used by:
//   - the player controllers and UpdateRankingTable
// -----------------------------------------------------------

class SettingsController extends BaseController
{






    // -----------------------------------------------------------
    // getUserIP
    // -----------------------------------------------------------
    //
    // The visitor's address as the app records it with logins
    // and solves: Cloudflare's CF-Connecting-IP when present —
    // copied over REMOTE_ADDR and HTTP_CLIENT_IP in $_SERVER
    // for the rest of the request — then a Client-IP header,
    // then X-Forwarded-For when it holds exactly one valid
    // address, else REMOTE_ADDR.
    //
    // Known bug (low): the proxies in front pass Client-IP and
    // CF-Connecting-IP on as the visitor sent them
    // (SettingsController.php:80), so a visitor picks the
    // address stored as last_ip and with each solve. Fix: take
    // request()->ip(), which TrustProxies already resolves from
    // the endpoint's X-Forwarded-For.
    //
    // Used by:
    //   - UserAuthController::login and check_member — last_ip
    //   - ChallengesController::submit_answer — the solve's ip
    // -----------------------------------------------------------

    public static function getUserIP()
    {
        if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
            $_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
            $_SERVER['HTTP_CLIENT_IP'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
        }
        $client = @$_SERVER['HTTP_CLIENT_IP'];
        $forward = @$_SERVER['HTTP_X_FORWARDED_FOR'];
        $remote = $_SERVER['REMOTE_ADDR'];

        if (filter_var($client, FILTER_VALIDATE_IP)) {
            $ip = $client;
        } elseif (filter_var($forward, FILTER_VALIDATE_IP)) {
            $ip = $forward;
        } else {
            $ip = $remote;
        }

        return $ip;
    }






    // -----------------------------------------------------------
    // time_elapsed_string
    // -----------------------------------------------------------
    //
    // How long ago a date was, in English: the largest unit
    // that is not zero — years down to seconds, weeks included
    // — with an s for more than one and " ago" after; every
    // such unit, comma-separated, when full is set; "just now"
    // when there is none. The difference carries no sign, so
    // a date in the future reads the same.
    //
    // Used by:
    //   - BaseController — the feed of recent solves
    //   - LeaderboardController::index and GroupsController —
    //     the last solve of each player
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






    // -----------------------------------------------------------
    // update_rank_table
    // -----------------------------------------------------------
    //
    // Copies the cyber_users_ranks view into Redis as
    // ranking_table: JSON of every ranked player, keyed by
    // user id.
    //
    // Used by:
    //   - UpdateRankingTable::handle (Http/Middleware/) —
    //     every request
    // -----------------------------------------------------------

    public static function update_rank_table()
    {
        $arr = DB::table('cyber_users_ranks')->get()->keyBy('user_id')->toArray();

        Redis::set('ranking_table', json_encode($arr));
    }






    // -----------------------------------------------------------
    // ordinal
    // -----------------------------------------------------------
    //
    // The number with its English suffix: st, nd or rd after a
    // last digit of 1, 2 or 3, th after any other and after 11
    // to 13.
    //
    // Used by:
    //   - BaseController — the player's rank
    //   - LeaderboardController::index and GroupsController —
    //     the ranks of the leaderboards
    // -----------------------------------------------------------

    public static function ordinal($number)
    {
        $ends = array('th','st','nd','rd','th','th','th','th','th','th');
        if ((($number % 100) >= 11) && (($number%100) <= 13))
            return $number. 'th';
        else
            return $number. $ends[$number % 10];
    }
}
