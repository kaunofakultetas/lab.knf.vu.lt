<?php

// -----------------------------------------------------------
//  [*] SettingsController — the lab's settings page
//
//  The main admins' settings page with its one form, the
//  registration switch — the cyber_settings row the
//  registration reads. It also holds the control panel's
//  copies of two helpers of Users\SettingsController, for
//  the group leaderboard.
//
//    GET  /control-panel/settings          — the page
//    POST /control-panel/settings/reg_open — the switch
//
//  Used by:
//    - routes/web.php — the routes above, all main-only
//      (perms)
//    - UsersGroupsController (this folder) — ordinal and
//      time_elapsed_string
// -----------------------------------------------------------


namespace App\Http\Controllers\Admins;

use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Admins\BaseController;
use Illuminate\Support\Facades\Hash;
use App\Models\Settings;
use App\Models\Admin;
use Illuminate\Http\Request;








// -----------------------------------------------------------
// SettingsController
// -----------------------------------------------------------
//
//   __construct         — the base's layout data
//   index               — the settings page
//   reg_open            — stores the switch
//   ordinal             — a rank with its suffix
//   time_elapsed_string — a date as how long ago it was
//
// Used by:
//   - routes/web.php and UsersGroupsController
// -----------------------------------------------------------

class SettingsController extends BaseController
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
    // GET /control-panel/settings
    //
    // The admins.settings view with the registration switch.
    //
    // Used by:
    //   - the Settings link of the panel's sidebar
    // -----------------------------------------------------------

    public function index()
    {
        return view('admins.settings', [
            'reg_open' => Settings::find(2)->val1
        ]);
    }






    // -----------------------------------------------------------
    // reg_open
    // -----------------------------------------------------------
    //
    // POST /control-panel/settings/reg_open
    //
    // Stores the registration switch as sent — '1' opens the
    // registration, anything else closes it — then goes back
    // with "Updated!".
    //
    // Used by:
    //   - the registration form of admins/settings.blade.php
    // -----------------------------------------------------------

    public function reg_open(Request $req)
    {
        Settings::where(['id' => 2])->update(['val1'=>$req->input('reg_open')]);

        session()->flash('success', 'Updated!');

        return back();
    }






    // -----------------------------------------------------------
    // ordinal
    // -----------------------------------------------------------
    //
    // A copy of Users\SettingsController::ordinal: the number
    // with its English suffix.
    //
    // Used by:
    //   - UsersGroupsController::group_leaderboard — the ranks
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
    //   - UsersGroupsController::group_leaderboard — the last
    //     solves
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
