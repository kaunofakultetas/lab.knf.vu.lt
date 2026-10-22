<?php

// -----------------------------------------------------------
//  [*] UsersGroupsController — the study groups
//
//  The control panel's groups: a lab admin creates a group
//  for a class, adds its students by VU id and its
//  challenges, and follows the group's leaderboard; the
//  players then find the group in their sidebar. A lab
//  admin sees and changes only their own groups — the
//  r_exists middleware checks — a main admin all of them.
//
//    GET  /control-panel/users/vu-groups                                   — the list
//    GET  /control-panel/dialog/users/vu-groups/new                        — add dialog
//    POST /control-panel/dialog/users/vu-groups/store                      — adds
//    GET  /control-panel/dialog/users/vu-groups/edit/{uid}                 — rename dialog
//    POST /control-panel/dialog/users/vu-groups/update                     — renames
//    GET  /control-panel/users/vu-groups/delete/{uid}                      — deletes
//    GET  /control-panel/users/vu-groups/users_list/{uid}                  — the members
//    GET  /control-panel/dialog/users/vu-groups/user_add_{type}/{uid}      — member dialog
//    POST /control-panel/dialog/users/vu-groups/users_store                — adds a member
//    GET  /control-panel/users/vu-groups/delete_group/{uid}                — removes one
//    GET  /control-panel/users/vu-groups/challenges_list/{uid}             — the challenges
//    GET  /control-panel/dialog/users/vu-groups/challenge_add_{type}/{uid} — challenge dialog
//    POST /control-panel/dialog/users/vu-groups/challenges_store           — adds one
//    GET  /control-panel/users/vu-groups/delete_challenge/{uid}            — removes one
//    GET  /control-panel/users/vu-groups/leaderboard/{uid}                 — the leaderboard
//
//  Used by:
//    - routes/web.php — the routes above
// -----------------------------------------------------------


namespace App\Http\Controllers\Admins;

use Mail;
use Session;
use App\Http\Controllers\Admins\BaseController;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Challenges;
use App\Models\User;
use App\Models\Groups;
use App\Models\GroupsChallenges;
use App\Models\GroupsUsers;








// -----------------------------------------------------------
// UsersGroupsController
// -----------------------------------------------------------
//
// The group, member and challenge forms are not validated
// (LAB-14): an empty one ends in a server error on a NOT
// NULL column.
//
//   __construct              — the base's layout data
//   index                    — the groups list
//   create                   — the add dialog
//   store                    — adds a group
//   edit                     — the rename dialog
//   update                   — renames a group
//   destroy                  — deletes a group
//   users_list               — the members
//   togroup_form             — the member dialog
//   users_store              — adds a member
//   group_destroy            — removes a member
//   challenges_list          — the group's challenges
//   challenge_destroy        — removes a challenge
//   challenges_togroup_form  — the challenge dialog
//   challenges_store         — adds a challenge
//   group_leaderboard        — the leaderboard
//   get_group_leaderboard    — its query
//
// Used by:
//   - routes/web.php
// -----------------------------------------------------------

class UsersGroupsController extends BaseController
{






    // -----------------------------------------------------------
    // __construct
    // -----------------------------------------------------------
    //
    // Only runs the base constructor, which shares the
    // layout's data.
    //
    // Used by:
    //   - Laravel, when it builds the controller for a request
    // -----------------------------------------------------------

    public function __construct()
    {
        parent::__construct();
    }






    // -----------------------------------------------------------
    // index
    // -----------------------------------------------------------
    //
    // GET /control-panel/users/vu-groups
    //
    // The admins.users-groups view: every group for a main
    // admin, by the session's flag (LAB-28), only the admin's
    // own for a lab admin; newest first, 50 to a page of the
    // list.
    //
    // Used by:
    //   - the Groups link of the panel's sidebar
    // -----------------------------------------------------------

    public function index(Request $request)
    {
        if(Session::get('main') == 1){
            $all_groups = Groups::all()->toArray();
            $groups_ordered = Groups::all()->sortByDesc('id')->toArray();
        } else {
            $all_groups = Groups::where(['created_by'=>Auth::guard('admins')->id()])->get()->toArray();
            $groups_ordered = Groups::where(['created_by'=>Auth::guard('admins')->id()])->get()->sortByDesc('id')->toArray();
        }

        if (count($all_groups) > 0) {
            $arr = $groups_ordered;
        } else {
            $arr = array();
        }

        if (empty($request->get('page')) || ! is_numeric($request->get('page'))) {
            $curr_page = 1;
        } else {
            $curr_page = $request->get('page');
        }

        $perPage = 50;

        $currentItems = array_slice($arr, $perPage * ($curr_page - 1), $perPage);

        $paginator = new LengthAwarePaginator($currentItems, count($arr), $perPage, $curr_page, [
            'path' => url('control-panel/users/vu-groups')
        ]);

        if (count($arr) == 0) {
            $arr = array();
            $paginator = array();
        }

        return view('admins.users-groups', [
            'recordlist' => $paginator
        ]);
    }






