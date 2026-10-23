<?php

// -----------------------------------------------------------
//  [*] ChallengesHistory — the feed of recent solves
//
//  One cyber_challenges_history row per solve, stamped
//  twice: created_time in Unix seconds and created as a
//  date. submit_answer also hands it a content line, which
//  is neither fillable nor a column, so it is dropped. The
//  latest six rows, with the player's username and the
//  challenge's name, are kept in Redis as tasks_history for
//  the player sidebar's feed.
//
//  Used by:
//    - Users\ChallengesController::submit_answer — writes a
//      row and refreshes tasks_history
//    - Admins\LeaderboardController::dashboard — the latest
//      solves of the control panel
//    - Admins\ChallengesController::destroy
// -----------------------------------------------------------


namespace App\Models;

use Illuminate\Database\Eloquent\Model;








// -----------------------------------------------------------
// ChallengesHistory
// -----------------------------------------------------------
//
// Columns: created_time, created, user_id and challenge_id.
//
// Used by:
//   - Users\ChallengesController::submit_answer
//   - Admins\LeaderboardController and
//     Admins\ChallengesController
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
}
