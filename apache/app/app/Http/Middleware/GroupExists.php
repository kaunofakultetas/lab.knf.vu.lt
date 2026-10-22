<?php

// -----------------------------------------------------------
//  [*] GroupExists — group pages for the group's members
//
//  Lets a player into a group's pages only when the group
//  exists and the player is one of its members. Members are
//  stored by VU id (cyber_users_groups.vu_id), so a player
//  with a local account is never a member. Anything else
//  is a 404.
//
//  Used by:
//    - app/Http/Kernel.php — the group.exists alias
//    - routes/web.php — /user/group/{public_id} and
//      /user/group/leaderboard/{public_id}
// -----------------------------------------------------------


namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Groups;
use App\Models\GroupsUsers;








// -----------------------------------------------------------
// GroupExists
// -----------------------------------------------------------
//
// One method, handle.
//
// Used by:
//   - app/Http/Kernel.php — the group.exists alias
// -----------------------------------------------------------

class GroupExists
{






    // -----------------------------------------------------------
    // handle
    // -----------------------------------------------------------
    //
    // Looks up the group by the route's public_id and the
    // signed-in player's VU id, and passes the request when a
    // membership joins the two; otherwise 404.
    //
    // Used by:
    //   - the group.exists alias — the two group routes
    // -----------------------------------------------------------

    public function handle($request, Closure $next)
    {
        $public_id =  $request->route('public_id');
        $group_id = Groups::where(['public_id'=>$public_id])->get()->pluck('id')->first();
        $user_id = Auth::guard('users')->id();
        $vu_id = User::where(['id'=>$user_id])->get()->pluck('vu_id')->first();

        if(GroupsUsers::where(['group_id' => $group_id, 'vu_id' => $vu_id])->count() > 0) {
            return $next($request);
        } else {
            abort(404);
        }
    }
}
