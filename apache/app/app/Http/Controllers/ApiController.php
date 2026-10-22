<?php

// -----------------------------------------------------------
//  [*] ApiController — an unused data export
//
//  Eight actions that would hand out the lab's tables as
//  JSON — players, categories, challenges, the solve feed,
//  the solves, groups, members and group challenges. No
//  route names them, so none is reachable. Dead code, kept
//  as found.
//
//  Used by:
//    - nothing at the moment
// -----------------------------------------------------------


namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Categories;
use App\Models\Challenges;
use App\Models\ChallengesHistory;
use App\Models\ChallengesSubmitted;
use App\Models\Groups;
use App\Models\GroupsUsers;
use App\Models\GroupsChallenges;








// -----------------------------------------------------------
// ApiController
// -----------------------------------------------------------
//
// Every action returns the rows of one table, a few columns
// each, as an array Laravel would send as JSON. The flags of
// the challenges are not among the columns.
//
// Used by:
//   - nothing at the moment
// -----------------------------------------------------------

class ApiController extends Controller
{






    // -----------------------------------------------------------
    // get_users
    // -----------------------------------------------------------
    //
    // Every player's id, VU id, username and points.
    //
    // Used by:
    //   - nothing at the moment
    // -----------------------------------------------------------

    public function get_users()
    {
        return User::select(['id','vu_id','username','points'])->get()->toArray();
    }






    // -----------------------------------------------------------
    // get_categories
    // -----------------------------------------------------------
    //
    // Every category's id and name.
    //
    // Used by:
    //   - nothing at the moment
    // -----------------------------------------------------------

    public function get_categories()
    {
        return Categories::select(['id','name'])->get()->toArray();
    }






    // -----------------------------------------------------------
    // get_challenges
    // -----------------------------------------------------------
    //
    // Every challenge's id, name, points and hidden flag.
    //
    // Used by:
    //   - nothing at the moment
    // -----------------------------------------------------------

    public function get_challenges()
    {
        return Challenges::select(['id','name','points','hidden'])->get()->toArray();
    }






    // -----------------------------------------------------------
    // get_challenges_history
    // -----------------------------------------------------------
    //
    // The first 50 rows of the solve feed, in table order.
    //
    // Used by:
    //   - nothing at the moment
    // -----------------------------------------------------------

    public function get_challenges_history()
    {
        return ChallengesHistory::select(['id','created_time','created','user_id','challenge_id'])->limit(50)->get()->toArray();
    }






    // -----------------------------------------------------------
    // get_challenges_submitted
    // -----------------------------------------------------------
    //
    // Every solve, without the address it came from.
    //
    // Used by:
    //   - nothing at the moment
    // -----------------------------------------------------------

    public function get_challenges_submitted()
    {
        return ChallengesSubmitted::select(['id','user_id','challenge_id','points_given','created'])->get()->toArray();
    }






    // -----------------------------------------------------------
    // get_groups
    // -----------------------------------------------------------
    //
    // Every group's id, name and creation time.
    //
    // Used by:
    //   - nothing at the moment
    // -----------------------------------------------------------

    public function get_groups()
    {
        return Groups::select(['id','name','created'])->get()->toArray();
    }






    // -----------------------------------------------------------
    // get_groups_users
    // -----------------------------------------------------------
    //
    // Every group membership: the group and the member's VU
    // id.
    //
    // Used by:
    //   - nothing at the moment
    // -----------------------------------------------------------

    public function get_groups_users()
    {
        return GroupsUsers::select(['id','group_id','vu_id'])->get()->toArray();
    }






    // -----------------------------------------------------------
    // get_groups_challenges
    // -----------------------------------------------------------
    //
    // Every challenge of every group.
    //
    // Used by:
    //   - nothing at the moment
    // -----------------------------------------------------------

    public function get_groups_challenges()
    {
        return GroupsChallenges::select(['id','group_id','challenge_id'])->get()->toArray();
    }
}
