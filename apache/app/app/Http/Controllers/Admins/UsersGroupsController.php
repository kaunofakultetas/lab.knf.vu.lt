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
//    POST /control-panel/users/vu-groups/delete/{uid}                      — deletes
//    GET  /control-panel/users/vu-groups/users_list/{uid}                  — the members
//    GET  /control-panel/dialog/users/vu-groups/user_add_{type}/{uid}      — member dialog
//    POST /control-panel/dialog/users/vu-groups/users_store                — adds a member
//    POST /control-panel/users/vu-groups/delete_group/{uid}                — removes one
//    GET  /control-panel/users/vu-groups/challenges_list/{uid}             — the challenges
//    GET  /control-panel/dialog/users/vu-groups/challenge_add_{type}/{uid} — challenge dialog
//    POST /control-panel/dialog/users/vu-groups/challenges_store           — adds one
//    POST /control-panel/users/vu-groups/delete_challenge/{uid}            — removes one
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
// A group form without a name, a member form without a VU
// id and a challenge form without an existing challenge go
// back with the error.
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
    // admin, only the admin's own for a lab admin; newest
    // first, 50 to a page of the list.
    //
    // Used by:
    //   - the VU Groups link of the panel's sidebar
    // -----------------------------------------------------------

    public function index(Request $request)
    {
        if(Auth::guard('admins')->user()?->main == 1){
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

        $curr_page = $this->listPage();

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
    // then back with "Created!". A form without a name goes
    // back with the error.
    //
    // Used by:
    //   - the group dialog, for a new group
    // -----------------------------------------------------------

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        Groups::create(['name' => $request->input('name'), 'created_by'=>Auth::guard('admins')->id(), 'created'=>date("Y-m-d H:i:s"), 'public_id'=>Str::uuid()]);

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
    // with "Updated!"; an empty name goes back with the error.
    //
    // Used by:
    //   - the group dialog, for an existing group
    // -----------------------------------------------------------

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        Groups::where(['id' => $request->input('uid')])->update(['name' => $request->input('name')]);

        session()->flash('success', 'Updated!');

        return back();
    }






    // -----------------------------------------------------------
    // destroy
    // -----------------------------------------------------------
    //
    // POST /control-panel/users/vu-groups/delete/{uid}
    //
    // Deletes the group with its challenge places and
    // memberships, then goes back with "Deleted!". The deletes
    // run in a transaction that first locks the group's row,
    // as the adds do, so an add in flight either finishes
    // first and its row goes with the group, or waits and
    // then finds no group.
    //
    // Used by:
    //   - Confirm_Dialog() of custom_admin.js — the delete
    //     buttons of the groups list
    // -----------------------------------------------------------

    public function destroy($uid)
    {
        session()->flash('success', 'Deleted!');

        DB::transaction(function () use ($uid) {
            Groups::whereKey($uid)->lockForUpdate()->first();

            GroupsChallenges::where(['group_id'=>$uid])->delete();
            GroupsUsers::where(['group_id'=>$uid])->delete();
            Groups::destroy($uid);
        });

        return back();
    }






    // -----------------------------------------------------------
    // users_list
    // -----------------------------------------------------------
    //
    // GET /control-panel/users/vu-groups/users_list/{uid}
    //
    // The admins.users-groups-userlist view: the group's
    // members by VU id, the latest added first like every
    // list of the panel, 50 to a page of the list, under the
    // group's name. The page prints the id into its add
    // button's script, which is safe as the route takes
    // digits only (RouteServiceProvider::boot).
    //
    // Known bug LAB-56 (low): the digits-only pattern lets a
    // leading zero through; MySQL reads 010 as 10, but the
    // script reads it as the octal 8, so the add button acts
    // on another record than the page shows. Fix: refuse a
    // leading zero in the patterns, or print the id as a
    // number.
    //
    // Used by:
    //   - the members buttons of the groups list
    // -----------------------------------------------------------

    public function users_list(Request $request, $uid)
    {
        if (count(GroupsUsers::where(['group_id'=>$uid])->get()->toArray()) > 0) {
            $arr = GroupsUsers::where(['cyber_users_groups.group_id'=>$uid])
                ->orderByDesc('id')->get()->toArray();
        } else {
            $arr = array();
        }

        $curr_page = $this->listPage();

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
    // login. A form without a VU id goes back with the error.
    // The check and the insert run in a transaction that
    // first locks the group's row, so an add sent twice at
    // once — a double click — waits, then finds the member
    // there, and stores it once; a group another admin
    // deleted meanwhile goes back with "No group!" and gets
    // no row.
    //
    // Used by:
    //   - the member dialog's form
    // -----------------------------------------------------------

    public function users_store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vu_id' => 'required|max:255'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $found = DB::transaction(function () use ($request) {
            if (Groups::whereKey($request->input('uid'))->lockForUpdate()->first() === null) {
                return false;
            }

            $num = GroupsUsers::where(['vu_id' => $request->input('vu_id'), 'group_id' => $request->input('uid')])->get()->count();

            if($num == 0){
                GroupsUsers::create(['vu_id' => $request->input('vu_id'), 'group_id' => $request->input('uid')]);
                session()->flash('success', 'Added!');
            } else {
                session()->flash('danger', 'User already added!');
            }

            return true;
        });

        if (! $found) {
            return back()->withErrors(['No group!']);
        }

        return back();
    }






    // -----------------------------------------------------------
    // group_destroy
    // -----------------------------------------------------------
    //
    // POST /control-panel/users/vu-groups/delete_group/{uid}
    //
    // Removes the membership row uid — one member of one group
    // — and goes back with "Deleted!". The route's r_exists
    // check (membership_owned) keeps a lab admin to the
    // members of its own groups.
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
    // list, under the group's name. The page prints the id
    // into its add button's script, which is safe as the
    // route takes digits only (RouteServiceProvider::boot).
    //
    // Known bug LAB-56 (low): the digits-only pattern lets a
    // leading zero through; MySQL reads 010 as 10, but the
    // script reads it as the octal 8, so the add button acts
    // on another record than the page shows. Fix: refuse a
    // leading zero in the patterns, or print the id as a
    // number.
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

        $curr_page = $this->listPage();

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
    // POST /control-panel/users/vu-groups/delete_challenge/{uid}
    //
    // Removes the group-challenge row uid and goes back with
    // "Deleted!". The route's r_exists check
    // (group_challenge_owned) keeps a lab admin to the
    // challenges of its own groups.
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
    // other admins' included, hidden ones too. That is the
    // design: the admins share one catalogue of challenges,
    // as the challenges list shows each admin every one,
    // while each lab admin keeps its own groups.
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
    // or "Challenge already added!". A form without a
    // challenge, or naming one that does not exist, goes back
    // with the error. As in users_store, the group's row is
    // locked around the check and the insert, so a double
    // click stores the challenge once, and a group deleted
    // meanwhile goes back with "No group!" and gets no row.
    //
    // Used by:
    //   - the challenge dialog's form
    // -----------------------------------------------------------

    public function challenges_store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'challenge_id' => 'required|exists:cyber_challenges,id'
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $found = DB::transaction(function () use ($request) {
            if (Groups::whereKey($request->input('uid'))->lockForUpdate()->first() === null) {
                return false;
            }

            $num = GroupsChallenges::where(['group_id' => $request->input('uid'), 'challenge_id' => $request->input('challenge_id')])->get()->count();

            if($num == 0){
                GroupsChallenges::create(['challenge_id' => $request->input('challenge_id'), 'group_id' => $request->input('uid')]);
                session()->flash('success', 'Added!');
            } else {
                session()->flash('danger', 'Challenge already added!');
            }

            return true;
        });

        if (! $found) {
            return back()->withErrors(['No group!']);
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

        $members = GroupsUsers::select("vu_id")->where(["group_id"=>$group_id])->get()->pluck("vu_id")->toArray();

        $ranking_table = json_decode(json_encode(UsersGroupsController::get_group_leaderboard($challenges, $members)), 1);

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
    // the group's members ranked by the points the group's
    // challenges gave them, ties going to whose latest of
    // those solves was booked first, with the time of that
    // solve.
    //
    // Used by:
    //   - group_leaderboard (above)
    // -----------------------------------------------------------

    public static function get_group_leaderboard($challenges, $members)
    {
        $data = DB::table('cyber_users')
            ->leftJoin('cyber_challenges_submitted', 'cyber_challenges_submitted.user_id', '=', 'cyber_users.id')
            ->whereIn('cyber_challenges_submitted.challenge_id', $challenges)
            ->whereIn('cyber_users.vu_id', $members)
            ->groupBy('cyber_users.id')
            ->orderByRaw('SUM(`cyber_challenges_submitted`.`points_given`) DESC')
            ->orderByRaw('MAX(`cyber_challenges_submitted`.`id`)')
            ->selectRaw('row_number() OVER (ORDER BY SUM(`cyber_challenges_submitted`.`points_given`) DESC, MAX(`cyber_challenges_submitted`.`id`)) AS `position`, `cyber_users`.`id` AS `id`, SUM(`cyber_challenges_submitted`.`points_given`) AS `points`, `cyber_users`.`email` AS `email`, `cyber_users`.`username` AS `username`, `cyber_users`.`user_type` AS `user_type`, MAX(`cyber_challenges_submitted`.`created`) AS `last_challenge`')
            ->get()->toArray();

        return $data;
    }
}
