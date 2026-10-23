<?php

// -----------------------------------------------------------
//  [*] ChallengesHistory — the feed of recent solves
//
//  One cyber_challenges_history row per solve, stamped
//  twice: created_time in Unix seconds and created as a
//  date. submit_answer also hands it a content line, which
//  is neither fillable nor a column, so it is dropped. The
//  latest six rows of challenges every player may see, with
//  the player's username and the challenge's name, are kept
//  in Redis as tasks_history for the player sidebar's feed
//  (refreshFeed).
//
//  Used by:
//    - Users\ChallengesController::submit_answer — writes a
//      row and refreshes tasks_history
//    - Admins\LeaderboardController::dashboard — the latest
//      solves of the control panel
//    - Admins\ChallengesController — update, status and
//      destroy refresh tasks_history, destroy deletes rows
//    - Admins\UsersController::destroy — deletes an
//      account's rows and refreshes tasks_history
// -----------------------------------------------------------


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Redis;








// -----------------------------------------------------------
// ChallengesHistory
// -----------------------------------------------------------
//
// Columns: created_time, created, user_id and challenge_id.
//
//   refreshFeed — rebuilds tasks_history
//
// Used by:
//   - Users\ChallengesController::submit_answer
//   - Admins\LeaderboardController, Admins\ChallengesController
//     and Admins\UsersController
// -----------------------------------------------------------

class ChallengesHistory extends Model
{

    // The table, which has no created_at / updated_at
    // columns; rememberTokenName belongs to the account
    // models, a plain model never reads it
    protected $table = "cyber_challenges_history";
    public $timestamps = false;
    protected $rememberTokenName = false;

    // The columns submit_answer fills
    protected $fillable = [
        'created_time',
        'created',
        'user_id',
        'challenge_id'
    ];

    // Empty: toArray hands out every column
    protected $hidden = [];

    // The column types; created_time is Unix seconds
    protected $casts = [
        'user_id'=>'integer',
        'challenge_id'=>'integer',
        'created_time' => 'integer',
        'created' => 'datetime'
    ];






    // -----------------------------------------------------------
    // refreshFeed
    // -----------------------------------------------------------
    //
    // Rebuilds tasks_history, the sidebar's feed: the six
    // latest solves, newest first, each row with the player's
    // username and the challenge's name. Only solves of
    // challenges on the main dashboard (hidden 0) by accounts
    // that still exist are listed, so a hidden challenge's
    // name never reaches players outside its groups, and no
    // row is left without its player or its challenge. It
    // runs after every change the feed shows: a solve, and a
    // challenge renamed, hidden, shown or deleted, or an
    // account deleted.
    //
    // Used by:
    //   - Users\ChallengesController::submit_answer
    //   - Admins\ChallengesController — update, status and
    //     destroy
    //   - Admins\UsersController::destroy
    // -----------------------------------------------------------

    public static function refreshFeed(): void
    {
        Redis::set('tasks_history', json_encode(
            self::selectRaw("cyber_challenges_history.*, cyber_users.username, cyber_challenges.name")
            ->join('cyber_users', 'cyber_users.id', 'cyber_challenges_history.user_id')
            ->join('cyber_challenges', 'cyber_challenges.id', 'cyber_challenges_history.challenge_id')
            ->where('cyber_challenges.hidden', 0)
            ->orderBy('cyber_challenges_history.id', 'desc')
            ->take(6)
            ->get()
            ->toArray()));
    }
}
