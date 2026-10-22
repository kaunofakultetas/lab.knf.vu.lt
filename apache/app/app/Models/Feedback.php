<?php

// -----------------------------------------------------------
//  [*] Feedback — the players' feedback messages
//
//  One cyber_feedback row per message a player sends from
//  the feedback dialog: the player's id, the text and when
//  it was sent. Nothing in the app reads the rows back;
//  they are read in the database.
//
//  Used by:
//    - Users\ChallengesController::feedback_save
// -----------------------------------------------------------


namespace App\Models;

use Illuminate\Database\Eloquent\Model;








// -----------------------------------------------------------
// Feedback
// -----------------------------------------------------------
//
// Columns: user_id, feedback and created, which the app
// fills itself.
//
// Used by:
//   - Users\ChallengesController::feedback_save — stores a
//     message
// -----------------------------------------------------------

class Feedback extends Model
{

    // The table, which has no created_at / updated_at
    // columns; rememberTokenName belongs to the account
    // models, a plain model never reads it
    protected $table = "cyber_feedback";
    public $timestamps = false;
    protected $rememberTokenName = false;

    // The columns feedback_save fills
    protected $fillable = [
        'user_id',
        'feedback',
        'created'
    ];
}
