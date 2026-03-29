<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\Certs;

class CertVerify
{
  
    public function handle($request, Closure $next)
    {  
              
        $public_id =  $request->route('public_id');
         
        $user_id = Auth::guard('cert')->id();
        
            if (Certs::where(['public_id' => $public_id, 'user_id' => $user_id])->count() == 1) {
                
                return $next($request);
            } else {
                
                abort(404);
            }
        
       
    }
    
    
}