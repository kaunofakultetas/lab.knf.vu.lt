<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class ValidateReset
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

        $reset_token = $request->route('reset_token');

        if(User::where(['remember_token'=>$reset_token])->where('remember_token', '<>', '')->whereNotNull('remember_token')->count() == 1) {

                return $next($request);

            } else {

                abort(404);

            }


    }
}