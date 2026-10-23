<?php

// -----------------------------------------------------------
//  [*] R_Exists — ownership checks of groups and challenges
//
//  Guards the control panel's group and challenge routes:
//  the group or challenge the request names — or the group a
//  member or a group challenge belongs to — must belong to
//  the signed-in admin, created_by, unless the admin is a
//  main admin, who may touch them all. A missing group or
//  challenge sends the admin back with "No group!" or "No
//  challenge!", someone else's with "You don't have
//  permissions to do this!".
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
use App\Models\Groups;
use App\Models\GroupsChallenges;
use App\Models\GroupsUsers;








// -----------------------------------------------------------
// R_Exists
// -----------------------------------------------------------
//
// One method, handle, which the alias hands one of six
// types after its colon.
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
    // post; membership_owned and group_challenge_owned read a
    // member's or a group challenge's row id from the route's
    // uid and check the group the row belongs to. Whether the
    // admin is a main admin is read from the account on every
    // request, as in Perms. A type other than the six falls
    // through and returns nothing, which Laravel answers with
    // an empty page; every route names one of the six. Two
    // file-link routes hand it a link's id as a challenge id
    // (LAB-06, routes/web.php).
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
                    return back()->withErrors(['You don\'t have permissions to do this!']);
                }
            } else {
                return back()->withErrors(['No group!']);
            }
        }

        // The group whose id the form posts as uid
        if($type =='group_owned_uid'){
            $uid = $request->get('uid');

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
                    return back()->withErrors(['You don\'t have permissions to do this!']);
                }
            } else {
                return back()->withErrors(['No challenge!']);
            }
        }

        // The challenge whose id the form posts as uid
        if($type =='challenge_owned_uid'){
            $uid = $request->get('uid');

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
    }
}