    // -----------------------------------------------------------
    // create
    // -----------------------------------------------------------
    //
    // GET /control-panel/dialog/users/vu-groups/new
    //
    // The admins.dialogs.users_groups_form view, empty, for a
    // new group.
    //
    // Used by:
    //   - users_groups_form() of dialogs.js — the add button of
    //     the groups list
    // -----------------------------------------------------------

    public function create()
    {
        return view('admins.dialogs.users_groups_form', [
            'type'=>'new'
        ]);
    }






    // -----------------------------------------------------------
    // store
    // -----------------------------------------------------------
    //
    // POST /control-panel/dialog/users/vu-groups/store
    //
    // Adds a group with the name as sent, owned by the
    // signed-in admin, with a fresh UUID as its public id;
    // then back with "Created!".
    //
    // Used by:
    //   - the group dialog, for a new group
    // -----------------------------------------------------------

    public function store(Request $request)
    {
        Groups::create(['name' => $request->get('name'), 'created_by'=>Auth::guard('admins')->id(), 'created'=>date("Y-m-d H:i:s"), 'public_id'=>Str::uuid()]);

        session()->flash('success', 'Created!');

        return back();
    }






    // -----------------------------------------------------------
    // edit
    // -----------------------------------------------------------
    //
    // GET /control-panel/dialog/users/vu-groups/edit/{uid}
    //
    // The group dialog filled with the group's name.
    //
    // Used by:
    //   - users_groups_form() of dialogs.js — the rename
    //     buttons of the groups list
    // -----------------------------------------------------------

    public function edit(Request $request, $uid)
    {
        return view('admins.dialogs.users_groups_form', [
            'name' => Groups::find($uid)->name,
            'type'=>'edit',
            'uid'=>$uid
        ]);
    }






    // -----------------------------------------------------------
    // update
    // -----------------------------------------------------------
    //
    // POST /control-panel/dialog/users/vu-groups/update
    //
    // Renames the group with the posted uid, then goes back
    // with "Updated!".
    //
    // Used by:
    //   - the group dialog, for an existing group
    // -----------------------------------------------------------

    public function update(Request $request)
    {
        Groups::where(['id' => $request->get('uid')])->update(['name' => $request->get('name')]);

        session()->flash('success', 'Updated!');

        return back();
    }






    // -----------------------------------------------------------
    // destroy
    // -----------------------------------------------------------
    //
    // GET /control-panel/users/vu-groups/delete/{uid}
    //
    // Deletes the group with its challenge places and
    // memberships, then goes back with "Deleted!" — a change
    // made by a plain link (LAB-19).
    //
    // Used by:
    //   - Confirm_Dialog() of custom_admin.js — the delete
    //     buttons of the groups list
    // -----------------------------------------------------------

    public function destroy($uid)
    {
        session()->flash('success', 'Deleted!');

        GroupsChallenges::where(['group_id'=>$uid])->delete();
        GroupsUsers::where(['group_id'=>$uid])->delete();
        Groups::destroy($uid);

        return back();
    }






    // -----------------------------------------------------------
    // users_list
    // -----------------------------------------------------------
    //
    // GET /control-panel/users/vu-groups/users_list/{uid}
    //
    // The admins.users-groups-userlist view: the group's
    // members by VU id, 50 to a page of the list, under the
    // group's name. The sort names a column the rows do not
    // carry, so the list keeps the table's order.
    //
    // Used by:
    //   - the members buttons of the groups list
    // -----------------------------------------------------------

    public function users_list(Request $request, $uid)
    {
        if (count(GroupsUsers::where(['group_id'=>$uid])->get()->toArray()) > 0) {
            $arr = GroupsUsers::where(['cyber_users_groups.group_id'=>$uid])
                ->get()->sortByDesc('cyber_users_groups.id')->toArray();
        } else {
            $arr = array();
        }

        if (empty($request->get('page')) || ! is_numeric($request->get('page'))) {
            $curr_page = 1;
        } else {
            $curr_page = $request->get('page');
        }

        $perPage = 50;

        $currentItems = array_slice($arr, $perPage * ($curr_page - 1), $perPage);

        $paginator = new LengthAwarePaginator($currentItems, count($arr), $perPage, $curr_page, [
            'path' => url('control-panel/users/vu-groups/users_list/'.$uid)
        ]);

        if (count($arr) == 0) {
            $arr = array();
            $paginator = array();
        }

        return view('admins.users-groups-userlist', [
            'recordlist' => $paginator,
            'group_name' => Groups::find($uid)->name,
            'uid'=>$uid
        ]);
    }






