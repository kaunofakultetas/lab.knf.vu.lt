<?php

// -----------------------------------------------------------
//  [*] Groups — the study groups
//
//  One cyber_groups row per group an admin creates for a
//  class: public_id, the random id of its pages; created_by,
//  the admin who owns it; its name and creation time. The
//  members are in GroupsUsers, the challenges in
//  GroupsChallenges.
//
//  Used by:
//    - Admins\UsersGroupsController — the groups pages of
//      the control panel
//    - Users\GroupsController and Users\ChallengesController
//      — the group pages and their challenges
//    - GroupExists and R_Exists (Http/Middleware/)
//    - Admins\LeaderboardController::dashboard
//    - ApiController, which no route reaches
// -----------------------------------------------------------


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Models\Challenges;
use App\Models\ChallengesSubmitted;
use App\Models\User;
use App\Models\Groups;
use App\Models\GroupsChallenges;
use App\Models\GroupsUsers;








// -----------------------------------------------------------
// Groups
// -----------------------------------------------------------
//
//   groupPointsPercent — the player's share of a group's
//                        points
//   groupExists        — whether a public id names a group
//
// Used by:
//   - Admins\UsersGroupsController, Users\GroupsController
//     and Users\ChallengesController
//   - GroupExists and R_Exists
// -----------------------------------------------------------

class Groups extends Model
{

    // The table, which has no created_at / updated_at
    // columns; rememberTokenName belongs to the account
    // models, a plain model never reads it
    protected $table = "cyber_groups";
    public $timestamps = false;
    protected $rememberTokenName = false;

    // The columns, the id included
    protected $fillable = [
        'id',
        'public_id',
        'created_by',
        'name',
        'created'
    ];

    // Empty: toArray hands out every column
    protected $hidden = [];

    // The column types
    protected $casts = [
        'id' => 'integer',
        'created_by' => 'integer',
        'public_id' => 'string',
        'name' => 'string',
        'created' => 'datetime'
    ];






    // -----------------------------------------------------------
    // groupPointsPercent
    // -----------------------------------------------------------
    //
    // The signed-in player's progress in a group, as a whole
    // percentage: the points the player's solves of the
    // group's challenges gave, over the points of all of them;
    // 0 for a group without challenges. The solves count with
    // the points they gave then, the total with today's, so a
    // challenge repriced after a solve skews the share — past
    // 100 even.
    //
    // Used by:
    //   - Users\GroupsController::index — the group page's
    //     progress bar
    // -----------------------------------------------------------

    public static function groupPointsPercent($group_id)
    {
        $user_id = Auth::guard('users')->id();
        $group_challenges_arr = GroupsChallenges::where(['group_id'=>$group_id])->get()->pluck("challenge_id")->toArray();
        $solved_challenges = ChallengesSubmitted::where(['user_id'=>$user_id])->whereIn("challenge_id", $group_challenges_arr)->get()->pluck('challenge_id')->toArray();

        $sum_submitedpoints = ChallengesSubmitted::selectraw("SUM(points_given) as total_points")
            ->where(['user_id'=>$user_id])
            ->whereIn("challenge_id", $solved_challenges)
            ->get()->pluck('total_points')->first();

        $sum_totalpoints = Challenges::selectraw("SUM(points) as total_points")
            ->whereIn("id", $group_challenges_arr)
            ->get()->pluck('total_points')->first();

        if($sum_totalpoints !=0 && $sum_totalpoints != ''){
            $percentage = $sum_submitedpoints * 100 / $sum_totalpoints;
        } else {
            $percentage = 0;
        }
        return round($percentage);
    }






    // -----------------------------------------------------------
    // groupExists
    // -----------------------------------------------------------
    //
    // Whether exactly one group has the given public id.
    //
    // Used by:
    //   - Users\ChallengesController::show and submit_answer —
    //     the group a challenge was opened from
    // -----------------------------------------------------------

    public static function groupExists($public_id)
    {
        $num = Groups::where(['public_id'=>$public_id])->count();

        if($num == 1){
            return true;
        } else {
            return false;
        }
    }
}
