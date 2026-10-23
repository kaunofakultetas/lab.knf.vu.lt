<?php

// -----------------------------------------------------------
//  [*] ChallengesFiles — the file links of a challenge
//
//  One cyber_challenges_files row per link an admin attaches
//  to a challenge: a name and the address of the file — a
//  link, not an upload — which the challenge page lists for
//  the player to download.
//
//  Used by:
//    - Admins\ChallengesController — the file list of a
//      challenge, its dialog, store, update and delete
//    - Users\ChallengesController::show — through
//      getFilesById
//    - R_Exists (Http/Middleware/) — the challenge a link
//      belongs to, for file_owned and file_owned_uid
// -----------------------------------------------------------


namespace App\Models;

use Illuminate\Database\Eloquent\Model;








// -----------------------------------------------------------
// ChallengesFiles
// -----------------------------------------------------------
//
// Columns: challenge_id, name and file_url.
//
//   getFilesById — a challenge's links
//
// Used by:
//   - Admins\ChallengesController and
//     Users\ChallengesController
//   - R_Exists — the challenge a link belongs to
// -----------------------------------------------------------

class ChallengesFiles extends Model
{

    // The table, which has no created_at / updated_at
    // columns; rememberTokenName belongs to the account
    // models, a plain model never reads it
    protected $table = "cyber_challenges_files";
    public $timestamps = false;
    protected $rememberTokenName = false;

    // The columns of a link
    protected $fillable = [
        'challenge_id', 'name', 'file_url'
    ];

    // Empty: toArray hands out every column
    protected $hidden = [
    ];

    // The column types
    protected $casts = [
        'challenge_id'=>'integer',
        'name'=>'string',
        'file_url'=>'string'
    ];






    // -----------------------------------------------------------
    // getFilesById
    // -----------------------------------------------------------
    //
    // Every link of the challenge with the given id, as an
    // array of rows; an empty array when it has none.
    //
    // Used by:
    //   - Users\ChallengesController::show — the challenge
    //     page's downloads
    // -----------------------------------------------------------

    public static function getFilesById($id)
    {
        $files = ChallengesFiles::where('challenge_id', $id)->get()->toArray();

        return $files;
    }
}
