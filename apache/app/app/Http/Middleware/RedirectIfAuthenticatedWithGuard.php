<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticatedWithGuard
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
   
        public function handle(Request $request, Closure $next)
        {
            if (Auth::guard('admins')->check()) { 
                return redirect(route("admin_dashboard"));
            } 
            if (Auth::guard('users')->check()) { 
                return redirect(route("user_dashboard"));
            }
              
            return $next($request);
    }
    
}
