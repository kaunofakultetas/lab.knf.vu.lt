<?php

// -----------------------------------------------------------
//  [*] ChallengesVotes — the thumbs up and down
//
//  One cyber_challenges_votes row per player and challenge
//  the player voted on, holding up or down. The table's
//  unique key on user_id and challenge_id keeps a second
//  vote out, even two sent at once. The counts a page shows
//  are counted from the rows of accounts that still exist,
//  so they last as long as the database, and a deleted
//  account or challenge takes its votes along.
//
//  Used by:
//    - Users\ChallengesController — vote casts one, index
//      and show count them
//    - Admins\UsersController::destroy and
//      Admins\ChallengesController::destroy — delete an
//      account's or a challenge's votes
// -----------------------------------------------------------


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;








// -----------------------------------------------------------
// ChallengesVotes
// -----------------------------------------------------------
//
// Columns: user_id, challenge_id, vote — up or down — and
// created.
//
//   tally — the up and down counts of challenges
//
// Used by:
//   - Users\ChallengesController
//   - the account and challenge deletions of the control
//     panel
// -----------------------------------------------------------

class ChallengesVotes extends Model
{

    // The table, which has no created_at / updated_at
    // columns; rememberTokenName belongs to the account
    // models, a plain model never reads it
    protected $table = "cyber_challenges_votes";
    public $timestamps = false;
    protected $rememberTokenName = false;

    // The columns of a vote
    protected $fillable = [
        'user_id', 'challenge_id', 'vote', 'created'
    ];

    // Empty: toArray hands out every column
    protected $hidden = [
    ];

    // The column types
    protected $casts = [
        'user_id' => 'integer',
        'challenge_id' => 'integer',
        'created' => 'datetime'
    ];






    // -----------------------------------------------------------
    // tally
    // -----------------------------------------------------------
    //
    // The up and down counts of the challenges with the ids
    // given, keyed by challenge id, each as up and down. A
    // challenge nobody voted on is left out, and reads as no
    // votes. Only the votes of accounts that still exist
    // count: a vote cast while its account was being deleted
    // can be written after the account's votes were deleted,
    // and would otherwise stay counted for good.
    //
    // Used by:
    //   - Users\ChallengesController — index and show
    // -----------------------------------------------------------

    public static function tally(array $challenge_ids): array
    {
        return self::whereIn('challenge_id', $challenge_ids)
            ->whereIn('user_id', User::select('id'))
            ->groupBy('challenge_id')
            ->selectRaw("`challenge_id`, SUM(`vote` = 'up') AS `up`, SUM(`vote` = 'down') AS `down`")
            ->get()
            ->mapWithKeys(fn ($row) => [$row->challenge_id => ['up' => (int) $row->up, 'down' => (int) $row->down]])
            ->all();
    }
}
