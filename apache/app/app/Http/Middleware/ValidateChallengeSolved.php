<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Challenges;
use App\Models\ChallengesSubmitted;

class ValidateChallengeSolved
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
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