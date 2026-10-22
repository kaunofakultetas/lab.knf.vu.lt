<?php

// -----------------------------------------------------------
//  [*] Challenges — the lab's challenges
//
//  One cyber_challenges row per challenge: public_id, the
//  random id its addresses use; the partner behind it; the
//  admin who created it (created_by, which decides who else
//  may change it); its name, the short descr of the
//  dashboard and the HTML content of its page; the answer,
//  the flag a player must send; the points it gives; and
//  hidden, 1 for a challenge kept off the main dashboard —
//  its page still opens from a group or by its address.
//
//  Used by:
//    - Admins\ChallengesController — the challenge pages of
//      the control panel
//    - Users\ChallengesController — the dashboard, the
//      challenge page and the answers
//    - R_Exists and ValidateChallengeSolved
//      (Http/Middleware/)
//    - Admins\UsersGroupsController, Users\GroupsController
//      and Groups (this folder) — the group challenges
//    - ApiController, which no route reaches
// -----------------------------------------------------------


namespace App\Models;

use Illuminate\Database\Eloquent\Model;








// -----------------------------------------------------------
// Challenges
// -----------------------------------------------------------
//
// The answer is hidden, so the arrays the pages are built
// from never carry the flag.
//
//   getIdByPublicId        — the id behind a public id
//   getPartnerIdByPublicId — the partner behind a public id
//
// Used by:
//   - Admins\ChallengesController and
//     Users\ChallengesController
//   - R_Exists, ValidateChallengeSolved and the group pages
// -----------------------------------------------------------

class Challenges extends Model
{

    // The table, which has no created_at / updated_at
    // columns; rememberTokenName belongs to the account
    // models, a plain model never reads it
    protected $table = "cyber_challenges";
    public $timestamps = false;
    protected $rememberTokenName = false;

    // The columns the challenge form fills
    protected $fillable = [
        'public_id', 'partner_id', 'created_by', 'name', 'descr', 'content', 'answer', 'points' , 'hidden'
    ];

    // Left out of toArray and JSON: the flag
    protected $hidden = [
        'answer'
    ];

    // The column types
    protected $casts = [
        'name'=>'string',
        'public_id'=>'string',
        'created_by' => 'integer',
        'partner_id'=>'integer',
        'descr'=>'string',
        'content'=>'string',
        'answer'=>'string',
        'points'=>'integer',
        'hidden'=>'integer'
    ];






    // -----------------------------------------------------------
    // getIdByPublicId
    // -----------------------------------------------------------
    //
    // The id of the challenge with the given public id, or
    // null when there is none.
    //
    // Used by:
    //   - Users\ChallengesController::show and submit_answer
    //   - ValidateChallengeSolved::handle (Http/Middleware/)
    // -----------------------------------------------------------

    public static function getIdByPublicId($publicId)
    {
        return Challenges::select('id')->where('public_id', $publicId)->get()->pluck('id')->first();
    }






    // -----------------------------------------------------------
    // getPartnerIdByPublicId
    // -----------------------------------------------------------
    //
    // The partner id of the challenge with the given public
    // id, or null when there is none.
    //
    // Used by:
    //   - Users\ChallengesController::show — the partner logo
    // -----------------------------------------------------------

    public static function getPartnerIdByPublicId($publicId)
    {
        return Challenges::select('partner_id')->where('public_id', $publicId)->get()->pluck('partner_id')->first();
    }
}
