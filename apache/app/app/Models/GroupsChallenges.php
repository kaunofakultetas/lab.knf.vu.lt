<?php

// -----------------------------------------------------------
//  [*] GroupsChallenges — the challenges of a group
//
//  The link table between groups and challenges: one
//  cyber_groups_challenges row per challenge an admin added
//  to a group. A group's page lists these challenges, and
//  its leaderboard counts only their solves.
//
//  Used by:
//    - Admins\UsersGroupsController — the group's challenge
//      list, its add dialog and the removals
//    - Admins\ChallengesController::destroy
//    - Users\GroupsController — the group page and its
//      leaderboard
//    - Groups::groupPointsPercent (this folder)
//    - ApiController, which no route reaches
// -----------------------------------------------------------


namespace App\Models;

use Illuminate\Database\Eloquent\Model;








// -----------------------------------------------------------
// GroupsChallenges
// -----------------------------------------------------------
//
// Columns: id, group_id and challenge_id.
//
// Used by:
//   - Admins\UsersGroupsController, Admins\ChallengesController,
//     Users\GroupsController, Groups and ApiController
// -----------------------------------------------------------

class GroupsChallenges extends Model
{

    // The table, which has no created_at / updated_at
    // columns; rememberTokenName belongs to the account
    // models, a plain model never reads it
    protected $table = "cyber_groups_challenges";
    public $timestamps = false;
    protected $rememberTokenName = false;

    // The columns, the id included
    protected $fillable = [
        'id',
        'group_id',
        'challenge_id'
    ];

    // Empty: toArray hands out every column
    protected $hidden = [];

    // The ids come back as integers
    protected $casts = [
        'id' => 'integer',
        'group_id' => 'integer',
        'challenge_id' => 'integer'
    ];
}
