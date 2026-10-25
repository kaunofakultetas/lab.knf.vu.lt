<?php

// -----------------------------------------------------------
//  [*] R_Exists — ownership checks of groups and challenges
//
//  Guards the control panel's group and challenge routes:
//  the group or challenge the request names — or the group
//  a member or a group challenge belongs to, or the
//  challenge a file link belongs to — must belong to the
//  signed-in admin, created_by, unless the admin is a main
//  admin, who may touch them all. A missing group or
//  challenge sends the admin back with "No group!" or "No
//  challenge!", someone else's with "You don't have
//  permissions to do this!" — a page of a group or
//  challenge to its list instead, and a dialog answers with
//  the error in its modal.
//
//  Used by:
//    - app/Http/Kernel.php — the r_exists alias
//    - routes/web.php — the users/vu-groups and challenges
//      routes of the control panel
// -----------------------------------------------------------


namespace App\Http\Middleware;

use Closure;
use Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\Challenges;
use App\Models\ChallengesFiles;
use App\Models\Groups;
use App\Models\GroupsChallenges;
use App\Models\GroupsUsers;








// -----------------------------------------------------------
// R_Exists
// -----------------------------------------------------------
//
//   handle  — the check, which the alias hands one of eight
//             types after its colon
//   refuse  — the refusal of a group, a challenge or a file
//             link, missing or someone else's
//
// Used by:
//   - app/Http/Kernel.php — the r_exists alias
// -----------------------------------------------------------

class R_Exists
{






    // -----------------------------------------------------------
    // handle
    // -----------------------------------------------------------
    //
    // group_owned and challenge_owned read the id from the
    // route's uid, the _uid types from the uid field the forms
    // post — from the form's body first, as the controllers
    // read it, so a uid added to the address cannot have one
    // record checked and another changed; membership_owned and
    // group_challenge_owned read a member's or a group
    // challenge's row id from the route's uid and check the
    // group the row belongs to, and file_owned and
    // file_owned_uid read a file link's id and check the
    // challenge the link belongs to. Whether the admin is a
    // main admin is read from the account on every request, as
    // in Perms. A type other than the eight falls through and
    // returns nothing, which Laravel answers with an empty
    // page; every route names one of the eight.
    //
    // A refusal goes back to the page the admin came from, but
    // a dialog answers with the error in its modal, and a page
    // of a group or challenge refused — one that no longer
    // exists, or one the admin may not change — goes to its
    // list (refuse): it is often the very page an add or an
    // update went back to, and going back there would
    // redirect to itself until the browser gave up. The row
    // types let a row that does not exist through, as removing
    // it again changes nothing; the actions behind them check
    // the admin again on the row they find, as another admin
    // may have created it in the meantime.
    //
    // Used by:
    //   - the r_exists alias — the group and challenge routes
    // -----------------------------------------------------------

