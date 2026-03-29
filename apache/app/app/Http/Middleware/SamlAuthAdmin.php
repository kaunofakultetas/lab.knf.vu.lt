<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Admins\AdminAuthController;
use App\Models\Admin;

class SamlAuthAdmin
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
       
        Auth::guard('admins')->loginUsingId(AdminAuthController::check_admin($attributes));

        return $next($request);

    }
}