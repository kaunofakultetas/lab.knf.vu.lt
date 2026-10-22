<?php

// -----------------------------------------------------------
//  [*] Partners — the partners behind the challenges
//
//  One cyber_partners row per partner organisation: its
//  name and picture, the file name of its logo in the
//  upload folder. Every challenge names a partner, whose
//  logo its page shows.
//
//  Used by:
//    - Admins\PartnersController — the partners page and
//      its dialogs
//    - Admins\ChallengesController::create and edit — the
//      partner list of the challenge form
//    - Users\ChallengesController::show — the challenge
//      page's logo
// -----------------------------------------------------------


namespace App\Models;

use Illuminate\Database\Eloquent\Model;








// -----------------------------------------------------------
// Partners
// -----------------------------------------------------------
//
// Columns: id, name and picture.
//
// Used by:
//   - Admins\PartnersController, Admins\ChallengesController
//     and Users\ChallengesController
// -----------------------------------------------------------

class Partners extends Model
{

    // The table, which has no created_at / updated_at
    // columns; rememberTokenName belongs to the account
    // models, a plain model never reads it
    protected $table = "cyber_partners";
    public $timestamps = false;
    protected $rememberTokenName = false;

    // The columns, the id included
    protected $fillable = [
        'id',
        'name',
        'picture'
    ];

    // Empty: toArray hands out every column
    protected $hidden = [];

    // The column types
    protected $casts = [
        'id' => 'integer',
        'name' => 'string',
        'picture' => 'string'
    ];
}