    public function handle($request, Closure $next, $type)
    {
        // The group whose id is the route's uid
        if($type =='group_owned'){
            $uid = $request->route('uid');

            if(Groups::where(['id'=>$uid])->count() == 1) {
                if(Auth::guard('admins')->user()?->main == 1 || Groups::where(['id'=>$uid, 'created_by'=>Auth::guard('admins')->id()])->count() == 1){
                    return $next($request);
                } else {
                    return $this->refuse($request, 'You don\'t have permissions to do this!', '/control-panel/users/vu-groups');
                }
            } else {
                return $this->refuse($request, 'No group!', '/control-panel/users/vu-groups');
            }
        }

        // The group whose id the form posts as uid
        if($type =='group_owned_uid'){
            $uid = $request->input('uid');

            if(Groups::where(['id'=>$uid])->count() == 1) {
                if(Auth::guard('admins')->user()?->main == 1 || Groups::where(['id'=>$uid, 'created_by'=>Auth::guard('admins')->id()])->count() == 1){
                    return $next($request);
                } else {
                    return back()->withErrors(['You don\'t have permissions to do this!']);
                }
            } else {
                return back()->withErrors(['No group!']);
            }
        }

        // The challenge whose id is the route's uid
        if($type =='challenge_owned'){
            $uid = $request->route('uid');

            if(Challenges::where(['id'=>$uid])->count() == 1) {
                if(Auth::guard('admins')->user()?->main == 1 || Challenges::where(['id'=>$uid, 'created_by'=>Auth::guard('admins')->id()])->count() == 1){
                    return $next($request);
                } else {
                    return $this->refuse($request, 'You don\'t have permissions to do this!', '/control-panel/challenges');
                }
            } else {
                return $this->refuse($request, 'No challenge!', '/control-panel/challenges');
            }
        }

        // The challenge whose id the form posts as uid
        if($type =='challenge_owned_uid'){
            $uid = $request->input('uid');

            if(Challenges::where(['id'=>$uid])->count() == 1) {
                if(Auth::guard('admins')->user()?->main == 1 || Challenges::where(['id'=>$uid, 'created_by'=>Auth::guard('admins')->id()])->count() == 1){
                    return $next($request);
                } else {
                    return back()->withErrors(['You don\'t have permissions to do this!']);
                }
            } else {
                return back()->withErrors(['No challenge!']);
            }
        }

        // A member of a group, by the membership row's id in the
        // route's uid; a row already gone is let through, as
        // removing it again changes nothing
        if($type =='membership_owned'){
            $group_id = GroupsUsers::where(['id'=>$request->route('uid')])->value('group_id');

            if($group_id === null || Auth::guard('admins')->user()?->main == 1 || Groups::where(['id'=>$group_id, 'created_by'=>Auth::guard('admins')->id()])->count() == 1){
                return $next($request);
            } else {
                return back()->withErrors(['You don\'t have permissions to do this!']);
            }
        }

        // A challenge of a group, by the group-challenge row's id
        // in the route's uid, checked the same way
        if($type =='group_challenge_owned'){
            $group_id = GroupsChallenges::where(['id'=>$request->route('uid')])->value('group_id');

            if($group_id === null || Auth::guard('admins')->user()?->main == 1 || Groups::where(['id'=>$group_id, 'created_by'=>Auth::guard('admins')->id()])->count() == 1){
                return $next($request);
            } else {
                return back()->withErrors(['You don\'t have permissions to do this!']);
            }
        }

        // A file link of a challenge, by the link's id in the
        // route's uid; the challenge it belongs to is checked,
        // and a link already gone is let through, as for the
        // rows above
        if($type =='file_owned'){
            $challenge_id = ChallengesFiles::where(['id'=>$request->route('uid')])->value('challenge_id');

            if($challenge_id === null || Auth::guard('admins')->user()?->main == 1 || Challenges::where(['id'=>$challenge_id, 'created_by'=>Auth::guard('admins')->id()])->count() == 1){
                return $next($request);
            } else {
                return $this->refuse($request, 'You don\'t have permissions to do this!', '/control-panel/challenges');
            }
        }

        // The same, by the link's id the form posts as uid
        if($type =='file_owned_uid'){
            $challenge_id = ChallengesFiles::where(['id'=>$request->input('uid')])->value('challenge_id');

            if($challenge_id === null || Auth::guard('admins')->user()?->main == 1 || Challenges::where(['id'=>$challenge_id, 'created_by'=>Auth::guard('admins')->id()])->count() == 1){
                return $next($request);
            } else {
                return back()->withErrors(['You don\'t have permissions to do this!']);
            }
        }
    }






    // -----------------------------------------------------------
    // refuse
    // -----------------------------------------------------------
    //
    // The refusal of a group, a challenge or a file link — one
    // that does not exist, or another admin's — with the error
    // given: a dialog — a GET under /dialog/ — answers with the
    // error itself, the admins.dialogs.refused view, as the
    // panel loads a dialog into the modal of the page it was
    // opened from and would show there whatever page a
    // redirect led to; another page opened by GET goes to the
    // list given, as going back could land on the refused page
    // itself, the page an update went back to when its admin
    // lost the right to it meanwhile; a POST goes back.
    //
    // Used by:
    //   - handle (above) — group_owned, challenge_owned and
    //     file_owned
    // -----------------------------------------------------------

    private function refuse($request, string $error, string $list)
    {
        if ($request->isMethod('GET') && str_contains($request->path(), '/dialog/')) {
            return response()->view('admins.dialogs.refused', ['error' => $error]);
        }

        if ($request->isMethod('GET')) {
            return redirect($list)->withErrors([$error]);
        }

        return back()->withErrors([$error]);
    }
}
