<?php

// -----------------------------------------------------------
//  [*] ValidateChallengeSolved — one solve per challenge
//
//  Keeps a player from opening or answering a challenge
//  already solved: a booked solve (cyber_challenges_submitted)
//  of the player for the challenge in the route sends the
//  player to the dashboard with "You already solved this
//  challenge!". An unknown challenge passes, for the
//  controller to deal with.
//
//  Used by:
//    - app/Http/Kernel.php — the validate.ifsolved alias
//    - routes/web.php — GET and POST
//      /user/challenge/{public_id}/{public_group_id?}
// -----------------------------------------------------------


namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Challenges;
use App\Models\ChallengesSubmitted;








// -----------------------------------------------------------
// ValidateChallengeSolved
// -----------------------------------------------------------
//
// One method, handle.
//
// Used by:
//   - app/Http/Kernel.php — the validate.ifsolved alias
// -----------------------------------------------------------

class ValidateChallengeSolved
{






    // -----------------------------------------------------------
    // handle
    // -----------------------------------------------------------
    //
    // Counts the player's solves of the challenge with the
    // route's public_id and passes the request when there are
    // none. Two answers sent at once both pass before either
    // is booked; submit_answer then answers the second as
    // solved.
    //
    // Used by:
    //   - the validate.ifsolved alias — the challenge page and
    //     its answer form
    // -----------------------------------------------------------

    public function handle($request, Closure $next)
    {
        $public_id = $request->route('public_id');

        $solved = ChallengesSubmitted::where('user_id', '=', Auth::guard('users')->id())->where('challenge_id', '=', Challenges::getIdByPublicId($public_id))->count();

        if($solved == 0) {
            return $next($request);
        } else {
            return redirect(route('user_dashboard'))->withErrors(['You already solved this challenge!']);
        }
    }
}
