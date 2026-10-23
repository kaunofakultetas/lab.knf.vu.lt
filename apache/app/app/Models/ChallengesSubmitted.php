<?php

// -----------------------------------------------------------
//  [*] ChallengesSubmitted — the solves
//
//  One cyber_challenges_submitted row per challenge a player
//  solved: the points it gave, when and from which address.
//  Only right answers are stored, so a row means "solved";
//  the table's unique key on user_id and challenge_id keeps
//  a second solve of the same challenge out, even when both
//  arrive at once. The ranking views read the rows too
//  (mysql/init.sql).
//
//  Used by:
//    - Users\ChallengesController::submit_answer — books a
//      solve
//    - ValidateChallengeSolved (Http/Middleware/) and the
//      dashboards — what is solved already
//    - Groups::groupPointsPercent (this folder)
//    - Admins\LeaderboardController::dashboard and
//      Admins\ChallengesController::destroy
//    - ApiController, which no route reaches
// -----------------------------------------------------------


namespace App\Models;

use Illuminate\Database\Eloquent\Model;








// -----------------------------------------------------------
// ChallengesSubmitted
// -----------------------------------------------------------
//
// Columns: user_id, challenge_id, points_given, created and
// ip.
//
// Used by:
//   - Users\ChallengesController::submit_answer — books a
//     solve
//   - every page that shows what a player solved
// -----------------------------------------------------------

class ChallengesSubmitted extends Model
{

    // The table, which has no created_at / updated_at
    // columns; rememberTokenName belongs to the account
    // models, a plain model never reads it
    protected $table = "cyber_challenges_submitted";
    public $timestamps = false;
    protected $rememberTokenName = false;

    // The columns of a solve
    protected $fillable = [
        'user_id', 'challenge_id', 'points_given', 'created', 'ip'
    ];

    // Empty: toArray hands out every column
    protected $hidden = [
    ];

    // The column types; task_id is a column the table does
    // not have, so its cast never applies
    protected $casts = [
        'challenge_id'=>'integer',
        'task_id'=>'integer',
        'points_given'=>'integer',
        'created'=>'datetime',
        'ip'=>'string'
    ];
}
