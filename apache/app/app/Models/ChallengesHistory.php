<?php

// -----------------------------------------------------------
//  [*] ChallengesHistory — the feed of recent solves
//
//  One cyber_challenges_history row per solve, stamped
//  twice: created_time in Unix seconds and created as a
//  date. submit_answer also hands it a content line, which
//  is neither fillable nor a column, so it is dropped. The
//  latest six rows of challenges every player may see, with
//  the player's username and the challenge's name, are the
//  player sidebar's feed (feed), read on every player page.
//
//  Used by:
//    - Users\ChallengesController::submit_answer — writes a
//      row
//    - Users\BaseController — the sidebar's feed
//    - Admins\LeaderboardController::dashboard — the latest
//      solves of the control panel
//    - Admins\ChallengesController::destroy — deletes a
//      challenge's rows
//    - Admins\UsersController::destroy — deletes an
//      account's rows
// -----------------------------------------------------------


namespace App\Models;

use Illuminate\Database\Eloquent\Model;








// -----------------------------------------------------------
// ChallengesHistory
// -----------------------------------------------------------
//
// Columns: created_time, created, user_id and challenge_id.
//
//   feed — the sidebar's six latest solves
//
// Used by:
//   - Users\ChallengesController::submit_answer and
//     Users\BaseController
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
    // feed
    // -----------------------------------------------------------
    //
    // The sidebar's feed: the six latest solves, newest first,
    // each row with the player's username and the challenge's
    // name. Only solves of challenges on the main dashboard
    // (hidden 0) by accounts that still exist are listed, so
    // a hidden challenge's name never reaches players outside
    // its groups, and no row is left without its player or
    // its challenge. It is read from the database on every
    // player page, so it always shows the solves and names as
    // they are: a copy kept in Redis was empty after Redis
    // restarted, and of two solves at once, the rebuild that
    // read first could write last and drop the other.
    //
    // Used by:
    //   - Users\BaseController — the player layout's sidebar
    // -----------------------------------------------------------

    public static function feed(): array
    {
        return self::selectRaw("cyber_challenges_history.*, cyber_users.username, cyber_challenges.name")
            ->join('cyber_users', 'cyber_users.id', 'cyber_challenges_history.user_id')
            ->join('cyber_challenges', 'cyber_challenges.id', 'cyber_challenges_history.challenge_id')
            ->where('cyber_challenges.hidden', 0)
            ->orderBy('cyber_challenges_history.id', 'desc')
            ->take(6)
            ->get()
            ->toArray();
    }
}
