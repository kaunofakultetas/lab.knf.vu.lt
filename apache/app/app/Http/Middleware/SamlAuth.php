<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Users\UserAuthController;
use App\Models\User;

class SamlAuth
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

        $as = new \SimpleSAML\Auth\Simple('default-sp');
        $as->requireAuth();

        $attributes = $as->getAttributes();
          
        Auth::guard('users')->loginUsingId(UserAuthController::check_member($attributes));

        return $next($request);

    }
}