    // -----------------------------------------------------------
    // togroup_form
    // -----------------------------------------------------------
    //
    // GET /control-panel/dialog/users/vu-groups/user_add_{type}/{uid}
    //
    // The admins.dialogs.users_togroups_form view, which asks
    // for a VU id. The pages open it as new only; its other
    // variant posts to a users_update route that does not
    // exist.
    //
    // Used by:
    //   - users_togroups_form() of dialogs.js — the add button
    //     of the members page
    // -----------------------------------------------------------

    public function togroup_form(Request $request, $type, $uid)
    {
        return view('admins.dialogs.users_togroups_form', [
            'type'=>$type,
            'uid'=>$uid
        ]);
    }






    // -----------------------------------------------------------
    // users_store
    // -----------------------------------------------------------
    //
    // POST /control-panel/dialog/users/vu-groups/users_store
    //
    // Adds the VU id to the group with the posted uid unless
    // it is there already, then goes back with "Added!" or
    // "User already added!". The id need not belong to an
    // account yet: it becomes one at the student's first VU
    // login.
    //
    // Used by:
    //   - the member dialog's form
    // -----------------------------------------------------------

    public function users_store(Request $request)
    {
        $num = GroupsUsers::where(['vu_id' => $request->get('vu_id'), 'group_id' => $request->get('uid')])->get()->count();

        if($num == 0){
            GroupsUsers::create(['vu_id' => $request->get('vu_id'), 'group_id' => $request->get('uid')]);
            session()->flash('success', 'Added!');
        } else {
            session()->flash('danger', 'User already added!');
        }

        return back();
    }






    // -----------------------------------------------------------
    // group_destroy
    // -----------------------------------------------------------
    //
    // GET /control-panel/users/vu-groups/delete_group/{uid}
    //
    // Removes the membership row uid — one member of one group
    // — and goes back with "Deleted!", a change made by a
    // plain link (LAB-19).
    //
    // Known bug LAB-13 (medium): neither this route nor the
    // one of challenge_destroy runs an ownership check, unlike
    // every other group route, and both take bare row ids
    // (routes/web.php), so any admin can remove members and
    // challenges from any group. Fix: check that the row's
    // group belongs to the admin, as r_exists does for the
    // group itself.
    //
    // Used by:
    //   - Confirm_Dialog() of custom_admin.js — the remove
    //     buttons of the members page
    // -----------------------------------------------------------

    public function group_destroy($uid)
    {
        session()->flash('success', 'Deleted!');

        GroupsUsers::destroy($uid);

        return back();
    }






    // -----------------------------------------------------------
    // challenges_list
    // -----------------------------------------------------------
    //
    // GET /control-panel/users/vu-groups/challenges_list/{uid}
    //
    // The admins.users-groups-challengeslist view: the group's
    // challenges by name, newest first, 50 to a page of the
    // list, under the group's name.
    //
    // Used by:
    //   - the challenges buttons of the groups list
    // -----------------------------------------------------------

    public function challenges_list(Request $request, $uid)
    {
        if (count(GroupsChallenges::where(['group_id'=>$uid])->get()->toArray()) > 0) {
            $arr = GroupsChallenges::
                selectraw("cyber_groups_challenges.id as id, cyber_challenges.name as name")->
                leftJoin("cyber_challenges", "cyber_challenges.id", "cyber_groups_challenges.challenge_id")->
                where(['group_id'=>$uid])->
                groupBy("cyber_challenges.id")->
                get()->sortByDesc('id')->toArray();
        } else {
            $arr = array();
        }

        if (empty($request->get('page')) || ! is_numeric($request->get('page'))) {
            $curr_page = 1;
        } else {
            $curr_page = $request->get('page');
        }

        $perPage = 50;

        $currentItems = array_slice($arr, $perPage * ($curr_page - 1), $perPage);

        $paginator = new LengthAwarePaginator($currentItems, count($arr), $perPage, $curr_page, [
            'path' => url('control-panel/users/vu-groups/challenges_list/'.$uid)
        ]);

        if (count($arr) == 0) {
            $arr = array();
            $paginator = array();
        }

        return view('admins.users-groups-challengeslist', [
            'recordlist' => $paginator,
            'group_name' => Groups::find($uid)->name,
            'uid'=>$uid
        ]);
    }






    // -----------------------------------------------------------
    // challenge_destroy
    // -----------------------------------------------------------
    //
    // GET /control-panel/users/vu-groups/delete_challenge/{uid}
    //
    // Removes the group-challenge row uid and goes back with
    // "Deleted!", a change made by a plain link (LAB-19). No
    // ownership check (LAB-13, see group_destroy).
    //
    // Used by:
    //   - Confirm_Dialog() of custom_admin.js — the remove
    //     buttons of the group's challenges page
    // -----------------------------------------------------------

    public function challenge_destroy($uid)
    {
        session()->flash('success', 'Deleted!');

        GroupsChallenges::destroy($uid);

        return back();
    }






    // -----------------------------------------------------------
    // challenges_togroup_form
    // -----------------------------------------------------------
    //
    // GET /control-panel/dialog/users/vu-groups/challenge_add_{type}/{uid}
    //
    // The admins.dialogs.users_challengestogroups_form view
    // with every challenge of the lab not in the group yet —
    // other admins' included.
    //
    // Used by:
    //   - users_groupschallenges_form() of dialogs.js — the
    //     add button of the group's challenges page
    // -----------------------------------------------------------

    public function challenges_togroup_form(Request $request, $type, $uid)
    {
        $already_selected = GroupsChallenges::where(["group_id"=>$uid])->get()->pluck('challenge_id')->toArray();

        $challenge_list = Challenges::wherenotin("id", $already_selected)->get()->pluck('name', 'id')->toArray();

        return view('admins.dialogs.users_challengestogroups_form', [
            'type'=>$type,
            'uid'=>$uid,
            'challenge_list'=>$challenge_list
        ]);
    }






    // -----------------------------------------------------------
    // challenges_store
    // -----------------------------------------------------------
    //
    // POST /control-panel/dialog/users/vu-groups/challenges_store
    //
    // Adds the challenge to the group with the posted uid
    // unless it is there already, then goes back with "Added!"
    // or "Challenge already added!".
    //
    // Used by:
    //   - the challenge dialog's form
    // -----------------------------------------------------------

    public function challenges_store(Request $request)
    {
        $num = GroupsChallenges::where(['group_id' => $request->get('uid'), 'challenge_id' => $request->get('challenge_id')])->get()->count();

        if($num == 0){
            GroupsChallenges::create(['challenge_id' => $request->get('challenge_id'), 'group_id' => $request->get('uid')]);
            session()->flash('success', 'Added!');
        } else {
            session()->flash('danger', 'Challenge already added!');
        }

        return back();
    }






    // -----------------------------------------------------------
    // group_leaderboard
    // -----------------------------------------------------------
    //
    // GET /control-panel/users/vu-groups/leaderboard/{uid}
    //
    // The admins.groups_leaderboard view: the ranking of
    // get_group_leaderboard with ordinal positions and how
    // long ago each last solve was, through this folder's
    // SettingsController copies, under the group's name.
    //
    // Used by:
    //   - the leaderboard buttons of the groups list
    // -----------------------------------------------------------

    public function group_leaderboard(Request $request, $uid)
    {
        $group_id = $uid;

        $challenges = GroupsChallenges::select("challenge_id")->where(["group_id"=>$group_id])->get()->pluck("challenge_id")->toArray();

        $ranking_table = json_decode(json_encode(UsersGroupsController::get_group_leaderboard($challenges)), 1);

        $ordinalUsers = collect($ranking_table)->map(function($user) {
            $user['position'] = SettingsController::ordinal($user['position']);
            return $user;
        });

        $ordinalUsers = collect($ordinalUsers)->map(function($user) {
            $user['last_challenge'] = SettingsController::time_elapsed_string($user['last_challenge']);
            return $user;
        });

        return view('admins.groups_leaderboard', [
            'ranking_table'=>$ordinalUsers,
            'group_name'=>Groups::select("name")->where(['id'=>$group_id])->get()->pluck('name')->first(),
            'group_id'=>$group_id
        ]);
    }






    // -----------------------------------------------------------
    // get_group_leaderboard
    // -----------------------------------------------------------
    //
    // A copy of Users\GroupsController::get_group_leaderboard:
    // every player with a solve of one of the challenges —
    // members or not — ranked by lab-wide points, with a last
    // solve that is whichever MySQL reads first (as in
    // LAB-01).
    //
    // Used by:
    //   - group_leaderboard (above)
    // -----------------------------------------------------------

    public static function get_group_leaderboard($challenges)
    {
        $data = DB::table('cyber_users')
            ->leftJoin('cyber_challenges_submitted', 'cyber_challenges_submitted.user_id', '=', 'cyber_users.id')
            ->whereIn('cyber_challenges_submitted.challenge_id', $challenges)
            ->groupBy('cyber_users.id')
            ->orderByDesc('cyber_users.points')
            ->orderBy('cyber_challenges_submitted.created')
            ->selectRaw('row_number() OVER (ORDER BY `cyber_users`.`points` DESC) AS `position`, `cyber_users`.`id` AS `id`, `cyber_users`.`points` AS `points`, `cyber_users`.`email` AS `email`, `cyber_users`.`username` AS `username`, `cyber_users`.`user_type` AS `user_type`, `cyber_challenges_submitted`.`created` AS `last_challenge`')
            ->get()->toArray();

        return $data;
    }
}
